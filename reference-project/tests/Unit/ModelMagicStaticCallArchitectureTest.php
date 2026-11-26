<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Tests\Support\Architecture\ModelMagicStaticCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

// The examples use the framework's own user model, which every Laravel application has: the guard loads the class a call names.
dataset('magic static calls on a model', [
    'where' => ['<?php use Illuminate\Foundation\Auth\User; return User::where("a", 1);', 'where() is a magic static call on a model: start the query at User::query()'],
    'find' => ['<?php use Illuminate\Foundation\Auth\User; return User::find(1);', 'find() is a magic static call on a model: start the query at User::query()'],
    'where through an alias' => ['<?php use Illuminate\Foundation\Auth\User as Person; return Person::where("a", 1);', 'where() is a magic static call on a model: start the query at User::query()'],
    // The class names keep their leading backslash: a formatter turns the plain spelling into `User::class . '::where(...)'`, which has none and resolves inside the example's namespace.
    'where by the fully qualified name' => ['<?php return \\' . User::class . '::where("a", 1);', 'where() is a magic static call on a model'],
    'where on the parent of a model' => ['<?php namespace App; final class Probe extends \\' . User::class . ' { public function a() { return parent::where("a", 1); } }', 'where() is a magic static call on a model'],
]);

it('detects a magic static call on a model', function (string $source, string $words): void {
    $violations = new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('magic static calls on a model');

dataset('static calls that are no magic call on a model', [
    'a query started at query()' => ['<?php use Illuminate\Foundation\Auth\User; return User::query()->where("a", 1);'],
    'a static method the model declares' => ['<?php use Illuminate\Foundation\Auth\User; return User::unguard();'],
    'a call on a class that is no model' => ['<?php use Illuminate\Support\Str; return Str::where("a", 1);'],
    'a call on self in a class that is no model' => ['<?php namespace App; final class Probe { public function a() { return self::where("a", 1); } }'],
    'a class that does not exist' => ['<?php return App\Missing\Thing::where("a", 1);'],
]);

it('accepts a query that starts at query(), a static method of the model and a class that is no model', function (string $source): void {
    expect((new ModelMagicStaticCallGuard(new PhpFileInspector(), sys_get_temp_dir(), ['app']))->inSource($source))->toBe([]);
})->with('static calls that are no magic call on a model');

it('reads the folders below a checkout under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Bad.php' => "<?php\nuse Illuminate\\Foundation\\Auth\\User;\nreturn User::where('a', 1);\n",
        'app/Good.php' => "<?php\nuse Illuminate\\Foundation\\Auth\\User;\nreturn User::query()->where('a', 1);\n",
    ], prefix: '.checkout-');

    try {
        $violations = new ModelMagicStaticCallGuard(new PhpFileInspector(), $root, ['app'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php: line 3: where() is a magic static call on a model');
});

it('starts every query at Model::query()', function (): void {
    expect(new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database'])->violations())->toBe([]);
});
