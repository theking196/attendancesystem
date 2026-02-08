<?php

declare(strict_types=1);

namespace AttendanceSystem;

final class Auth
{
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
}
