<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/TenantContext.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/ConfigSettingsService.php';
require_once __DIR__ . '/../src/Services/EmailTransport.php';
require_once __DIR__ . '/../src/Services/EmailNotificationService.php';

use AttendanceSystem\TenantContext;
use AttendanceSystem\Services\EmailNotificationService;

$options = getopt('', ['org:', 'limit::']);
$orgId = $options['org'] ?? getenv('ORG_ID');

if ($orgId === null || $orgId === '') {
    fwrite(STDERR, "Organization id is required. Use --org=ID or ORG_ID env.\n");
    exit(1);
}

TenantContext::bind((int) $orgId);

$limit = $options['limit'] ?? 25;

$service = new EmailNotificationService();
$processed = $service->sendQueuedEmails((int) $limit);

fwrite(STDOUT, "Processed {$processed} queued emails for organization {$orgId}.\n");
