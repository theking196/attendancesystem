<?php

declare(strict_types=1);

namespace AttendanceSystem;

use PDO;
use PDOStatement;

final class Database
{
    private static ?PDO $connection = null;

    public static function select(string $sql, array $params = []): array
    {
        $statement = self::prepareTenantScopedStatement($sql, $params);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function execute(string $sql, array $params = []): int
    {
        $statement = self::prepareTenantScopedStatement($sql, $params);
        $statement->execute();

        return $statement->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        TenantContext::requireBound();

        $tenantData = array_merge(['organization_id' => TenantContext::organizationId()], $data);
        $columns = array_keys($tenantData);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $statement = self::connection()->prepare($sql);
        foreach ($tenantData as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }

        $statement->execute();

        return (int) self::connection()->lastInsertId();
    }

    private static function prepareTenantScopedStatement(string $sql, array $params): PDOStatement
    {
        TenantContext::requireBound();

        $tenantSql = self::applyTenantScope($sql);
        $tenantParams = array_merge(['organization_id' => TenantContext::organizationId()], $params);

        $statement = self::connection()->prepare($tenantSql);
        $statement->bindValue(':organization_id', $tenantParams['organization_id'], PDO::PARAM_INT);

        foreach ($tenantParams as $key => $value) {
            if ($key === 'organization_id') {
                continue;
            }

            $placeholder = ':' . ltrim((string) $key, ':');
            $statement->bindValue($placeholder, $value);
        }

        return $statement;
    }

    private static function applyTenantScope(string $sql): string
    {
        $normalizedSql = trim($sql);
        $lowerSql = strtolower($normalizedSql);

        if (str_starts_with($lowerSql, 'select')) {
            if (str_contains($lowerSql, ' where ')) {
                return $normalizedSql . ' AND organization_id = :organization_id';
            }

            return $normalizedSql . ' WHERE organization_id = :organization_id';
        }

        if (str_starts_with($lowerSql, 'update')) {
            if (str_contains($lowerSql, ' where ')) {
                return $normalizedSql . ' AND organization_id = :organization_id';
            }

            return $normalizedSql . ' WHERE organization_id = :organization_id';
        }

        if (str_starts_with($lowerSql, 'delete')) {
            if (str_contains($lowerSql, ' where ')) {
                return $normalizedSql . ' AND organization_id = :organization_id';
            }

            return $normalizedSql . ' WHERE organization_id = :organization_id';
        }

        return $normalizedSql;
    }

    private static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = getenv('DB_DSN') ?: '';
        if ($dsn === '') {
            throw new RuntimeException('DB_DSN environment variable is required.');
        }

        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';

        self::$connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$connection;
    }
}
