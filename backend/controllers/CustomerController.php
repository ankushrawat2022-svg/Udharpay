<?php
declare(strict_types=1);

final class CustomerController
{
    public static function dashboard(array $user): never
    {
        RoleMiddleware::requireRole($user, ['customer']);
        $pdo = Database::connection();

        $custStmt = $pdo->prepare('SELECT * FROM customers WHERE user_id = :uid LIMIT 1');
        $custStmt->execute(['uid' => $user['id']]);
        $customer = $custStmt->fetch();
        if (!$customer) {
            Response::error('Customer profile not found.', 404);
        }

        $shopsStmt = $pdo->prepare(
            'SELECT ca.shop_id, s.shop_code, s.shop_name, s.city, s.is_verified,
                    ca.total_credited, ca.total_repaid, ca.outstanding_balance,
                    COALESCE(cl.credit_limit, s.default_credit_limit) AS credit_limit,
                    COALESCE(cl.is_credit_blocked, 0) AS is_credit_blocked
             FROM credit_accounts ca
             INNER JOIN shops s ON s.id = ca.shop_id
             LEFT JOIN credit_limits cl ON cl.shop_id = ca.shop_id AND cl.customer_id = ca.customer_id
             WHERE ca.customer_id = :cid
             ORDER BY ca.outstanding_balance DESC'
        );
        $shopsStmt->execute(['cid' => $customer['id']]);
        $connectedShops = $shopsStmt->fetchAll();

        $bills = CreditBillModel::listByCustomerUser((int)$user['id']);
        $payments = PaymentModel::listByCustomerUser((int)$user['id']);
        $mandates = MandateModel::listByCustomerUser((int)$user['id']);
        $ledger = LedgerModel::listEntries(null, (int)$customer['id']);

        $notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20');
        $notifStmt->execute(['uid' => $user['id']]);
        $notifications = $notifStmt->fetchAll();

        $totalOutstanding = array_reduce($connectedShops, fn($carry, $s) => $carry + (float)$s['outstanding_balance'], 0.0);

        Response::success([
            'customer' => $customer,
            'summary' => [
                'total_outstanding' => round($totalOutstanding, 2),
                'connected_shops_count' => count($connectedShops),
                'pending_approvals' => count(array_filter($bills, fn($b) => $b['status'] === 'pending_customer_approval')),
                'active_mandates' => count(array_filter($mandates, fn($m) => $m['status'] === 'active')),
            ],
            'connected_shops' => $connectedShops,
            'bills' => $bills,
            'payments' => $payments,
            'mandates' => $mandates,
            'ledger' => $ledger,
            'notifications' => $notifications,
        ]);
    }
}
