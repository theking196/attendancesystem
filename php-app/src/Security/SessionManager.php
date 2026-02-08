<?php

declare(strict_types=1);

namespace AttendanceSystem\Security;

use RuntimeException;

final class SessionManager
{
    private const ROTATION_INTERVAL_SECONDS = 900;

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.cookie_samesite', 'Strict');

        $secureCookie = self::shouldUseSecureCookies();
        ini_set('session.cookie_secure', $secureCookie ? '1' : '0');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Unable to start secure session.');
        }

        self::rotateIfNeeded();
    }

    private static function rotateIfNeeded(): void
    {
        $now = time();
        $rotatedAt = $_SESSION['rotated_at'] ?? null;

        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['rotated_at'] = $now;
            return;
        }

        if ($rotatedAt === null || !is_int($rotatedAt)) {
            $_SESSION['rotated_at'] = $now;
            return;
        }

        if ($now - $rotatedAt >= self::ROTATION_INTERVAL_SECONDS) {
            session_regenerate_id(true);
            $_SESSION['rotated_at'] = $now;
        }
    }

    private static function shouldUseSecureCookies(): bool
    {
        $forceSecure = getenv('SESSION_COOKIE_SECURE');
        if ($forceSecure !== false && $forceSecure !== '') {
            return filter_var($forceSecure, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}
