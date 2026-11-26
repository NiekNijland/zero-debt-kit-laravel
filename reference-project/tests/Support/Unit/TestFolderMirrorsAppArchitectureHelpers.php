<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\Architecture\PhpFileInspector;

final class TestFolderMirrorsAppArchitectureHelpers
{
    /** The folders of `tests/<tier>` that cover code outside `app/`, with the folder of the repository they cover. */
    private const array HOMES = [
        'Bootstrap' => 'bootstrap',
        'Config' => 'config',
        'Database' => 'database',
        'Lang' => 'lang',
        'Routing' => 'routes',
        'View' => 'resources/views',
    ];

    /**
     * @return list<string> `<test file>: <what is wrong>` per test that does not sit where the code it covers sits
     */
    public static function violations(string $root): array
    {
        $inspector = new PhpFileInspector();
        $classes = [];
        $folders = [];

        foreach ($inspector->phpFiles($root . '/app') as $file) {
            $relative = substr($file, strlen($root . '/app/'));
            $folder = dirname($relative) === '.' ? '' : dirname($relative);
            $classes[basename($relative, '.php')][$folder] = true;

            for ($ancestor = $folder; $ancestor !== '' && $ancestor !== '.'; $ancestor = dirname($ancestor)) {
                $folders[$ancestor] = true;
            }
        }

        $violations = [];

        foreach (['Unit', 'Feature', 'Integration'] as $tier) {
            foreach ($inspector->phpFiles($root . '/tests/' . $tier, mayBeAbsent: true) as $file) {
                if (! str_ends_with($file, 'Test.php')) {
                    continue;
                }

                $relative = substr($file, strlen($root . '/tests/' . $tier . '/'));
                $folder = dirname($relative) === '.' ? '' : dirname($relative);
                $name = basename($relative, 'Test.php');
                $path = 'tests/' . $tier . '/' . $relative;

                if (isset($classes[$name])) {
                    if (! isset($classes[$name][$folder])) {
                        $violations[] = $path . ': the class ' . $name . ' lives in app/' . implode(' and app/', array_keys($classes[$name])) . ', the test belongs in the same folder of tests/' . $tier;
                    }

                    continue;
                }

                $home = explode('/', $folder)[0];

                if ($folder !== '' && ! isset($folders[$folder]) && ! isset(self::HOMES[$home])) {
                    $violations[] = $path . ': app/' . $folder . ' does not exist, the test belongs in the folder of the code it covers';
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param array<string, string> $files path relative to the sandbox => contents
     */
    public static function sandbox(array $files): string
    {
        $root = sys_get_temp_dir() . '/test-folder-mirrors-' . bin2hex(random_bytes(6));

        foreach ($files as $path => $contents) {
            if (! is_dir(dirname($root . '/' . $path))) {
                mkdir(dirname($root . '/' . $path), 0777, true);
            }

            file_put_contents($root . '/' . $path, $contents);
        }

        return $root;
    }

    public static function remove(string $root): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            if ($entry instanceof SplFileInfo) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
        }

        rmdir($root);
    }
}
