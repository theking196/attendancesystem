<?php

declare(strict_types=1);

require_once __DIR__ . '/src/TenantContext.php';
require_once __DIR__ . '/src/Auth.php';

use AttendanceSystem\TenantContext;
use AttendanceSystem\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$organizationId = Auth::resolveOrganizationId();
if ($organizationId === null) {
    throw new RuntimeException('Authenticated user organization_id is required.');
}

TenantContext::bind($organizationId);
