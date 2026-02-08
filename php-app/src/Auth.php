<?php

declare(strict_types=1);

namespace AttendanceSystem;

use RuntimeException;

final class Auth
{
    private const DEFAULT_ROLE = 'viewer';

    public static function resolveOrganizationId(): ?int
    {
        $orgIdHeader = $_SERVER['HTTP_X_ORGANIZATION_ID'] ?? null;
        $sessionOrgId = $_SESSION['organization_id'] ?? null;

        $headerId = self::normalizeId($orgIdHeader);
        $sessionId = self::normalizeId($sessionOrgId);

        if ($sessionId !== null && $headerId !== null && $sessionId !== $headerId) {
            throw new RuntimeException('Session is already bound to a different organization.');
        }

        if ($sessionId === null && $headerId !== null) {
            $_SESSION['organization_id'] = $headerId;
            $sessionId = $headerId;
        }

        return $sessionId ?? $headerId;
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

    private static function normalizeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        return $id;
    }
}
