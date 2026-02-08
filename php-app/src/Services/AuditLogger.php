<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;

final class AuditLogger
{
    public const EVENT_ENROLLMENT_CREATED = 'enrollment_created';
    public const EVENT_EMBEDDING_UPDATED = 'embedding_updated';

    public static function logEnrollment(int $userId, int $embeddingId, array $context = []): int
    {
        $payload = array_merge($context, [
            'user_id' => $userId,
            'embedding_id' => $embeddingId,
        ]);

        return self::log(self::EVENT_ENROLLMENT_CREATED, $payload, $userId);
    }

    public static function logEmbeddingUpdate(int $embeddingId, array $context = []): int
    {
        $payload = array_merge($context, [
            'embedding_id' => $embeddingId,
        ]);

        return self::log(self::EVENT_EMBEDDING_UPDATED, $payload, null);
    }

    private static function log(string $eventType, array $payload, ?int $userId): int
    {
        return Database::insert('audit_logs', [
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }
}
