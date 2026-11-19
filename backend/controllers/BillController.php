<?php
declare(strict_types=1);

/**
 * Credit Bill Workflow Controller
 * - Merchant creates bill in 'pending_customer_approval' state
 * - Enforces shop-level credit limit & block check
 * - Customer MUST explicitly accept or reject before ledger debit is posted
 */
final class BillController
{
    public static function create(array $user): never
    {
        RoleMiddleware::requireRole($user, ['merchant', 'manager']);
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['shop_id', 'customer_id', 'due_date', 'items']);

        $shopId = (int)$input['shop_id'];
        $customerId = (int)$input['customer_id'];
        $dueDate = Validator::cleanString((string)$input['due_date'], 10);
        $installments = max(1, min(12, (int)($input['installment_count'] ?? 1)));
        $notes = Validator::cleanString((string)($input['notes'] ?? ''), 480);
        $idempotencyKey = (string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($input['idempotency_key'] ?? Security::randomToken(16)));

        if ($user['role'] === 'merchant' && !ShopModel::verifyMerchantOwnsShop((int)$user['id'], $shopId)) {
            Response::error('Unauthorized shop access.', 403);
        }

        $items = $input['items'];
        if (!is_array($items) || count($items) === 0) {
            Response::error('At least one product item is required.', 422);
        }

        $subtotal = 0.00;
        $cleanItems = [];
        foreach ($items as $item) {
            $name = Validator::cleanString((string)($item['item_name'] ?? ''), 150);
            $qty = max(0.01, round((float)($item['quantity'] ?? 1), 2));
            $price = Validator::positiveAmount($item['unit_price'] ?? 0, 'unit_price');
            if ($name === '') {
                Response::error('Every line item must have a product name.', 422);
            }
            $lineTotal = round($qty * $price, 2);
            $subtotal = round($subtotal + $lineTotal, 2);
            $cleanItems[] = [
                'item_name' => $name,
                'quantity' => $qty,
                'unit' => Validator::cleanString((string)($item['unit'] ?? 'pcs'), 20),
                'unit_price' => $price,
                'line_total' => $lineTotal,
            ];
        }

        $pdo = Database::connection();

        // Duplicate submission protection
        $dup = $pdo->prepare('SELECT id, bill_number, status FROM credit_bills WHERE idempotency_key = :key LIMIT 1');
        $dup->execute(['key' => $idempotencyKey]);
        if ($existing = $dup->fetch()) {
            Response::success(['bill' => $existing, 'duplicate_prevented' => true], 'Duplicate bill request safely returned existing bill.');
        }

        // Check shop-level credit limit and block flag
        $limitStmt = $pdo->prepare('SELECT * FROM credit_limits WHERE shop_id = :shop_id AND customer_id = :customer_id LIMIT 1');
        $limitStmt->execute(['shop_id' => $shopId, 'customer_id' => $customerId]);
        $limitRow = $limitStmt->fetch();
        if ($limitRow && (int)$limitRow['is_credit_blocked'] === 1) {
            Response::error('New credit is blocked for this customer at this shop.', 403);
        }

        $accStmt = $pdo->prepare('SELECT outstanding_balance FROM credit_accounts WHERE shop_id = :shop_id AND customer_id = :customer_id LIMIT 1');
        $accStmt->execute(['shop_id' => $shopId, 'customer_id' => $customerId]);
        $outstanding = (float)($accStmt->fetchColumn() ?: 0);

        $shopLimit = $limitRow ? (float)$limitRow['credit_limit'] : 5000.00;
        $forceOverride = !empty($input['merchant_limit_override']);
        if (($outstanding + $subtotal) > $shopLimit && !$forceOverride) {
            Response::error(
                sprintf('Credit limit exceeded: Outstanding ₹%.2f + Bill ₹%.2f exceeds shop limit ₹%.2f. Confirm manual merchant override to proceed.', $outstanding, $subtotal, $shopLimit),
                422,
                ['credit_limit_exceeded' => true, 'current_outstanding' => $outstanding, 'shop_limit' => $shopLimit]
            );
        }

        $billNumber = Security::generateReference('UP');
        $billQrToken = 'BILLQR-' . Security::randomToken(12);

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO credit_bills
                  (bill_number, bill_qr_token, shop_id, customer_id, created_by_user_id, subtotal_amount, tax_amount, total_amount, paid_amount, pending_amount, installment_count, due_date, notes, status, idempotency_key)
                 VALUES
                  (:bill_number, :qr, :shop_id, :customer_id, :creator, :subtotal, 0.00, :total, 0.00, :pending, :inst, :due_date, :notes, "pending_customer_approval", :idem)'
            );
            $ins->execute([
                'bill_number' => $billNumber,
                'qr' => $billQrToken,
                'shop_id' => $shopId,
                'customer_id' => $customerId,
                'creator' => $user['id'],
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'pending' => $subtotal,
                'inst' => $installments,
                'due_date' => $dueDate,
                'notes' => $notes,
                'idem' => $idempotencyKey,
            ]);
            $billId = (int)$pdo->lastInsertId();

            $itemIns = $pdo->prepare(
                'INSERT INTO credit_bill_items (bill_id, item_name, quantity, unit, unit_price, line_total)
                 VALUES (:bill_id, :name, :qty, :unit, :price, :total)'
            );
            foreach ($cleanItems as $ci) {
                $itemIns->execute([
                    'bill_id' => $billId,
                    'name' => $ci['item_name'],
                    'qty' => $ci['quantity'],
                    'unit' => $ci['unit'],
                    'price' => $ci['unit_price'],
                    'total' => $ci['line_total'],
                ]);
            }

            $custUserId = (int)$pdo->query("SELECT user_id FROM customers WHERE id = {$customerId}")->fetchColumn();
            AuditService::notify($custUserId, 'New Credit Bill Awaiting Approval', "Bill {$billNumber} for ₹{$subtotal} requires your explicit acceptance or rejection.", 'bill');
            AuditService::log((int)$user['id'], (string)$user['role'], 'bill.created', 'credit_bills', (string)$billId, ['bill_number' => $billNumber, 'total' => $subtotal]);

            $pdo->commit();
            Response::success([
                'bill_id' => $billId,
                'bill_number' => $billNumber,
                'bill_qr_token' => $billQrToken,
                'total_amount' => $subtotal,
                'status' => 'pending_customer_approval',
            ], 'Credit bill created and sent to customer for explicit approval.', 201);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Failed to create credit bill.', 500);
        }
    }

    public static function decide(int $billId, array $user): never
    {
        RoleMiddleware::requireRole($user, ['customer']);
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['decision']);
        $decision = (string)$input['decision'];
        if (!in_array($decision, ['accept', 'reject'], true)) {
            Response::error('Decision must be accept or reject.', 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT b.*, c.user_id AS customer_user_id, s.shop_name, m.user_id AS merchant_user_id
                 FROM credit_bills b
                 INNER JOIN customers c ON c.id = b.customer_id
                 INNER JOIN shops s ON s.id = b.shop_id
                 INNER JOIN merchants m ON m.id = s.merchant_id
                 WHERE b.id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $billId]);
            $bill = $stmt->fetch();

            if (!$bill || (int)$bill['customer_user_id'] !== (int)$user['id']) {
                $pdo->rollBack();
                Response::error('Credit bill not found.', 404);
            }
            if ($bill['status'] !== 'pending_customer_approval') {
                $pdo->rollBack();
                Response::error('This bill has already been processed.', 409);
            }

            if ($decision === 'reject') {
                $reason = Validator::cleanString((string)($input['reason'] ?? 'Customer rejected bill'), 250);
                $pdo->prepare('UPDATE credit_bills SET status = "rejected", customer_rejected_at = NOW(), rejection_reason = :r WHERE id = :id')
                    ->execute(['r' => $reason, 'id' => $billId]);

                $pdo->prepare(
                    'INSERT INTO consents (customer_id, shop_id, bill_id, consent_type, decision, ip_address)
                     VALUES (:cid, :sid, :bid, "credit_bill_approval", "rejected", :ip)'
                )->execute(['cid' => $bill['customer_id'], 'sid' => $bill['shop_id'], 'bid' => $billId, 'ip' => Security::clientIp()]);

                AuditService::notify((int)$bill['merchant_user_id'], 'Credit Bill Rejected', "Customer rejected bill {$bill['bill_number']}. Reason: {$reason}", 'bill');
                AuditService::log((int)$user['id'], 'customer', 'bill.rejected', 'credit_bills', (string)$billId, ['reason' => $reason]);
                $pdo->commit();
                Response::success(['bill_id' => $billId, 'status' => 'rejected'], 'Bill rejected. No debt was added to your ledger.');
            }

            // Explicit acceptance -> post append-only debit to ledger
            $pdo->prepare('UPDATE credit_bills SET status = "active", customer_approved_at = NOW() WHERE id = :id')
                ->execute(['id' => $billId]);

            $pdo->prepare(
                'INSERT INTO consents (customer_id, shop_id, bill_id, consent_type, decision, ip_address)
                 VALUES (:cid, :sid, :bid, "credit_bill_approval", "accepted", :ip)'
            )->execute(['cid' => $bill['customer_id'], 'sid' => $bill['shop_id'], 'bid' => $billId, 'ip' => Security::clientIp()]);

            $ledger = LedgerService::postCreditDebit(
                $pdo,
                (int)$bill['shop_id'],
                (int)$bill['customer_id'],
                $billId,
                (float)$bill['total_amount'],
                "Customer approved credit bill {$bill['bill_number']} at {$bill['shop_name']}",
                (int)$user['id']
            );

            AuditService::notify((int)$bill['merchant_user_id'], 'Credit Bill Approved', "Customer approved bill {$bill['bill_number']} (₹{$bill['total_amount']}). Ledger Ref: {$ledger['reference_number']}", 'bill');
            AuditService::log((int)$user['id'], 'customer', 'bill.approved', 'credit_bills', (string)$billId, $ledger);

            $pdo->commit();
            Response::success([
                'bill_id' => $billId,
                'bill_number' => $bill['bill_number'],
                'status' => 'active',
                'ledger_reference' => $ledger['reference_number'],
                'running_balance' => $ledger['running_balance'],
            ], 'Credit bill explicitly approved and posted to append-only ledger.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Unable to process bill decision.', 500);
        }
    }
}
