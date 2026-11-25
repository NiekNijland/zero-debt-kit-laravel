<?php

declare(strict_types=1);

uses(Tests\Concerns\GuardsAgainstDatabaseAccess::class);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

it('enforces the morph map', function (): void {
    expect(Relation::requiresMorphMap())->toBeTrue();
});

it('maps exactly the models that a morph relation stores, each under its snake_case name', function (): void {
    /** @var array<class-string, string> $classMap */
    $classMap = require base_path('vendor/composer/autoload_classmap.php');
    $morphTraits = [HasRoles::class, Notifiable::class];

    $morphModels = array_values(array_filter(
        array_keys($classMap),
        static fn (string $class): bool => str_starts_with($class, 'App\\Models\\')
            && is_subclass_of($class, Model::class)
            && array_intersect($morphTraits, class_uses_recursive($class)) !== [],
    ));

    expect($morphModels)->not->toBeEmpty()
        ->and(array_values(Relation::morphMap()))->toEqualCanonicalizing($morphModels);

    foreach (Relation::morphMap() as $alias => $class) {
        expect($alias)->toBe(Str::snake(class_basename($class)))
            ->and(new $class()->getMorphClass())->toBe($alias);
    }
});
