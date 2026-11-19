<?php
declare(strict_types=1);

final class ManagerController
{
    public static function assignedShops(array $user): never
    {
        RoleMiddleware::requireRole($user, ['manager', 'super_admin']);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT ss.staff_role, ss.can_create_bill, ss.can_record_payment, ss.can_change_limit,
                    s.id AS shop_id, s.shop_code, s.shop_name, s.city, s.is_verified, m.business_legal_name, m.verification_status
             FROM shop_staff ss
             INNER JOIN shops s ON s.id = ss.shop_id
             INNER JOIN merchants m ON m.id = s.merchant_id
             WHERE ss.user_id = :uid AND ss.status = "active"'
        );
        $stmt->execute(['uid' => $user['id']]);
        $assigned = $stmt->fetchAll();

        $logsStmt = $pdo->prepare('SELECT * FROM audit_logs WHERE actor_user_id = :uid ORDER BY id DESC LIMIT 40');
        $logsStmt->execute(['uid' => $user['id']]);

        Response::success([
            'assigned_shops' => $assigned,
            'activity_history' => $logsStmt->fetchAll(),
        ]);
    }
}
