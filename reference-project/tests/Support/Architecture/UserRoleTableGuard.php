<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class UserRoleTableGuard
{
    private const array PATTERNS = [
        '/model_has_(?:roles|permissions)[^;]*->(?:insert|insertOrIgnore|insertGetId|upsert|update|delete|truncate)\s*\(/s',
        '/(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE)\s+model_has_(?:roles|permissions)/i',
        '/->(?:roles|permissions)\(\)->(?:attach|sync|detach|toggle)\s*\(/',
    ];

    /**
     * @param list<string> $folders
     * @param list<string> $allowed paths relative to the root
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
        private array $allowed,
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
            $this->folders,
            fn (string $path, string $source): array => in_array($path, $this->allowed, true) ? [] : $this->inSource($source),
        );
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                return ['the role and permission tables of a user are written by hand: call ' . $this->owner . ', which writes them and checks who may'];
            }
        }

        return [];
    }
}
