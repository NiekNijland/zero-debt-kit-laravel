<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use RuntimeException;
use Tests\Support\Globs;

/**
 * The import spelling of a route file, read from its `use` lines: it imports a controller folder and writes `Folder\Noun\VerbController::class`, never a
 * controller by its own import. The Inertia half of the old guard is `InertiaImportGuard`, for the projects that have pages.
 */
final readonly class ImportSpellingGuard
{
    /**
     * @param string $controllers the controller namespace, as written in a `use`, with single backslashes and no trailing backslash
     */
    public function __construct(
        private string $root,
        private string $controllers,
    ) {
    }

    /**
     * @param list<string> $files the route files and the bootstrap file, relative to the root
     * @return list<string>
     */
    public function violations(array $files): array
    {
        $violations = [];

        foreach ($files as $path) {
            if (! is_file($this->root . '/' . $path)) {
                throw new RuntimeException("the file {$path} does not exist under the root: a guard that reads a named file fails when it is renamed");
            }

            foreach ($this->controllerImportInSource((string) file_get_contents($this->root . '/' . $path)) as $message) {
                $violations[] = $path . ': ' . $message;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function controllerImportInSource(string $source): array
    {
        $pattern = '/^use ' . preg_quote($this->controllers, '/') . '\\\\[\w\\\\]*Controller(?: as \w+)?;$/m';

        return preg_match($pattern, $source) === 1 ? ['a route file imports a controller: import the folder and write Folder\Noun\VerbController::class'] : [];
    }

    /**
     * @return list<string> the paths relative to the root of the files that match a pattern below the root
     */
    public function files(string $pattern): array
    {
        return array_map(fn (string $file): string => substr($file, strlen($this->root) + 1), Globs::of($this->root . '/' . $pattern));
    }
}
