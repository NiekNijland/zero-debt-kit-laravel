<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Narrow;

it('puts a foreign key on every tenant_id column', function (): void {
    $unconstrained = collect(DB::select(<<<'SQL'
        SELECT c.table_name
        FROM information_schema.columns c
        JOIN information_schema.tables t
          ON t.table_schema = c.table_schema AND t.table_name = c.table_name AND t.table_type = 'BASE TABLE'
        WHERE c.table_schema = 'public'
          AND c.column_name = 'tenant_id'
          AND c.data_type IN ('bigint', 'integer')
          AND NOT EXISTS (
              SELECT 1
              FROM pg_constraint k
              JOIN pg_attribute a ON a.attrelid = k.conrelid AND a.attnum = ANY (k.conkey)
              WHERE k.contype = 'f'
                AND k.conrelid = format('public.%I', c.table_name)::regclass
                AND a.attname = 'tenant_id'
          )
        ORDER BY c.table_name
        SQL))->pluck('table_name')->all();

    expect($unconstrained)->toBe([]);
});

it('validates every foreign key on tenant_id', function (): void {
    $unvalidated = collect(array_map(Narrow::row(...), DB::select(<<<'SQL'
        SELECT k.conrelid::regclass::text AS table_name, k.conname AS name
        FROM pg_constraint k
        JOIN pg_attribute a ON a.attrelid = k.conrelid AND a.attnum = ANY (k.conkey)
        WHERE k.contype = 'f'
          AND k.connamespace = 'public'::regnamespace
          AND a.attname = 'tenant_id'
          AND NOT k.convalidated
        ORDER BY 1, 2
        SQL)))->map(fn (stdClass $row): string => Narrow::string($row->table_name) . '.' . Narrow::string($row->name))->all();

    expect($unvalidated)->toBe([]);
});

it('lets a foreign key of tenant_id alone point only at tenants and follow their deletion', function (): void {
    $wrong = collect(array_map(Narrow::row(...), DB::select(<<<'SQL'
        SELECT k.conrelid::regclass::text AS table_name, k.conname AS name
        FROM pg_constraint k
        JOIN pg_attribute a ON a.attrelid = k.conrelid AND a.attnum = k.conkey[1]
        WHERE k.contype = 'f'
          AND k.connamespace = 'public'::regnamespace
          AND cardinality(k.conkey) = 1
          AND a.attname = 'tenant_id'
          AND (k.confrelid <> 'public.tenants'::regclass OR k.confdeltype <> 'c')
        ORDER BY 1, 2
        SQL)))->map(fn (stdClass $row): string => Narrow::string($row->table_name) . '.' . Narrow::string($row->name))->all();

    expect($wrong)->toBe([]);
});

it('rejects a row for a tenant that does not exist', function (): void {
    $this->createTenantAndMakeCurrent();

    expect(fn () => DB::table('suppliers')->insert([
        'tenant_id' => 999_999,
        'name' => 'Example supplier',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
