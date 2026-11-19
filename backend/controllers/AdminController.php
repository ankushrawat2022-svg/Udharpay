<?php
declare(strict_types=1);

final class AdminController
{
    public static function overview(array $user): never
    {
        RoleMiddleware::requireRole($user, ['super_admin']);
        $pdo = Database::connection();

        $metrics = [
            'total_merchants' => (int)$pdo->query('SELECT COUNT(*) FROM merchants')->fetchColumn(),
            'total_customers' => (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'total_shops' => (int)$pdo->query('SELECT COUNT(*) FROM shops')->fetchColumn(),
            'total_credit_bills' => (int)$pdo->query('SELECT COUNT(*) FROM credit_bills')->fetchColumn(),
            'total_outstanding_credit' => (float)$pdo->query('SELECT COALESCE(SUM(outstanding_balance), 0) FROM credit_accounts')->fetchColumn(),
            'total_repayments' => (float)$pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "succeeded"')->fetchColumn(),
            'total_transactions' => (int)$pdo->query('SELECT COUNT(*) FROM ledger_entries')->fetchColumn(),
            'active_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
            'inactive_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE status != "active"')->fetchColumn(),
        ];

        $merchants = $pdo->query(
            'SELECT m.*, u.full_name, u.phone, u.email, (SELECT COUNT(*) FROM shops s WHERE s.merchant_id = m.id) AS shop_count
             FROM merchants m INNER JOIN users u ON u.id = m.user_id ORDER BY m.created_at DESC'
        )->fetchAll();

        $kycQueue = $pdo->query(
            'SELECT c.id AS customer_id, c.customer_code, c.kyc_status, u.full_name, u.phone
             FROM customers c INNER JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC'
        )->fetchAll();

        $partners = $pdo->query('SELECT * FROM partner_accounts ORDER BY id ASC')->fetchAll();
        $auditLogs = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100')->fetchAll();

        Response::success([
            'metrics' => $metrics,
            'merchants' => $merchants,
            'kyc_queue' => $kycQueue,
            'partners' => $partners,
            'audit_logs' => $auditLogs,
            'database_status' => Database::healthCheck(),
        ]);
    }

    public static function verifyMerchant(int $merchantId, array $user): never
    {
        RoleMiddleware::requireRole($user, ['super_admin', 'manager']);
        $input = Validator::readJsonBody();
        $status = in_array($input['status'] ?? '', ['verified', 'rejected', 'suspended'], true) ? $input['status'] : 'verified';
        $reason = Validator::cleanString((string)($input['reason'] ?? 'Verification status updated'), 250);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE merchants SET verification_status = :st, verified_by = :uid, verified_at = NOW() WHERE id = :id')
                ->execute(['st' => $status, 'uid' => $user['id'], 'id' => $merchantId]);

            $pdo->prepare('UPDATE shops SET is_verified = :v WHERE merchant_id = :mid')
                ->execute(['v' => $status === 'verified' ? 1 : 0, 'mid' => $merchantId]);

            $pdo->prepare('INSERT INTO admin_actions (admin_user_id, action_type, target_table, target_id, reason) VALUES (:uid, "merchant.verify", "merchants", :tid, :r)')
                ->execute(['uid' => $user['id'], 'tid' => $merchantId, 'r' => $reason]);

            AuditService::log((int)$user['id'], (string)$user['role'], 'admin.merchant_verify', 'merchants', (string)$merchantId, ['status' => $status]);
            $pdo->commit();
            Response::success(['merchant_id' => $merchantId, 'verification_status' => $status], 'Merchant verification updated.');
        } catch (Throwable) {
            $pdo->rollBack();
            Response::error('Failed to update merchant verification.', 500);
        }
    }
}
