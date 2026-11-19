<?php
declare(strict_types=1);

final class LedgerModel
{
    public static function listEntries(?int $shopId = null, ?int $customerId = null): array
    {
        $pdo = Database::connection();
        $clauses = ['1=1'];
        $params = [];
        if ($shopId !== null) {
            $clauses[] = 'l.shop_id = :shop_id';
            $params['shop_id'] = $shopId;
        }
        if ($customerId !== null) {
            $clauses[] = 'l.customer_id = :customer_id';
            $params['customer_id'] = $customerId;
        }

        $sql = 'SELECT l.*, s.shop_name, u.full_name AS customer_name, b.bill_number
                FROM ledger_entries l
                INNER JOIN shops s ON s.id = l.shop_id
                INNER JOIN customers c ON c.id = l.customer_id
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN credit_bills b ON b.id = l.bill_id
                WHERE ' . implode(' AND ', $clauses) . '
                ORDER BY l.id DESC
                LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
