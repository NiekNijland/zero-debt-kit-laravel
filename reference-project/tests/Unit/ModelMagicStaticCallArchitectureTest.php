<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Tests\Support\Architecture\ModelMagicStaticCallGuard;
use Tests\Support\Architecture\PhpFileInspector;

uses()->group('arch');

it('detects a magic static call on a model and accepts the near misses (examples that name application models: reasoned, not run without the application)', function (): void {
    $guard = new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app']);
    $source = '<?php namespace App\Models\Tenant; use App\Models\Tenant\User as Person; final class Probe extends Order { public function a() { return [Person::where("a", 1), User::find(1), parent::where("a", 1), self::query()->where("a", 1), User::query()->where("a", 1), User::withTrashed(), User::factory(), parent::boot()]; } }';
    $violations = $guard->inSource($source);

    expect($violations)->toHaveCount(4)
        ->and($violations[0])->toContain('where() is a magic static call on a model: start the query at User::query()');
});

it('starts every query at Model::query()', function (): void {
    expect(new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database'])->violations())->toBe([]);
});
