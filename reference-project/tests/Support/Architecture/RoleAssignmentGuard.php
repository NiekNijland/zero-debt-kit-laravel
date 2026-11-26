<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class RoleAssignmentGuard
{
    public const array ALL = ['assignRole', 'syncRoles', 'removeRole', 'givePermissionTo', 'revokePermissionTo'];

    public const array ROLES_ONLY = ['assignRole', 'syncRoles', 'removeRole'];

    /**
     * @param array<string, list<string>> $scopes folder relative to the root => the methods that may not be called in it
     * @param list<string> $allowed paths relative to the root that may call them: the owner and the seeder that writes the base rows
     * @param string $owner the short name of the one class that changes roles, for the message
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $scopes,
        private array $allowed,
        private string $owner,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->scopes as $folder => $methods) {
            $violations = [...$violations, ...FolderScan::over(
                $this->inspector,
                $this->root,
                [$folder],
                fn (string $path, string $source): array => in_array($path, $this->allowed, true) ? [] : $this->inSource($source, $methods),
            )];
        }

        return $violations;
    }

    /**
     * @param list<string> $methods
     * @return list<string>
     */
    public function inSource(string $source, array $methods = self::ALL): array
    {
        if (preg_match('/->(' . implode('|', $methods) . ')\s*\(/', $source, $match) !== 1) {
            return [];
        }

        return ['->' . $match[1] . '() changes the roles or permissions of a user: call ' . $this->owner . ', the one class that does it and checks who may'];
    }
}
