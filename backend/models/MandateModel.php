<?php
declare(strict_types=1);

final class MandateModel
{
    public static function listByCustomerUser(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT m.*, s.shop_name, ca.outstanding_balance
             FROM payment_mandates m
             INNER JOIN customers c ON c.id = m.customer_id
             INNER JOIN shops s ON s.id = m.shop_id
             LEFT JOIN credit_accounts ca ON ca.shop_id = m.shop_id AND ca.customer_id = m.customer_id
             WHERE c.user_id = :user_id
             ORDER BY m.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
