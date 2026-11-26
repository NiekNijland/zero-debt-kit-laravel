<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Inertia\Inertia;

final readonly class InertiaPageGuard
{
    /**
     * @param list<string> $sources the folders that render pages, relative to the root; any of them may be absent
     * @param string $pages the folder of the page files, relative to the root
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $sources,
        private string $pages,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->sources as $directory) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $directory, mayBeAbsent: true) as $file) {
                foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                    $violations[] = substr($file, strlen($this->root) + 1) . ':' . $violation;
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $violations = [];

        foreach ($this->inspector->calls($source) as $call) {
            $page = match (true) {
                $call['kind'] === 'static' && $call['name'] === 'render' && $call['class'] === Inertia::class,
                $call['kind'] === 'function' && $call['name'] === 'inertia' && $call['arguments'] !== [] => $call['arguments'][0] ?? '',
                $call['kind'] === 'method' && $call['name'] === 'defaults' && ($call['arguments'][0] ?? '') === "'component'" => $call['arguments'][1] ?? '',
                default => null,
            };

            if ($page === null) {
                continue;
            }

            if (preg_match('/^\'([^\'\\\\$]+)\'$/', $page, $matches) !== 1) {
                $violations[] = $call['line'] . ': an Inertia page that is not named by a string: write the page as text, as in Inertia::render(\'Order/Show\', ...), so the file in ' . $this->pages . ' can be found';

                continue;
            }

            if (! is_file($this->root . '/' . $this->pages . '/' . $matches[1] . '.tsx')) {
                $violations[] = $call['line'] . ': the Inertia page ' . $matches[1] . ' has no file: add ' . $this->pages . '/' . $matches[1] . '.tsx or render the page that exists';
            }
        }

        return $violations;
    }
}
