<?php

declare(strict_types=1);

namespace AttendanceSystem\Security;

use RuntimeException;

final class Role
{
    private const PERMISSIONS = [
        'viewer' => ['analytics:read'],
        'manager' => ['analytics:read', 'alerts:read'],
        'admin' => ['analytics:read', 'alerts:read', 'jobs:write'],
    ];

    private string $name;

    /** @var Permission[] */
    private array $permissions;

    /**
     * @param Permission[] $permissions
     */
    public function __construct(string $name, array $permissions)
    {
        $this->name = $name;
        $this->permissions = $permissions;
    }

    public static function fromName(string $name): self
    {
        $normalized = strtolower($name);
        if (!array_key_exists($normalized, self::PERMISSIONS)) {
            throw new RuntimeException(sprintf('Unknown role "%s".', $name));
        }

        $permissions = array_map(
            static fn (string $permission) => new Permission($permission),
            self::PERMISSIONS[$normalized]
        );

        return new self($normalized, $permissions);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isAny(array $allowedRoles): bool
    {
        foreach ($allowedRoles as $role) {
            if (strtolower((string) $role) === $this->name) {
                return true;
            }
        }

        return false;
    }

    public function allows(string $permissionName): bool
    {
        if ($this->name === 'admin') {
            return true;
        }

        foreach ($this->permissions as $permission) {
            if ($permission->name() === $permissionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function permissions(): array
    {
        return array_map(
            static fn (Permission $permission) => $permission->name(),
            $this->permissions
        );
    }
}
