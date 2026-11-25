<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\Narrow;

it('indexes the columns of every foreign key', function (): void {
    $foreignKeys = DB::select(<<<'SQL'
        SELECT c.conrelid::regclass::text AS table_name,
               c.conname AS name,
               array_to_json(ARRAY(
                   SELECT a.attname
                   FROM unnest(c.conkey) WITH ORDINALITY AS k(attnum, position)
                   JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum
                   ORDER BY k.position
               )) AS columns
        FROM pg_constraint c
        WHERE c.contype = 'f' AND c.connamespace = 'public'::regnamespace
        SQL);

    $indexes = collect(DB::select(<<<'SQL'
        SELECT i.indrelid::regclass::text AS table_name,
               array_to_json(ARRAY(
                   SELECT a.attname
                   FROM unnest(i.indkey::int2[]) WITH ORDINALITY AS k(attnum, position)
                   LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum
                   WHERE k.position <= i.indnkeyatts
                   ORDER BY k.position
               )) AS columns,
               pg_get_expr(i.indpred, i.indrelid) AS predicate
        FROM pg_index i
        JOIN pg_class t ON t.oid = i.indrelid
        JOIN pg_class ic ON ic.oid = i.indexrelid
        JOIN pg_am am ON am.oid = ic.relam
        WHERE t.relnamespace = 'public'::regnamespace
          AND am.amname IN ('btree', 'hash')
        SQL))->groupBy('table_name');

    $unindexed = collect(array_map(Narrow::row(...), $foreignKeys))
        ->reject(function (stdClass $foreignKey) use ($indexes): bool {
            $columns = Narrow::strings(json_decode(Narrow::string($foreignKey->columns), true));
            $prefixes = array_filter([$columns, array_values(array_diff($columns, ['tenant_id']))], static fn (mixed $prefix): bool => $prefix !== []);
            $predicates = array_map(fn (string $column): string => "({$column} IS NOT NULL)", $columns);

            return $indexes->get(Narrow::string($foreignKey->table_name), collect())->contains(function (stdClass $index) use ($prefixes, $predicates): bool {
                if ($index->predicate !== null && ! in_array($index->predicate, $predicates, true)) {
                    return false;
                }

                $indexColumns = Narrow::strings(json_decode(Narrow::string($index->columns), true));

                foreach ($prefixes as $prefix) {
                    $leading = array_slice($indexColumns, 0, count($prefix));

                    if (array_diff($prefix, $leading) === [] && count($leading) === count($prefix)) {
                        return true;
                    }
                }

                return false;
            });
        })
        ->map(fn (stdClass $foreignKey): string => Narrow::string($foreignKey->table_name) . '.' . Narrow::string($foreignKey->name))
        ->sort()
        ->values()
        ->all();

    expect($unindexed)->toBe([]);
});
