<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\RecursiveFiles;

final readonly class DocumentedPathGuard
{
    private const array SOURCE_ROOTS = ['.github', '.storybook', 'app', 'bootstrap', 'config', 'database', 'docs', 'lang', 'resources', 'routes', 'scripts', 'tests'];

    private const array GUIDES = ['AGENTS.md', 'DESIGN.md', 'docs/review-rubric.md'];

    private const string FILE = '/\.(php|tsx?|mjs|js|json|md|sh|css|neon|ya?ml)$/';

    public function __construct(private string $root)
    {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $files = $this->sourceFiles();
        $guides = array_filter(self::GUIDES, fn (string $guide): bool => is_file($this->root . '/' . $guide));
        $violations = [];

        foreach ($files as $file) {
            if (basename($file) === 'AGENTS.md' && ! in_array($file, $guides, true)) {
                $guides[] = $file;
            }
        }

        sort($guides);

        foreach ($guides as $guide) {
            foreach (self::pathsIn((string) file_get_contents($this->root . '/' . $guide)) as [$line, $path]) {
                $known = in_array(explode('/', $path)[0], self::SOURCE_ROOTS, true);

                if ($known && ! file_exists($this->root . '/' . rtrim($path, '/'))) {
                    $violations[] = $guide . ':' . $line . ': `' . $path . '` does not exist: name the file or folder as it is now, or write an example with a placeholder such as <Name>';
                }

                if (! $known && ! is_dir($this->root . '/' . explode('/', $path)[0]) && preg_match(self::FILE, $path) === 1 && ! array_any($files, static fn (string $file): bool => str_ends_with('/' . $file, '/' . $path))) {
                    $violations[] = $guide . ':' . $line . ': no file ends in `' . $path . '`: name the file as it is now, from the repository root when that is clearer, or write an example with a placeholder such as <Name>';
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{int, string}>
     */
    public static function pathsIn(string $markdown): array
    {
        $paths = [];

        foreach (explode("\n", $markdown) as $index => $text) {
            preg_match_all('/`([A-Za-z0-9_.\/-]+)`/', $text, $matches);

            foreach ($matches[1] as $token) {
                if (str_contains(trim($token, '/'), '/') && ! str_contains($token, '...') && ! str_contains($token, '//')) {
                    $paths[] = [$index + 1, $token];
                }
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (self::SOURCE_ROOTS as $directory) {
            if (! is_dir($this->root . '/' . $directory)) {
                continue;
            }

            foreach (RecursiveFiles::in($this->root . '/' . $directory) as $file) {
                if ($file->isFile()) {
                    $files[] = substr($file->getPathname(), strlen($this->root) + 1);
                }
            }
        }

        return $files;
    }
}
