<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Jobs/Queue.php';

use AttendanceSystem\Jobs\Queue;

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && $path === '/') {
    http_response_code(200);
    echo 'Attendance System API';
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

json_response(['error' => 'Not found.'], 404);
