<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\Architecture\PhpFileInspector;

final class ValidatedInputArchitectureHelpers
{
    private const array RAW_READERS = [
        'input', 'string', 'str', 'integer', 'float', 'boolean', 'date', 'enum', 'enums', 'array', 'collect', 'query', 'post', 'get',
        'all', 'only', 'except', 'safe', 'json', 'keys', 'fluent', 'has', 'hasany', 'filled', 'anyfilled', 'missing', 'exists',
        'isnotfilled', 'whenhas', 'whenfilled', 'file', 'hasfile', 'allfiles',
    ];

    private const array REQUESTS = ['$request', '->request', 'request()'];

    private const array REQUEST_FACADES = ['Request', 'Illuminate\Support\Facades\Request'];

    /**
     * @return list<string> `file:line` of every raw input read in a controller
     */
    public static function offendersInControllers(): array
    {
        $root = dirname(__DIR__, 3);
        $inspector = new PhpFileInspector();
        $offenders = [];

        foreach ($inspector->phpFiles($root . '/app/Http/Controllers') as $file) {
            foreach (self::rawReadLines($inspector->callsIn($file)) as $line) {
                $offenders[] = substr($file, strlen($root) + 1) . ':' . $line;
            }
        }

        return $offenders;
    }

    /**
     * @return list<int>
     */
    public static function rawReadLinesIn(string $source): array
    {
        return self::rawReadLines(new PhpFileInspector()->calls($source));
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @return list<int>
     */
    private static function rawReadLines(array $calls): array
    {
        $lines = [];

        foreach ($calls as $call) {
            $reader = in_array($call['name'], self::RAW_READERS, true);

            if ($reader && in_array($call['kind'], ['method', 'nullsafe'], true) && in_array($call['receiver'], self::REQUESTS, true)) {
                $lines[] = $call['line'];
            }

            if ($reader && $call['kind'] === 'static' && in_array(ltrim($call['written'], '\\'), self::REQUEST_FACADES, true)) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'function' && ltrim(mb_strtolower($call['written']), '\\') === 'request' && $call['arguments'] !== []) {
                $lines[] = $call['line'];
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }
}
