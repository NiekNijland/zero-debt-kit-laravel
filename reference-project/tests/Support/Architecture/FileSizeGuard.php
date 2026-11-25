<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\RecursiveFiles;

final class FileSizeGuard
{
    private const array ROOTS = ['app', 'resources', 'tests'];

    private const array EXEMPT_DATA = ['resources/translations'];

    /**
     * @return list<string>
     */
    public static function oversized(string $root): array
    {
        $files = [];

        foreach (self::ROOTS as $directory) {
            foreach (RecursiveFiles::in($root . '/' . $directory) as $file) {
                if ($file->getExtension() === 'php' && ! self::isExemptData($root, $file->getPathname()) && substr_count((string) file_get_contents($file->getPathname()), "\n") > 400) {
                    $files[] = (string) realpath($file->getPathname());
                }
            }
        }

        sort($files);

        return $files;
    }

    private static function isExemptData(string $root, string $path): bool
    {
        foreach (self::EXEMPT_DATA as $directory) {
            if (str_starts_with($path, $root . '/' . $directory . '/')) {
                return true;
            }
        }

        return false;
    }
}
