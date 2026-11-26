<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class LikeSearchGuard
{
    private const string CASE_INSENSITIVE = '/\bilike\b|\blower\s*\(.*\)\s*like\b/is';

    private const string CLOSURE = '/^(static\s+)?(fn|function)\b/';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = substr($file, strlen($this->root) + 1) . ':' . $violation;
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
            $arguments = implode(', ', array_filter($call['arguments'], static fn (string $argument): bool => preg_match(self::CLOSURE, $argument) !== 1));

            if (preg_match(self::CASE_INSENSITIVE, $arguments) !== 1 || str_contains($arguments, 'Like::') || ! str_contains($arguments, '$')) {
                continue;
            }

            $violations[] = $call['line'] . ': a case-insensitive LIKE with a pattern built by hand: write whereLike($column, Like::contains($term)), or for a prefix search over an expression index whereRaw(\'lower(col) like ?\', [Like::escape($term) . \'%\'])';
        }

        return array_values(array_unique($violations));
    }
}
