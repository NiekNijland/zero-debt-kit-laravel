<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestTierGuard;
use Tests\Support\RecursiveFiles;

final class TestTierArchitectureHelpers
{
    public static function tierGuardFor(string $root): TestTierGuard
    {
        return new TestTierGuard(new PhpFileInspector(), $root);
    }

    /**
     * @param array<string, string> $files
     */
    public static function tierSandbox(array $files): string
    {
        $root = sys_get_temp_dir() . '/tier-guard-' . bin2hex(random_bytes(6));

        foreach ($files as $path => $contents) {
            $absolute = $root . '/' . $path;
            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0777, true);
            }

            file_put_contents($absolute, $contents);
        }

        return $root;
    }

    public static function tierRemove(string $root): void
    {
        foreach (RecursiveFiles::in($root, childFirst: true) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($root);
    }
}
