<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * Outside the HTTP layer a refusal is a typed exception, not an HTTP answer: `abort(401|403)`, `abort_if()` and `abort_unless()` with those
 * statuses, and an access-denied, unauthorized or 401 and 403 HTTP exception. A refusal there is a domain exception that extends the framework's
 * authorization exception, which the framework answers with 403.
 */
final readonly class HttpRefusalGuard
{
    /**
     * @param list<string> $exemptPrefixes path prefixes relative to the root of the HTTP layer: the controllers have the stricter rule, and a middleware answers the HTTP request itself
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private array $exemptPrefixes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over(
            $this->inspector,
            $this->root,
            [$this->folder],
            fn (string $path, string $source): array => array_any($this->exemptPrefixes, static fn (string $prefix): bool => str_starts_with($path, $prefix)) ? [] : $this->inSource($source),
        );
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $lines = [];

        foreach ($this->inspector->calls($source) as $call) {
            $function = $call['kind'] === 'function' ? ltrim(mb_strtolower($call['written']), '\\') : '';
            $status = match ($function) {
                'abort' => $call['arguments'][0] ?? '',
                'abort_if', 'abort_unless' => $call['arguments'][1] ?? '',
                default => '',
            };

            if (self::denies($status)) {
                $lines[] = $call['line'];
            }
        }

        foreach (PhpCallReader::instantiations($source) as $instantiation) {
            $class = basename(str_replace('\\', '/', $instantiation['class']));

            if (in_array($class, ['AccessDeniedHttpException', 'UnauthorizedHttpException'], true) || ($class === 'HttpException' && self::denies($instantiation['arguments'][0] ?? ''))) {
                $lines[] = $instantiation['line'];
            }
        }

        sort($lines);

        return array_map(
            static fn (int $line): string => 'line ' . $line . ': an HTTP refusal outside the HTTP layer: throw a domain exception that extends the authorization exception, and let the framework answer 403',
            $lines,
        );
    }

    private static function denies(string $status): bool
    {
        $status = trim($status, '() ');

        return in_array($status, ['401', '403'], true) || str_ends_with($status, 'HTTP_FORBIDDEN') || str_ends_with($status, 'HTTP_UNAUTHORIZED');
    }
}
