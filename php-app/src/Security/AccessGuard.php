<?php

declare(strict_types=1);

namespace AttendanceSystem\Security;

use AttendanceSystem\Auth;
use AttendanceSystem\TenantContext;
use RuntimeException;

final class AccessGuard
{
    private static ?Role $currentRole = null;

    public static function enforce(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session must be active before enforcing access guard.');
        }

        $organizationId = Auth::resolveOrganizationId();
        if ($organizationId === null) {
            throw new RuntimeException('Authenticated user organization_id is required.');
        }

        if (!TenantContext::has()) {
            TenantContext::bind($organizationId);
        }

        $roleName = Auth::resolveRole();
        self::$currentRole = Role::fromName($roleName);
    }

    public static function currentRole(): Role
    {
        if (self::$currentRole === null) {
            throw new RuntimeException('Access guard has not been enforced.');
        }

        return self::$currentRole;
    }
}
