<?php

declare(strict_types=1);

namespace AttendanceSystem;

final class Auth
{
    private const DEFAULT_ROLE = 'viewer';

    public static function resolveOrganizationId(): ?int
    {
        $orgIdHeader = $_SERVER['HTTP_X_ORGANIZATION_ID'] ?? null;
        if ($orgIdHeader !== null && $orgIdHeader !== '') {
            return (int) $orgIdHeader;
        }

        $sessionOrgId = $_SESSION['organization_id'] ?? null;
        if ($sessionOrgId !== null && $sessionOrgId !== '') {
            return (int) $sessionOrgId;
        }

        return null;
    }

    public static function resolveRole(): string
    {
        $roleHeader = $_SERVER['HTTP_X_USER_ROLE'] ?? null;
        if ($roleHeader !== null && $roleHeader !== '') {
            return strtolower($roleHeader);
        }

        $sessionRole = $_SESSION['user_role'] ?? null;
        if ($sessionRole !== null && $sessionRole !== '') {
            return strtolower((string) $sessionRole);
        }

        return self::DEFAULT_ROLE;
    }
}
