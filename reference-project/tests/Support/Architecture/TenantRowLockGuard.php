<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * Only the tenant row lock locks the row of a tenant: a lock after `Tenant::` or on the tenants table, in any file but the one that owns it.
 */
final readonly class TenantRowLockGuard
{
    /**
     * @param string $allowed the path relative to the root of the class that owns the lock
     * @param string $model the short name of the tenant model
     * @param string $table the table of the tenants
     * @param string $owner the short name of the class that owns the lock, for the message
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private string $allowed,
        private string $model,
        private string $table,
        private string $owner,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over(
            $this->inspector,
            $this->root,
            [$this->folder],
            fn (string $path, string $source): array => $path === $this->allowed ? [] : $this->inSource($source),
        );
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $pattern = '/(?:\b' . preg_quote($this->model, '/') . '::|table\([\'"]' . preg_quote($this->table, '/') . '[\'"]\))[^;]*?->(?:lockForUpdate|lock|sharedLock)\(/s';

        return preg_match($pattern, $source) === 1 ? ['the row of a tenant is locked directly: lock it through ' . $this->owner . ', the one place that fixes the lock mode and the order'] : [];
    }
}
