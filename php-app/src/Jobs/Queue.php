<?php

declare(strict_types=1);

namespace AttendanceSystem\Jobs;

use AttendanceSystem\Database;

final class Queue
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const TYPE_ATTENDANCE_RECOGNITION = 'attendance_recognition';

    public static function enqueueAttendanceRecognition(array $payload): int
    {
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        return Database::insert('jobs', [
            'job_type' => self::TYPE_ATTENDANCE_RECOGNITION,
            'payload' => $encodedPayload,
            'status' => self::STATUS_QUEUED,
        ]);
    }

    public static function fetchJobStatus(int $jobId): ?array
    {
        $rows = Database::select(
            'SELECT id, job_type, payload, status, created_at, updated_at FROM jobs WHERE id = :id',
            ['id' => $jobId]
        );

        if ($rows === []) {
            return null;
        }

        $job = $rows[0];
        $payload = $job['payload'];
        $decodedPayload = is_string($payload) ? json_decode($payload, true) : $payload;
        if ($decodedPayload === null && json_last_error() !== JSON_ERROR_NONE) {
            $decodedPayload = $payload;
        }

        return [
            'job_id' => (int) $job['id'],
            'job_type' => $job['job_type'],
            'status' => $job['status'],
            'payload' => $decodedPayload,
            'created_at' => $job['created_at'],
            'updated_at' => $job['updated_at'],
        ];
    }
}
