<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/TenantContext.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/AnalyticsProcessor.php';

use AttendanceSystem\TenantContext;
use AttendanceSystem\Services\AnalyticsProcessor;

$options = getopt('', ['org:', 'date::', 'month::']);
$orgId = $options['org'] ?? getenv('ORG_ID');

if ($orgId === null || $orgId === '') {
    fwrite(STDERR, "Organization id is required. Use --org=ID or ORG_ID env.\n");
    exit(1);
}

TenantContext::bind((int) $orgId);

$dayInput = $options['date'] ?? 'today';
$monthInput = $options['month'] ?? 'first day of this month';

$day = new DateTimeImmutable($dayInput);
$monthStart = (new DateTimeImmutable($monthInput))->modify('first day of this month');

$processor = new AnalyticsProcessor();
$processor->processDaily($day);
$processor->processMonthly($monthStart);
$processor->processEngagementScores($monthStart);
$processor->detectAnomalies($day);

fwrite(STDOUT, "Analytics processing complete for organization {$orgId}.\n");
