<?php
declare(strict_types=1);

/**
 * Transparent, historical repayment summary for manual merchant review.
 * Never makes automated lending decisions on fabricated data.
 */
final class RiskService
{
    public static function evaluateForMerchantReview(PDO $pdo, int $shopId, int $customerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) AS paid_bills,
                SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) AS overdue_bills,
                COALESCE(SUM(pending_amount), 0) AS current_pending
             FROM credit_bills
             WHERE shop_id = :shop_id AND customer_id = :customer_id AND status != "rejected"'
        );
        $stmt->execute(['shop_id' => $shopId, 'customer_id' => $customerId]);
        $stats = $stmt->fetch() ?: ['paid_bills' => 0, 'overdue_bills' => 0, 'current_pending' => 0];

        $paid = (int)$stats['paid_bills'];
        $overdue = (int)$stats['overdue_bills'];

        if ($overdue > 0) {
            $band = 'elevated';
            $basis = "{$overdue} overdue bill(s) recorded in this shop. Manual merchant review advised.";
        } elseif ($paid >= 3) {
            $band = 'low';
            $basis = "{$paid} fully settled bill(s) with zero overdue history in this shop.";
        } else {
            $band = 'review_required';
            $basis = "Limited repayment history ({$paid} settled bill(s)). Merchant sets credit limit manually.";
        }

        return [
            'paid_bills' => $paid,
            'overdue_bills' => $overdue,
            'current_pending' => (float)$stats['current_pending'],
            'risk_band' => $band,
            'basis_summary' => $basis,
            'note' => 'Advisory summary from verified shop history only. Credit limit approval is strictly manual by the merchant.',
        ];
    }
}
