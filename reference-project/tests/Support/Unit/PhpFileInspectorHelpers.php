<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

final class PhpFileInspectorHelpers
{
    public static function checksRoleName(string $source): bool
    {
        $root = SourceSandbox::create(['Subject.php' => $source]);

        try {
            return ArchChecks::checksRoleName(new PhpFileInspector(), $root . '/Subject.php');
        } finally {
            SourceSandbox::remove($root);
        }
    }

    public static function castsDateMutably(string $source): bool
    {
        $root = SourceSandbox::create(['Subject.php' => $source]);

        try {
            return ArchChecks::castsDateMutably(new PhpFileInspector(), $root . '/Subject.php');
        } finally {
            SourceSandbox::remove($root);
        }
    }

    /**
     * @return list<string> `kind|line|callee|receiver` per call
     */
    public static function summarisedCalls(string $source): array
    {
        return array_map(
            static fn (array $call): string => implode('|', [$call['kind'], $call['line'], $call['written'] !== '' ? $call['written'] : $call['name'], $call['receiver'] ?? '-']),
            new PhpFileInspector()->calls($source),
        );
    }
}
