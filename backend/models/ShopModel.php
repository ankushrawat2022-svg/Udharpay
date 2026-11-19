<?php
declare(strict_types=1);

final class ShopModel
{
    public static function listByMerchantUser(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT s.*, q.qr_token, q.qr_payload_url, q.scan_count, m.business_legal_name, m.verification_status
             FROM shops s
             INNER JOIN merchants m ON m.id = s.merchant_id
             LEFT JOIN shop_qr_codes q ON q.shop_id = s.id
             WHERE m.user_id = :user_id
             ORDER BY s.id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findByQrToken(string $qrToken): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT s.id, s.shop_code, s.shop_name, s.category, s.address, s.city, s.state, s.pincode,
                    s.phone, s.logo_path, s.is_verified, s.status, q.qr_token, q.qr_payload_url
             FROM shop_qr_codes q
             INNER JOIN shops s ON s.id = q.shop_id
             WHERE q.qr_token = :token AND q.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['token' => $qrToken]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE shop_qr_codes SET scan_count = scan_count + 1 WHERE qr_token = :token')
                ->execute(['token' => $qrToken]);
        }
        return $row ?: null;
    }

    public static function verifyMerchantOwnsShop(int $userId, int $shopId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT s.id
             FROM shops s
             INNER JOIN merchants m ON m.id = s.merchant_id
             WHERE s.id = :shop_id AND m.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['shop_id' => $shopId, 'user_id' => $userId]);
        return (bool)$stmt->fetch();
    }
}
