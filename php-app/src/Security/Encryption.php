<?php

declare(strict_types=1);

namespace AttendanceSystem\Security;

use RuntimeException;

final class Encryption
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;
    private const SALT_BYTES = 16;
    private const KEY_BYTES = 32;
    private const HKDF_INFO = 'attendance-system-facial-embedding';

    /**
     * @return array{ciphertext: string, kdf_salt: string}
     */
    public static function encryptEmbedding(string $plaintext): array
    {
        $salt = random_bytes(self::SALT_BYTES);
        $key = self::deriveKey($salt);
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt embedding payload.');
        }

        return [
            'ciphertext' => $iv . $tag . $ciphertext,
            'kdf_salt' => $salt,
        ];
    }

    public static function decryptEmbedding(string $ciphertext, string $kdfSalt): string
    {
        $key = self::deriveKey($kdfSalt);

        $iv = substr($ciphertext, 0, self::IV_BYTES);
        $tag = substr($ciphertext, self::IV_BYTES, self::TAG_BYTES);
        $payload = substr($ciphertext, self::IV_BYTES + self::TAG_BYTES);

        if ($iv === false || $tag === false || $payload === false) {
            throw new RuntimeException('Invalid ciphertext payload.');
        }

        $plaintext = openssl_decrypt(
            $payload,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt embedding payload.');
        }

        return $plaintext;
    }

    private static function deriveKey(string $salt): string
    {
        $managedKey = self::managedKey();
        $derived = hash_hkdf('sha256', $managedKey, self::KEY_BYTES, self::HKDF_INFO, $salt, true);

        if ($derived === false || strlen($derived) !== self::KEY_BYTES) {
            throw new RuntimeException('Failed to derive encryption key.');
        }

        return $derived;
    }

    private static function managedKey(): string
    {
        $raw = getenv('FACIAL_EMBEDDING_MANAGED_KEY');
        if ($raw === false || $raw === '') {
            throw new RuntimeException('FACIAL_EMBEDDING_MANAGED_KEY is required.');
        }

        $decoded = base64_decode($raw, true);
        if ($decoded !== false) {
            $raw = $decoded;
        }

        if (strlen($raw) < self::KEY_BYTES) {
            throw new RuntimeException('FACIAL_EMBEDDING_MANAGED_KEY must be at least 32 bytes.');
        }

        return $raw;
    }
}
