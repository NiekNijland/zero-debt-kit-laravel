<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

dataset('retired column types', [
    'json, write jsonb' => ['json'],
    'timestamp, write timestamptz' => ['timestamp without time zone'],
]);

it('has no column of a retired type', function (string $type): void {
    $columns = DB::select(<<<'SQL'
        SELECT c.table_name || '.' || c.column_name AS name
        FROM information_schema.columns c
        JOIN information_schema.tables t
          ON t.table_schema = c.table_schema AND t.table_name = c.table_name AND t.table_type = 'BASE TABLE'
        WHERE c.table_schema = 'public' AND c.data_type = ?
        ORDER BY name
        SQL, [$type]);

    expect(array_column($columns, 'name'))->toBe([]);
})->with('retired column types');
