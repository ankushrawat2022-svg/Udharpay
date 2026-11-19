<?php
declare(strict_types=1);

/**
 * Payment & UPI AutoPay Partner Contract.
 * Real partner implementations remain disabled (NOT CONFIGURED) until authorized
 * RBI / NPCI partner onboarding and valid credentials are configured in backend/.env.
 */
interface PaymentPartnerInterface
{
    public function isConfigured(): bool;
    public function initiatePayment(string $paymentRef, float $amount, string $mode): array;
    public function verifyWebhook(string $rawBody, string $signatureHeader): bool;
    public function createMandateRequest(string $mandateRef, float $maxAmount, string $frequency): array;
    public function requestMandateCancellation(string $mandateRef, string $reason): array;
}
