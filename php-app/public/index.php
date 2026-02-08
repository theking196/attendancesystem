<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Jobs/Queue.php';
require_once __DIR__ . '/../src/Services/AnalyticsService.php';
require_once __DIR__ . '/../src/Security/AccessGuard.php';

use AttendanceSystem\Jobs\Queue;
use AttendanceSystem\Services\AnalyticsService;
use AttendanceSystem\Security\AccessGuard;
use DateTimeImmutable;

AccessGuard::enforce();

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
}

function require_role(array $allowedRoles): string
{
    $role = AccessGuard::currentRole();
    if (!$role->isAny($allowedRoles)) {
        json_response([
            'error' => 'Forbidden.',
            'rbac' => [
                'role' => $role->name(),
                'permissions' => $role->permissions(),
                'required_roles' => $allowedRoles,
            ],
        ], 403);
        exit;
    }

    return $role->name();
}

function require_permission(string $permission): string
{
    $role = AccessGuard::currentRole();
    if (!$role->allows($permission)) {
        json_response([
            'error' => 'Forbidden.',
            'rbac' => [
                'role' => $role->name(),
                'permissions' => $role->permissions(),
                'required_permissions' => [$permission],
            ],
        ], 403);
        exit;
    }

    return $role->name();
}

function require_query_date(string $key): string
{
    $value = $_GET[$key] ?? null;
    if (!is_string($value) || $value === '') {
        json_response(['error' => sprintf('Missing %s query parameter.', $key)], 400);
        exit;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        json_response(['error' => sprintf('Invalid %s date format. Use YYYY-MM-DD.', $key)], 400);
        exit;
    }

    return $value;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && $path === '/') {
    http_response_code(200);
    echo 'Attendance System API';
    exit;
}

if ($method === 'GET' && $path === '/app') {
    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Attendance System Dashboard</title>
    <link rel="stylesheet" href="/css/app.css" />
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/js/app.js"></script>
  </body>
</html>';
    exit;
}

if ($method === 'POST' && $path === '/jobs/attendance-recognition') {
    $rawBody = file_get_contents('php://input');
    $payload = $rawBody !== '' ? json_decode($rawBody, true) : [];

    if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
        json_response(['error' => 'Invalid JSON payload.'], 400);
        exit;
    }

    $jobId = Queue::enqueueAttendanceRecognition($payload ?? []);
    json_response(['job_id' => $jobId, 'status' => Queue::STATUS_QUEUED], 202);
    exit;
}

if ($method === 'GET' && preg_match('#^/jobs/(\d+)$#', (string) $path, $matches) === 1) {
    $jobId = (int) $matches[1];
    $job = Queue::fetchJobStatus($jobId);
    if ($job === null) {
        json_response(['error' => 'Job not found.'], 404);
        exit;
    }

    json_response($job, 200);
    exit;
}

if ($method === 'GET' && $path === '/api/v1/me') {
    $role = AccessGuard::currentRole();
    json_response([
        'data' => [
            'role' => $role->name(),
            'permissions' => $role->permissions(),
        ],
    ], 200);
    exit;
}

if ($method === 'GET' && $path === '/api/v1/analytics/daily') {
    require_permission('analytics:read');
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $metrics = $service->fetchDailyMetrics($start, $end);

    json_response(['data' => $metrics], 200);
    exit;
}

if ($method === 'GET' && $path === '/api/v1/analytics/monthly') {
    require_permission('analytics:read');
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $metrics = $service->fetchMonthlyMetrics($start, $end);

    json_response(['data' => $metrics], 200);
    exit;
}

if ($method === 'GET' && $path === '/api/v1/analytics/engagement-scores') {
    require_permission('alerts:read');
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $scores = $service->fetchEngagementScores($start, $end);

    json_response(['data' => $scores], 200);
    exit;
}

if ($method === 'GET' && $path === '/api/v1/analytics/alerts') {
    require_permission('alerts:read');
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $alerts = $service->fetchAlerts($start, $end);

    json_response(['data' => $alerts], 200);
    exit;
}

if ($method === 'GET' && $path === '/dashboard/analytics/daily') {
    require_role(['viewer', 'manager', 'admin']);
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $metrics = $service->fetchDailyMetrics($start, $end);

    json_response(['data' => $metrics], 200);
    exit;
}

if ($method === 'GET' && $path === '/dashboard/analytics/monthly') {
    require_role(['viewer', 'manager', 'admin']);
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $metrics = $service->fetchMonthlyMetrics($start, $end);

    json_response(['data' => $metrics], 200);
    exit;
}

if ($method === 'GET' && $path === '/dashboard/analytics/engagement-scores') {
    require_role(['manager', 'admin']);
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $scores = $service->fetchEngagementScores($start, $end);

    json_response(['data' => $scores], 200);
    exit;
}

if ($method === 'GET' && $path === '/dashboard/analytics/alerts') {
    require_role(['manager', 'admin']);
    $start = require_query_date('start');
    $end = require_query_date('end');

    $service = new AnalyticsService();
    $alerts = $service->fetchAlerts($start, $end);

    json_response(['data' => $alerts], 200);
    exit;
}

json_response(['error' => 'Not found.'], 404);
