<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class LikeSearchGuard
{
    private const string CASE_INSENSITIVE = '/\bilike\b|\blower\s*\(.*\)\s*like\b/is';

    /** `whereLike` is case-insensitive by default, so any hand-built pattern in it is the case this guard is about. */
    private const array LIKE_METHODS = ['wherelike', 'orwherelike', 'wherenotlike', 'orwherenotlike'];

    private const string HELPER_CALL = '/Like::\w+(\((?:[^()]|(?1))*\))/';

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
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
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
            $arguments = array_values(array_filter($call['arguments'], static fn (string $argument): bool => preg_match(self::CLOSURE, $argument) !== 1));
            $insensitive = in_array(mb_strtolower($call['name']), self::LIKE_METHODS, true) || preg_match(self::CASE_INSENSITIVE, implode(', ', $arguments)) === 1;

            if (! $insensitive) {
                continue;
            }

            // A call of the helper is cut out of its argument, so one helper call does not excuse a second pattern built by hand in the same call.
            $arguments = array_map(static fn (string $argument): string => (string) preg_replace(self::HELPER_CALL, '', $argument), $arguments);

            if (! $this->builtByHand($arguments)) {
                continue;
            }

            $violations[] = $call['line'] . ': a case-insensitive LIKE with a pattern built by hand: write whereLike($column, Like::contains($term)), or for a prefix search over an expression index whereRaw(\'lower(col) like ?\', [Like::escape($term) . \'%\'])';
        }

        return array_values(array_unique($violations));
    }

    /**
     * The first argument holds the SQL or the column: a variable there is a fragment of SQL, an interpolation or a
     * percent sign in it is a pattern. Every other argument is a binding or a pattern: a variable or a call in it
     * that is not the helper is a pattern built by hand, a plain quoted text is a fixed pattern.
     *
     * @param list<string> $arguments
     */
    private function builtByHand(array $arguments): bool
    {
        foreach ($arguments as $index => $argument) {
            if ($index === 0 && preg_match('/\{\$|\$\{|%/', $argument) === 1) {
                return true;
            }

            if ($index > 0 && preg_match('/\$|\w\s*\(/', $argument) === 1) {
                return true;
            }
        }

        return false;
    }
}
