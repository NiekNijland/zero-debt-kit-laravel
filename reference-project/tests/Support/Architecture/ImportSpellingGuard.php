<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use RuntimeException;
use Tests\Support\Globs;

/**
 * Two import spellings, read from the `use` lines of a file: a route file imports a controller folder and writes `Folder\Noun\VerbController::class`
 * (never a controller by its own import), and the Inertia response is imported as `InertiaResponse` (never as `Response`, which is the HTTP response's name).
 */
final readonly class ImportSpellingGuard
{
    /**
     * @param string $controllers the controller namespace, as written in a `use`, with single backslashes and no trailing backslash
     * @param string $page the class name of the Inertia response, with single backslashes
     * @param string $pageAlias the alias that the Inertia response is imported as
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $controllers,
        private string $page,
        private string $pageAlias,
    ) {
    }

    /**
     * @param list<string> $files the route files and the bootstrap file, relative to the root
     * @return list<string>
     */
    public function controllerImports(array $files): array
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
     * @return list<string>
     */
    public function pageImportInSource(string $source): array
    {
        $pattern = '/^use ' . preg_quote($this->page, '/') . '(?! as ' . $this->pageAlias . ';)(?: as \w+)?;$/m';

        return preg_match($pattern, $source) === 1 ? ['the Inertia response is imported under another name: import it as ' . $this->pageAlias . ', because Response is the HTTP response'] : [];
    }

    /**
     * @return list<string>
     */
    public function pageImports(string $folder): array
    {
        return FolderScan::over($this->inspector, $this->root, [$folder], fn (string $path, string $source): array => $this->pageImportInSource($source));
    }

    /**
     * @return list<string> the paths relative to the root of the files that match a pattern below the root
     */
    public function files(string $pattern): array
    {
        return array_map(fn (string $file): string => substr($file, strlen($this->root) + 1), Globs::of($this->root . '/' . $pattern));
    }
}
