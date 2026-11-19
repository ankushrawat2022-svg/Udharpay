<?php
declare(strict_types=1);

final class Validator
{
    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Response::error('Invalid JSON request payload.', 400);
        }
        return $decoded;
    }

    public static function requireFields(array $input, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                $missing[$field] = 'This field is required.';
            }
        }
        if ($missing !== []) {
            Response::error('Validation failed.', 422, $missing);
        }
    }

    public static function indianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (!preg_match('/^[6-9]\d{9}$/', $digits)) {
            Response::error('Enter a valid 10-digit Indian mobile number.', 422, ['phone' => 'Invalid Indian mobile number.']);
        }
        return $digits;
    }

    public static function positiveAmount(mixed $value, string $field = 'amount'): float
    {
        if (!is_numeric($value)) {
            Response::error("Invalid {$field}.", 422, [$field => 'Must be a valid number.']);
        }
        $num = round((float)$value, 2);
        if ($num <= 0 || $num > 5000000) {
            Response::error("Invalid {$field} range.", 422, [$field => 'Must be between ₹0.01 and ₹50,00,000.']);
        }
        return $num;
    }

    public static function cleanString(string $value, int $maxLength = 255): string
    {
        $trimmed = trim(strip_tags($value));
        return mb_substr($trimmed, 0, $maxLength);
    }
}
