<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\Architecture\PhpFileInspector;

final class EmptyCatchArchitectureHelpers
{
    /**
     * @return list<int>
     */
    public static function swallowedCatchLines(string $source): array
    {
        $file = sys_get_temp_dir() . '/empty-catch-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($file, $source);

        try {
            return new PhpFileInspector()->swallowedBroadCatchLines($file);
        } finally {
            unlink($file);
        }
    }

    /**
     * @return array<string, int>
     */
    public static function swallowedCatchCounts(): array
    {
        $root = dirname(__DIR__, 3);
        $counts = [];

        $inspector = new PhpFileInspector();

        foreach ($inspector->phpFiles($root . '/app') as $file) {
            $count = count($inspector->swallowedBroadCatchLines($file));

            if ($count > 0) {
                $counts[substr($file, strlen($root) + 1)] = $count;
            }
        }

        ksort($counts);

        return $counts;
    }
}
