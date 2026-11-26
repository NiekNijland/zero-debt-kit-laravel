<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ValidatedInputGuard
{
    private const array RAW_READERS = [
        'input', 'string', 'str', 'integer', 'float', 'boolean', 'date', 'enum', 'enums', 'array', 'collect', 'query', 'post', 'get',
        'all', 'only', 'except', 'safe', 'json', 'keys', 'fluent', 'has', 'hasany', 'filled', 'anyfilled', 'missing', 'exists',
        'isnotfilled', 'whenhas', 'whenfilled', 'file', 'hasfile', 'allfiles',
    ];

    private const array REQUESTS = ['$request', '->request', 'request()'];

    private const array REQUEST_FACADES = ['Request', 'Illuminate\Support\Facades\Request'];

    private const string MESSAGE = 'request input read past the form request: read it from $request->validated() (or an accessor of the form request) so only validated data reaches the action';

    /**
     * @param string $folder the controller folder under the root
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string> `line N: message` per raw input read in the source
     */
    public function inSource(string $source): array
    {
        return array_map(static fn (int $line): string => 'line ' . $line . ': ' . self::MESSAGE, self::rawReadLines($this->inspector->calls($source)));
    }

    /**
     * @return list<string> `file:line N: message` per raw input read in a controller, relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach (self::rawReadLines($this->inspector->callsIn($file)) as $line) {
                $violations[] = substr($file, strlen($this->root) + 1) . ':line ' . $line . ': ' . self::MESSAGE;
            }
        }

        return $violations;
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
