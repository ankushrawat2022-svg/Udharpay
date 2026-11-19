<?php
declare(strict_types=1);

final class WebhookController
{
    public static function receive(string $partnerCode, array $config): never
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $signature = (string)($_SERVER['HTTP_X_UDHARPAY_SIGNATURE'] ?? $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

        $result = WebhookService::process($partnerCode, $rawBody, $signature, $config);
        if (!$result['accepted']) {
            Response::error($result['message'], (int)$result['status_code']);
        }
        Response::success($result, $result['message']);
    }
}
