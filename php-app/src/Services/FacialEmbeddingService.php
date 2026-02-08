<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;
use AttendanceSystem\Security\Encryption;

final class FacialEmbeddingService
{
    public function createEnrollment(int $userId, string $embeddingPayload, array $context = []): int
    {
        $encrypted = Encryption::encryptEmbedding($embeddingPayload);

        $embeddingId = Database::insert('facial_embeddings', [
            'user_id' => $userId,
            'embedding_ciphertext' => $encrypted['ciphertext'],
            'kdf_salt' => $encrypted['kdf_salt'],
        ]);

        AuditLogger::logEnrollment($userId, $embeddingId, $context);

        return $embeddingId;
    }

    public function updateEmbedding(int $embeddingId, string $embeddingPayload, array $context = []): void
    {
        $encrypted = Encryption::encryptEmbedding($embeddingPayload);

        Database::execute(
            'UPDATE facial_embeddings SET embedding_ciphertext = :embedding_ciphertext, kdf_salt = :kdf_salt, created_at = NOW() WHERE id = :id',
            [
                'embedding_ciphertext' => $encrypted['ciphertext'],
                'kdf_salt' => $encrypted['kdf_salt'],
                'id' => $embeddingId,
            ]
        );

        AuditLogger::logEmbeddingUpdate($embeddingId, $context);
    }
}
