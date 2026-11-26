<?php

declare(strict_types=1);

use Tests\Support\Architecture\ConfigAccessGuard;
use Tests\Support\Architecture\MigrationClassGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('migrations that name a class', [
    'the live role enum' => ["<?php\nuse App\\Modules\\Roles\\Enums\\Role;\n", 'the live permission or role class'],
    'a live permission case' => ["<?php\n\$a = Permission::VIEW_ORDERS;\n", 'write the name as a string'],
    'an application class' => ["<?php\nuse App\\Models\\Order;\n", 'names an application class'],
    'an application class in a call' => ["<?php\n\$a = \\App\\Support\\Json::decode(\$x);\n", 'names an application class'],
]);

it('detects a migration that names an application class', function (string $source, string $words): void {
    $messages = new MigrationClassGuard(dirname(__DIR__, 2), 'database/migrations', 'App\Modules\Roles\Enums', 'App\\')->inSource($source);

    expect($messages)->not->toBe([])
        ->and($messages[0])->toContain($words);
})->with('migrations that name a class');

dataset('migrations that name no class', [
    'a plain migration' => ["<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nreturn new class extends Migration {};\n"],
    'a word that ends like the namespace' => ["<?php\n\$a = 'MyApp\\\\Models';\n"],
    'a role as a string' => ["<?php\nDB::table('roles')->insert(['name' => 'admin']);\n"],
    'a class that ends in Role' => ["<?php\n\$a = SomeRole::class;\n"],
]);

it('accepts a migration that names no application class', function (string $source): void {
    expect(new MigrationClassGuard(dirname(__DIR__, 2), 'database/migrations', 'App\Modules\Roles\Enums', 'App\\')->inSource($source))->toBe([]);
})->with('migrations that name no class');

it('names the file of a migration that names a class, in a checkout under a dot directory, and fails on an empty folder', function (): void {
    $root = SourceSandbox::create([
        'database/migrations/2024_01_01_000000_a.php' => "<?php\nuse App\\Models\\Order;\n",
        'database/migrations/2024_01_02_000000_b.php' => "<?php\n",
        'empty/.gitkeep' => '',
    ], prefix: '.checkout-');

    try {
        $violations = new MigrationClassGuard($root, 'database/migrations', 'App\Modules\Roles\Enums', 'App\\')->violations();
        $empty = new MigrationClassGuard($root, 'empty', 'App\Modules\Roles\Enums', 'App\\')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('database/migrations/2024_01_01_000000_a.php: a migration names an application class')
        ->and($empty)->toHaveCount(1)
        ->and($empty[0])->toContain('no migration was read');
});

it('keeps application classes out of the migrations', function (): void {
    expect(new MigrationClassGuard(dirname(__DIR__, 2), 'database/migrations', 'App\Modules\Roles\Enums', 'App\\')->violations())->toBe([]);
});

dataset('untyped config reads', [
    'the helper' => ['<?php $a = config(\'app.name\');'],
    'the helper with a default and a backslash' => ['<?php $a = \config(\'app.name\', \'x\');'],
    'the helper without a key' => ['<?php $a = config()->string(\'app.name\');'],
    'the facade get' => ['<?php $a = Config::get(\'app.name\');'],
    'the qualified facade get' => ['<?php $a = \Illuminate\Support\Facades\Config::get(\'app.name\');'],
    'the injected repository get' => ['<?php $a = $this->config->get(\'app.name\');'],
]);

it('detects a config value that is read without a type', function (string $source): void {
    $messages = new ConfigAccessGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], Illuminate\Support\Facades\Config::class)->inSource($source);

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('through a typed accessor');
})->with('untyped config reads');

dataset('typed config reads and near misses', [
    'the typed facade accessor' => ['<?php $a = Config::string(\'app.name\');'],
    'a set and a typed read on the repository' => ['<?php Config::set(\'app.name\', \'x\'); $a = $this->config->string(\'app.name\');'],
    'a get on another receiver and a call in a comment' => ['<?php $a = $config->get(\'app.name\'); // config(\'app.name\')'],
    'a function with the same name in a namespace' => ['<?php namespace X; $a = \X\config(\'app.name\');'],
]);

it('accepts a typed config read and the near misses', function (string $source): void {
    expect(new ConfigAccessGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], Illuminate\Support\Facades\Config::class)->inSource($source))->toBe([]);
})->with('typed config reads and near misses');

it('reads config through the typed accessors', function (): void {
    expect(new ConfigAccessGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'routes', 'bootstrap'], Illuminate\Support\Facades\Config::class)->violations())->toBe([]);
});
