<?php
declare(strict_types=1);

/**
 * UPI AutoPay Mandate Controller (RBI / NPCI Compliant Architecture)
 * - Customer can revoke/cancel mandate at any time
 * - Cancelling a mandate preserves any outstanding Udhar debt
 * - Automatically notifies the merchant and offers alternate repayment options
 */
final class MandateController
{
    public static function cancel(int $mandateId, array $user, array $config): never
    {
        RoleMiddleware::requireRole($user, ['customer']);
        $input = Validator::readJsonBody();
        $reason = Validator::cleanString((string)($input['reason'] ?? 'Customer requested AutoPay mandate cancellation'), 250);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT m.*, c.user_id AS customer_user_id, s.shop_name, merch.user_id AS merchant_user_id,
                        COALESCE(ca.outstanding_balance, 0) AS outstanding_balance
                 FROM payment_mandates m
                 INNER JOIN customers c ON c.id = m.customer_id
                 INNER JOIN shops s ON s.id = m.shop_id
                 INNER JOIN merchants merch ON merch.id = s.merchant_id
                 LEFT JOIN credit_accounts ca ON ca.shop_id = m.shop_id AND ca.customer_id = m.customer_id
                 WHERE m.id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $mandateId]);
            $mandate = $stmt->fetch();

            if (!$mandate || (int)$mandate['customer_user_id'] !== (int)$user['id']) {
                $pdo->rollBack();
                Response::error('Mandate not found.', 404);
            }
            if (in_array($mandate['status'], ['cancelled', 'revoked'], true)) {
                $pdo->rollBack();
                Response::error('This mandate is already cancelled.', 409);
            }

            $partner = new DemoPaymentService($config);
            $partner->requestMandateCancellation((string)$mandate['mandate_ref'], $reason);

            $pdo->prepare('UPDATE payment_mandates SET status = "cancelled", cancellation_reason = :r, cancelled_at = NOW() WHERE id = :id')
                ->execute(['r' => $reason, 'id' => $mandateId]);

            $pdo->prepare('INSERT INTO mandate_events (mandate_id, event_type, actor_user_id, notes) VALUES (:mid, "cancelled", :uid, :notes)')
                ->execute(['mid' => $mandateId, 'uid' => $user['id'], 'notes' => $reason]);

            $pdo->prepare('INSERT INTO consents (customer_id, shop_id, consent_type, decision, ip_address) VALUES (:cid, :sid, "mandate_revocation", "revoked", :ip)')
                ->execute(['cid' => $mandate['customer_id'], 'sid' => $mandate['shop_id'], 'ip' => Security::clientIp()]);

            $remaining = (float)$mandate['outstanding_balance'];
            AuditService::notify(
                (int)$mandate['merchant_user_id'],
                'Customer Cancelled AutoPay Mandate',
                "Mandate {$mandate['mandate_ref']} was cancelled by customer. Outstanding Udhar ₹{$remaining} remains preserved and payable via standard UPI or cash.",
                'mandate'
            );
            AuditService::log((int)$user['id'], 'customer', 'mandate.cancelled', 'payment_mandates', (string)$mandateId, ['preserved_outstanding' => $remaining]);

            $pdo->commit();
            Response::success([
                'mandate_id' => $mandateId,
                'status' => 'cancelled',
                'preserved_outstanding_balance' => $remaining,
                'merchant_notified' => true,
                'alternative_repayment_methods' => ['Manual UPI Intent (DEMO)', 'Shop Counter Cash Receipt (DEMO)', 'Installment Schedule'],
            ], 'Mandate cancelled. Outstanding Udhar balance remains preserved and merchant has been notified.');
        } catch (Throwable) {
            $pdo->rollBack();
            Response::error('Unable to process mandate cancellation.', 500);
        }
    }
}
