<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentPartnerInterface.php';

/**
 * Safe DEMO Payment Service.
 * Clearly labeled DEMO. Never collects or stores UPI PIN, bank password, or CVV.
 * Production gateways remain disabled until partner credentials are configured.
 */
final class DemoPaymentService implements PaymentPartnerInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function isConfigured(): bool
    {
        return ($this->config['payment_mode'] ?? 'demo') === 'demo';
    }

    public function initiatePayment(string $paymentRef, float $amount, string $mode): array
    {
        if (($this->config['payment_mode'] ?? 'demo') !== 'demo') {
            return [
                'status' => 'not_configured',
                'is_demo' => false,
                'message' => 'Live payment partner is NOT CONFIGURED. Configure authorized partner credentials and webhook verification before enabling live payments.',
            ];
        }

        return [
            'status' => 'succeeded',
            'is_demo' => true,
            'partner_name' => 'DEMO_GATEWAY',
            'partner_transaction_id' => 'DEMO-TXN-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
            'receipt_number' => Security::generateReference('RCPT-DEMO'),
            'message' => 'DEMO repayment recorded safely. No real money moved.',
        ];
    }

    public function verifyWebhook(string $rawBody, string $signatureHeader): bool
    {
        $secret = (string)($this->config['payment_partner']['webhook_secret'] ?? 'NOT_CONFIGURED');
        return Security::verifyHmacSignature($rawBody, $signatureHeader, $secret);
    }

    public function createMandateRequest(string $mandateRef, float $maxAmount, string $frequency): array
    {
        return [
            'status' => 'pending_customer_approval',
            'is_demo' => true,
            'partner_name' => 'NOT_CONFIGURED_DEMO',
            'notice' => 'AutoPay requires explicit customer authorization on an NPCI-approved UPI app via an authorized payment partner. Live debits are disabled in DEMO mode.',
        ];
    }

    public function requestMandateCancellation(string $mandateRef, string $reason): array
    {
        return [
            'status' => 'cancelled',
            'is_demo' => true,
            'notice' => 'Mandate cancelled in DEMO mode. Any existing outstanding Udhar balance remains preserved and payable via alternate methods.',
        ];
    }
}
