<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * Runs a file-based reader of the inspector over a source string: the source is written to a temporary file for the length of the call.
 */
final class TemporarySource
{
    /**
     * @template T
     * @param callable(string): T $read receives the path of the temporary file
     * @return T
     */
    public static function with(string $source, callable $read): mixed
    {
        $path = sys_get_temp_dir() . '/source-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($path, $source);

        try {
            return $read($path);
        } finally {
            unlink($path);
        }
    }
}
