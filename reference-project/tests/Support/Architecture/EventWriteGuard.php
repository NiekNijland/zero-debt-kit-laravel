<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class EventWriteGuard
{
    private const string WRITES = 'create|forceCreate|firstOrCreate|updateOrCreate|insert|insertOrIgnore|insertGetId|insertUsing|upsert|update|updateOrInsert|increment|decrement|delete|forceDelete|truncate|prune|massPrune';

    /**
     * @param list<string> $folders
     * @param list<string> $allowed paths relative to the root: the audit service and the pruning on the model
     * @param string $eventModel the class name of the event model, as it is imported
     * @param string $table the table of the events
     * @param string $exemptPath a pattern of the relative path of the migrations of the table: they rewrite event names when an event class moves, and a migration has no class
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
        private array $allowed,
        private string $eventModel,
        private string $table,
        private string $exemptPath,
        private string $service,
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
            $this->folders,
            fn (string $path, string $source): array => in_array($path, $this->allowed, true) || preg_match($this->exemptPath, $path) === 1 ? [] : $this->inSource($source),
        );
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $table = preg_quote($this->table, '/');
        $patterns = [
            '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO|TRUNCATE(?:\s+TABLE)?)\s+' . $table . '\b/i',
            '/DB::table\(\s*[\'"]' . $table . '[\'"]\s*\)[^;]*->\s*(?:' . self::WRITES . ')\s*\(/s',
        ];

        if (preg_match_all('/^use\s+' . preg_quote($this->eventModel, '/') . '(?:\s+as\s+(\w+))?;/m', $source, $imports, PREG_SET_ORDER) > 0) {
            foreach ($imports as $import) {
                $name = $import[1] ?? basename(str_replace('\\', '/', $this->eventModel));
                $patterns[] = '/\b' . $name . '::[^;]*->\s*(?:' . self::WRITES . ')\s*\(/s';
                $patterns[] = '/\b' . $name . '::(?:' . self::WRITES . ')\s*\(/';
            }
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                return ['an event row is changed or removed: write it through ' . $this->service . ' and let the pruning on the model remove it'];
            }
        }

        return [];
    }
}
