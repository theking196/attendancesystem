<?php

declare(strict_types=1);

namespace AttendanceSystem;

final class TenantContext
{
    private static ?int $organizationId = null;
    private static bool $isBound = false;

    public static function bind(int $organizationId): void
    {
        if (self::$isBound) {
            if (self::$organizationId !== $organizationId) {
                throw new RuntimeException('Tenant context is already bound to a different organization.');
            }

            return;
        }

        self::$organizationId = $organizationId;
        self::$isBound = true;
    }

    public static function has(): bool
    {
        return self::$isBound;
    }

    public static function requireBound(): void
    {
        if (!self::$isBound || self::$organizationId === null) {
            throw new RuntimeException('Tenant context is required before executing tenant-scoped operations.');
        }
    }

    public static function organizationId(): int
    {
        self::requireBound();

        return self::$organizationId;
    }
}
