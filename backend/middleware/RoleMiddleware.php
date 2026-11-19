<?php
declare(strict_types=1);

final class RoleMiddleware
{
    /**
     * @param array<string> $allowedRoles
     */
    public static function requireRole(array $user, array $allowedRoles): void
    {
        $role = (string)($user['role'] ?? '');
        if (!in_array($role, $allowedRoles, true)) {
            Response::error('Access denied for your account role.', 403);
        }
    }
}
