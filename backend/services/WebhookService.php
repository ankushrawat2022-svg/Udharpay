<?php
declare(strict_types=1);

final class WebhookService
{
    public static function process(string $partnerCode, string $rawPayload, string $signatureHeader, array $config): array
    {
        $secret = (string)($config['payment_partner']['webhook_secret'] ?? 'NOT_CONFIGURED');
        if ($secret === 'NOT_CONFIGURED' || $secret === '') {
            return [
                'accepted' => false,
                'status_code' => 503,
                'message' => 'Payment webhook secret is NOT CONFIGURED. Live webhook processing is disabled.',
            ];
        }

        $verified = Security::verifyHmacSignature($rawPayload, $signatureHeader, $secret);
        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            return ['accepted' => false, 'status_code' => 400, 'message' => 'Malformed webhook JSON payload.'];
        }

        $eventId = (string)($decoded['event_id'] ?? Security::randomToken(12));
        $eventType = (string)($decoded['event_type'] ?? 'unknown');

        $pdo = Database::connection();

        // Duplicate webhook idempotency guard
        $exists = $pdo->prepare('SELECT id FROM webhook_events WHERE partner_code = :partner AND event_id = :event_id LIMIT 1');
        $exists->execute(['partner' => $partnerCode, 'event_id' => $eventId]);
        if ($exists->fetch()) {
            return ['accepted' => true, 'status_code' => 200, 'message' => 'Duplicate webhook event ignored safely.'];
        }

        $status = $verified ? 'processed' : 'rejected_signature';
        $ins = $pdo->prepare(
            'INSERT INTO webhook_events (partner_code, event_id, event_type, signature_verified, payload_json, processing_status, processed_at)
             VALUES (:partner, :event_id, :event_type, :verified, :payload, :status, NOW())'
        );
        $ins->execute([
            'partner' => $partnerCode,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'verified' => $verified ? 1 : 0,
            'payload' => $rawPayload,
            'status' => $status,
        ]);

        if (!$verified) {
            return ['accepted' => false, 'status_code' => 401, 'message' => 'Webhook signature verification failed.'];
        }

        return ['accepted' => true, 'status_code' => 200, 'message' => 'Webhook verified and logged for reconciliation.'];
    }
}
