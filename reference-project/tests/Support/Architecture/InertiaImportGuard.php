<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The Inertia response is imported as `InertiaResponse` (never as `Response`, which is the HTTP response's name), read from the `use` lines of a file.
 */
final readonly class InertiaImportGuard
{
    /**
     * @param class-string $page the class name of the Inertia response
     * @param string $pageAlias the alias that the Inertia response is imported as
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $page,
        private string $pageAlias,
    ) {
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
     * @param list<string> $folders the folders that may import it, relative to the root; a folder that does not exist is an error
     * @return list<string> `path: message` per file
     */
    public function violations(array $folders): array
    {
        return FolderScan::over($this->inspector, $this->root, $folders, fn (string $path, string $source): array => $this->pageImportInSource($source));
    }
}
