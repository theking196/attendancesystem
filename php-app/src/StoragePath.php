<?php

declare(strict_types=1);

namespace AttendanceSystem;

final class StoragePath
{
    public static function for(string $relativePath): string
    {
        TenantContext::requireBound();

        $trimmed = ltrim($relativePath, '/');
        $orgId = TenantContext::organizationId();

        return sprintf('storage/tenants/%d/%s', $orgId, $trimmed);
    }
}
