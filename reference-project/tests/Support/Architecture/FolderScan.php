<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The scan that the folder guards share: each PHP file below a list of folders of the root is read once and handed to a check, with its path relative
 * to the root (never the absolute path, so a checkout under a dot directory behaves the same). A folder that does not exist is an error.
 */
final class FolderScan
{
    /**
     * @param list<string> $folders
     * @param callable(string, string): list<string> $check receives the relative path and the source, answers the messages
     * @return list<string> `path: message` per finding
     */
    public static function over(PhpFileInspector $inspector, string $root, array $folders, callable $check): array
    {
        $violations = [];

        foreach ($folders as $folder) {
            foreach ($inspector->phpFiles($root . '/' . $folder) as $file) {
                $path = substr($file, strlen($root) + 1);

                foreach ($check($path, (string) file_get_contents($file)) as $message) {
                    $violations[] = $path . ': ' . $message;
                }
            }
        }

        return $violations;
    }
}
