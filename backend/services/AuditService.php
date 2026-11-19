<?php
declare(strict_types=1);

final class AuditService
{
    public static function log(?int $actorUserId, ?string $actorRole, string $action, string $entityType, ?string $entityId = null, array $metadata = []): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, actor_role, action, entity_type, entity_id, ip_address, metadata_json)
                 VALUES (:actor_user_id, :actor_role, :action, :entity_type, :entity_id, :ip_address, :metadata_json)'
            );
            $stmt->execute([
                'actor_user_id' => $actorUserId,
                'actor_role'    => $actorRole,
                'action'        => $action,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'ip_address'    => Security::clientIp(),
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {
            // Never fail primary request if audit write fails; write fallback file log
            $logLine = sprintf("[%s] AUDIT_FALLBACK %s %s:%s\n", date('c'), $action, $entityType, (string)$entityId);
            @file_put_contents(dirname(__DIR__) . '/logs/audit.log', $logLine, FILE_APPEND | LOCK_EX);
        }
    }

    public static function notify(int $userId, string $title, string $message, string $category = 'bill'): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, category)
             VALUES (:user_id, :title, :message, :category)'
        );
        $stmt->execute([
            'user_id'  => $userId,
            'title'    => $title,
            'message'  => $message,
            'category' => $category,
        ]);
    }
}
