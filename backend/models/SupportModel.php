<?php
declare(strict_types=1);

final class SupportModel
{
    public static function listTickets(?string $status = null): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT t.*, u.full_name AS requester_name, u.phone AS requester_phone, r.slug AS requester_role,
                       a.full_name AS assigned_agent_name
                FROM support_tickets t
                INNER JOIN users u ON u.id = t.requester_user_id
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN users a ON a.id = t.assigned_agent_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE t.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY t.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
