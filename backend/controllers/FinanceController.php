<?php
declare(strict_types=1);

final class FinanceController
{
    public static function overview(array $user): never
    {
        RoleMiddleware::requireRole($user, ['finance', 'super_admin']);
        $pdo = Database::connection();

        $subscriptions = $pdo->query(
            'SELECT sub.*, m.business_legal_name FROM subscriptions sub INNER JOIN merchants m ON m.id = sub.merchant_id ORDER BY sub.created_at DESC'
        )->fetchAll();

        $invoices = $pdo->query(
            'SELECT inv.*, m.business_legal_name, m.gstin FROM invoices inv INNER JOIN merchants m ON m.id = inv.merchant_id ORDER BY inv.created_at DESC'
        )->fetchAll();

        $refunds = $pdo->query(
            'SELECT p.*, s.shop_name FROM payments p INNER JOIN shops s ON s.id = p.shop_id WHERE p.status = "refunded" ORDER BY p.updated_at DESC'
        )->fetchAll();

        $revenue = (float)$pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = "paid"')->fetchColumn();
        $gstCollected = (float)$pdo->query('SELECT COALESCE(SUM(gst_amount), 0) FROM invoices WHERE status = "paid"')->fetchColumn();
        $settledVolume = (float)$pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "succeeded"')->fetchColumn();

        Response::success([
            'summary' => [
                'platform_revenue' => round($revenue, 2),
                'gst_tax_collected' => round($gstCollected, 2),
                'merchant_settlement_volume' => round($settledVolume, 2),
                'partner_charges_estimated' => round($settledVolume * 0.0045, 2),
            ],
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'refunds' => $refunds,
        ]);
    }
}
