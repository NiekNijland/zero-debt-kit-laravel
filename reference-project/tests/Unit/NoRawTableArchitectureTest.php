<?php

declare(strict_types=1);

use Tests\Support\Unit\NoRawTableArchitectureHelpers;

uses()->group('arch');

it('reaches the database through models: raw tables and raw SQL live in database/ and in the PostgreSQL advisory lock', function (): void {
    expect(NoRawTableArchitectureHelpers::offenders())->toBe([]);
});

it('recognizes every way to reach a table or a statement without a model', function (): void {
    $lines = NoRawTableArchitectureHelpers::rawCallLines(...);

    expect($lines("<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::table('category_product')->pluck('product_id');"))->toBe([3])
        ->and($lines("<?php\n\\DB::select('select 1');"))->toBe([2])
        ->and($lines("<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::statement('vacuum'); DB::raw('1'); DB::scalar('select 1'); DB::unprepared('x');"))->toHaveCount(4)
        ->and($lines("<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::connection('pgsql')->table('failed_jobs')->count();"))->toBe([3])
        ->and($lines("<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::transaction(fn () => 1); DB::afterCommit(fn () => 1); DB::query()->fromSub(\$q, 'sub'); DB::connection()->getPdo();"))->toBe([])
        ->and($lines("<?php\n\$this->table(['Field'], \$rows); CategoryProduct::query()->toBase()->select('product_id');"))->toBe([]);
});
