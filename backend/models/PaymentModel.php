<?php
declare(strict_types=1);

final class PaymentModel
{
    public static function listByCustomerUser(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, s.shop_name, b.bill_number
             FROM payments p
             INNER JOIN customers c ON c.id = p.customer_id
             INNER JOIN shops s ON s.id = p.shop_id
             LEFT JOIN credit_bills b ON b.id = p.bill_id
             WHERE c.user_id = :user_id
             ORDER BY p.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function listByShop(int $shopId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, u.full_name AS customer_name, b.bill_number
             FROM payments p
             INNER JOIN customers c ON c.id = p.customer_id
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN credit_bills b ON b.id = p.bill_id
             WHERE p.shop_id = :shop_id
             ORDER BY p.created_at DESC'
        );
        $stmt->execute(['shop_id' => $shopId]);
        return $stmt->fetchAll();
    }
}
