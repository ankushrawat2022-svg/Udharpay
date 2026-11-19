<?php
declare(strict_types=1);

final class CreditBillModel
{
    public static function listByShop(int $shopId, ?string $status = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT b.*, u.full_name AS customer_name, u.phone AS customer_phone, c.customer_code
                FROM credit_bills b
                INNER JOIN customers c ON c.id = b.customer_id
                INNER JOIN users u ON u.id = c.user_id
                WHERE b.shop_id = :shop_id';
        $params = ['shop_id' => $shopId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND b.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY b.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bills = $stmt->fetchAll();

        foreach ($bills as &$bill) {
            $bill['items'] = self::itemsForBill((int)$bill['id']);
        }
        return $bills;
    }

    public static function listByCustomerUser(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT b.*, s.shop_name, s.shop_code, s.is_verified AS shop_verified, s.city AS shop_city
             FROM credit_bills b
             INNER JOIN customers c ON c.id = b.customer_id
             INNER JOIN shops s ON s.id = b.shop_id
             WHERE c.user_id = :user_id
             ORDER BY b.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $bills = $stmt->fetchAll();
        foreach ($bills as &$bill) {
            $bill['items'] = self::itemsForBill((int)$bill['id']);
        }
        return $bills;
    }

    public static function itemsForBill(int $billId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM credit_bill_items WHERE bill_id = :bill_id ORDER BY id ASC');
        $stmt->execute(['bill_id' => $billId]);
        return $stmt->fetchAll();
    }
}
