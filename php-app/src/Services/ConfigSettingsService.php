<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;

final class ConfigSettingsService
{
    public function get(string $key): ?string
    {
        $rows = Database::select(
            'SELECT setting_value FROM config_settings WHERE setting_key = :setting_key',
            ['setting_key' => $key]
        );

        if ($rows === []) {
            return null;
        }

        $value = $rows[0]['setting_value'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
}
