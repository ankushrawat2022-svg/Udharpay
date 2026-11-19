<?php
declare(strict_types=1);

final class MerchantController
{
    public static function dashboard(array $user): never
    {
        RoleMiddleware::requireRole($user, ['merchant']);
        $shops = ShopModel::listByMerchantUser((int)$user['id']);
        $shopId = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : (int)($shops[0]['id'] ?? 0);

        if ($shopId > 0 && !ShopModel::verifyMerchantOwnsShop((int)$user['id'], $shopId)) {
            Response::error('Unauthorized shop selection.', 403);
        }

        $pdo = Database::connection();
        $bills = $shopId > 0 ? CreditBillModel::listByShop($shopId) : [];
        $ledger = $shopId > 0 ? LedgerModel::listEntries($shopId, null) : [];
        $payments = $shopId > 0 ? PaymentModel::listByShop($shopId) : [];

        $custStmt = $pdo->prepare(
            'SELECT c.id, c.customer_code, c.kyc_status, u.full_name, u.phone,
                    COALESCE(ca.total_credited, 0) AS total_credited,
                    COALESCE(ca.total_repaid, 0) AS total_repaid,
                    COALESCE(ca.outstanding_balance, 0) AS outstanding_balance,
                    COALESCE(cl.credit_limit, s.default_credit_limit) AS credit_limit,
                    COALESCE(cl.is_credit_blocked, 0) AS is_credit_blocked
             FROM customers c
             INNER JOIN users u ON u.id = c.user_id
             INNER JOIN shops s ON s.id = :shop_id_s
             LEFT JOIN credit_accounts ca ON ca.customer_id = c.id AND ca.shop_id = :shop_id_a
             LEFT JOIN credit_limits cl ON cl.customer_id = c.id AND cl.shop_id = :shop_id_l
             ORDER BY outstanding_balance DESC, u.full_name ASC'
        );
        $customers = [];
        if ($shopId > 0) {
            $custStmt->execute(['shop_id_s' => $shopId, 'shop_id_a' => $shopId, 'shop_id_l' => $shopId]);
            $customers = $custStmt->fetchAll();
            foreach ($customers as &$c) {
                $c['risk_review'] = RiskService::evaluateForMerchantReview($pdo, $shopId, (int)$c['id']);
            }
        }

        $totalOutstanding = array_reduce($customers, fn($carry, $c) => $carry + (float)$c['outstanding_balance'], 0.0);
        $totalPaid = array_reduce($customers, fn($carry, $c) => $carry + (float)$c['total_repaid'], 0.0);

        Response::success([
            'shops' => $shops,
            'active_shop_id' => $shopId,
            'summary' => [
                'total_outstanding' => round($totalOutstanding, 2),
                'total_paid' => round($totalPaid, 2),
                'pending_approval_bills' => count(array_filter($bills, fn($b) => $b['status'] === 'pending_customer_approval')),
                'active_customers' => count($customers),
            ],
            'customers' => $customers,
            'bills' => $bills,
            'payments' => $payments,
            'ledger' => $ledger,
        ]);
    }

    public static function createShop(array $user): never
    {
        RoleMiddleware::requireRole($user, ['merchant']);
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['shop_name', 'address', 'city', 'state', 'pincode', 'phone']);

        $pdo = Database::connection();
        $merchantId = (int)$pdo->query("SELECT id FROM merchants WHERE user_id = " . (int)$user['id'])->fetchColumn();
        if ($merchantId <= 0) {
            Response::error('Merchant profile not found.', 404);
        }

        $shopCode = Security::generateReference('SHP');
        $qrToken = 'QR-SHOP-' . $shopCode;
        $qrUrl = EnvLoader::get('APP_URL', 'http://localhost:8080/UdharPay/backend') . '/api/qr/shop/' . $qrToken;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO shops (merchant_id, shop_code, shop_name, category, address, city, state, pincode, phone, default_credit_limit, is_verified, status)
                 VALUES (:mid, :code, :name, :cat, :addr, :city, :state, :pin, :phone, :lim, 0, "active")'
            );
            $stmt->execute([
                'mid' => $merchantId,
                'code' => $shopCode,
                'name' => Validator::cleanString((string)$input['shop_name'], 160),
                'cat' => Validator::cleanString((string)($input['category'] ?? 'Kirana & General'), 80),
                'addr' => Validator::cleanString((string)$input['address'], 300),
                'city' => Validator::cleanString((string)$input['city'], 80),
                'state' => Validator::cleanString((string)$input['state'], 80),
                'pin' => Validator::cleanString((string)$input['pincode'], 10),
                'phone' => Validator::indianPhone((string)$input['phone']),
                'lim' => Validator::positiveAmount($input['default_credit_limit'] ?? 5000, 'default_credit_limit'),
            ]);
            $shopId = (int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO shop_qr_codes (shop_id, qr_token, qr_payload_url, is_active) VALUES (:sid, :tok, :url, 1)')
                ->execute(['sid' => $shopId, 'tok' => $qrToken, 'url' => $qrUrl]);

            AuditService::log((int)$user['id'], 'merchant', 'shop.created', 'shops', (string)$shopId, ['shop_code' => $shopCode]);
            $pdo->commit();
            Response::success(['shop_id' => $shopId, 'shop_code' => $shopCode, 'qr_token' => $qrToken], 'Shop created with permanent QR code.', 201);
        } catch (Throwable) {
            $pdo->rollBack();
            Response::error('Failed to create shop.', 500);
        }
    }

    public static function updateCustomerLimit(array $user): never
    {
        RoleMiddleware::requireRole($user, ['merchant']);
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['shop_id', 'customer_id', 'credit_limit']);

        $shopId = (int)$input['shop_id'];
        $customerId = (int)$input['customer_id'];
        if (!ShopModel::verifyMerchantOwnsShop((int)$user['id'], $shopId)) {
            Response::error('Unauthorized shop access.', 403);
        }

        $newLimit = Validator::positiveAmount($input['credit_limit'], 'credit_limit');
        $blocked = !empty($input['is_credit_blocked']) ? 1 : 0;
        $rationale = Validator::cleanString((string)($input['rationale'] ?? 'Merchant manual credit limit update'), 250);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $prevStmt = $pdo->prepare('SELECT credit_limit FROM credit_limits WHERE shop_id = :s AND customer_id = :c LIMIT 1');
            $prevStmt->execute(['s' => $shopId, 'c' => $customerId]);
            $prevLimit = (float)($prevStmt->fetchColumn() ?: 5000.00);

            $upsert = $pdo->prepare(
                'INSERT INTO credit_limits (shop_id, customer_id, credit_limit, is_credit_blocked, block_reason, updated_by)
                 VALUES (:s, :c, :lim, :blk, :reason, :uid)
                 ON DUPLICATE KEY UPDATE credit_limit = VALUES(credit_limit), is_credit_blocked = VALUES(is_credit_blocked), block_reason = VALUES(block_reason), updated_by = VALUES(updated_by)'
            );
            $upsert->execute([
                's' => $shopId,
                'c' => $customerId,
                'lim' => $newLimit,
                'blk' => $blocked,
                'reason' => $blocked ? $rationale : null,
                'uid' => $user['id'],
            ]);

            $decision = $blocked ? 'blocked' : ($newLimit < $prevLimit ? 'reduced' : 'approved');
            $pdo->prepare(
                'INSERT INTO credit_decisions (shop_id, customer_id, decided_by_user_id, previous_limit, new_limit, decision, rationale)
                 VALUES (:s, :c, :uid, :prev, :new, :dec, :rat)'
            )->execute([
                's' => $shopId,
                'c' => $customerId,
                'uid' => $user['id'],
                'prev' => $prevLimit,
                'new' => $newLimit,
                'dec' => $decision,
                'rat' => $rationale,
            ]);

            AuditService::log((int)$user['id'], 'merchant', 'credit_limit.updated', 'credit_limits', "{$shopId}:{$customerId}", ['new_limit' => $newLimit, 'blocked' => $blocked]);
            $pdo->commit();
            Response::success(['credit_limit' => $newLimit, 'is_credit_blocked' => (bool)$blocked], 'Customer credit limit updated.');
        } catch (Throwable) {
            $pdo->rollBack();
            Response::error('Could not update credit limit.', 500);
        }
    }
}
