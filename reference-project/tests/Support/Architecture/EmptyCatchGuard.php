<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class EmptyCatchGuard
{
    private const string MESSAGE = 'a broad catch that only swallows the exception: report it with report(), rethrow it, or catch the exact exception the code expects';

    /**
     * @param list<string> $directories folders under the root that the tree scan reads, relative to it
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $directories,
    ) {
    }

    /**
     * @return list<string> `line N: message` per swallowed broad catch in the source
     */
    public function inSource(string $source): array
    {
        $file = sys_get_temp_dir() . '/empty-catch-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($file, $source);

        try {
            return array_map(static fn (int $line): string => 'line ' . $line . ': ' . self::MESSAGE, $this->inspector->swallowedBroadCatchLines($file));
        } finally {
            unlink($file);
        }
    }

    /**
     * @return list<string> `file:line: message` relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->directories as $directory) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $directory) as $file) {
                foreach ($this->inspector->swallowedBroadCatchLines($file) as $line) {
                    $violations[] = substr($file, strlen($this->root) + 1) . ':' . $line . ': ' . self::MESSAGE;
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
