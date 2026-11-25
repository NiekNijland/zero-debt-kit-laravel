<?php

declare(strict_types=1);

use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Globs;

uses()->group('arch');

test('migrations never use the live permission or role classes', function (): void {
    $offenders = collect(Globs::of(dirname(__DIR__, 2) . '/database/migrations/*.php'))
        ->filter(fn (string $path): bool => preg_match(
            '/App\\\\Modules\\\\Roles\\\\Enums\\\\(Permission|Role)\b|(?<![\w\\\\])(Permission|Role)::/',
            (string) file_get_contents($path),
        ) === 1)
        ->map(fn (string $path): string => basename($path))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('migrations never reference application classes', function (): void {
    $offenders = collect(Globs::of(dirname(__DIR__, 2) . '/database/migrations/*.php'))
        ->filter(fn (string $path): bool => preg_match('/(?<![A-Za-z0-9_])App\\\\/', (string) file_get_contents($path)) === 1)
        ->map(fn (string $path): string => basename($path))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('config is read through the typed accessors', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $untypedReads = static fn (string $source): bool => ArchChecks::readsConfigUntyped($inspector->calls($source));
    $offenders = [];

    foreach (['app', 'routes', 'bootstrap'] as $directory) {
        foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
            if (ArchChecks::readsConfigUntyped($inspector->callsIn($file))) {
                $offenders[] = substr($file, strlen($root) + 1);
            }
        }
    }

    expect($offenders)->toBe([])
        ->and($untypedReads('<?php $a = config(\'app.name\');'))->toBeTrue()
        ->and($untypedReads('<?php $a = \config(\'app.name\', \'x\');'))->toBeTrue()
        ->and($untypedReads('<?php $a = config()->string(\'app.name\');'))->toBeTrue()
        ->and($untypedReads('<?php $a = Config::get(\'app.name\');'))->toBeTrue()
        ->and($untypedReads('<?php $a = \Illuminate\Support\Facades\Config::get(\'app.name\');'))->toBeTrue()
        ->and($untypedReads('<?php $a = $this->config->get(\'app.name\');'))->toBeTrue()
        ->and($untypedReads('<?php $a = Config::string(\'app.name\');'))->toBeFalse()
        ->and($untypedReads('<?php Config::set(\'app.name\', \'x\'); $a = $this->config->string(\'app.name\');'))->toBeFalse()
        ->and($untypedReads('<?php $a = $config->get(\'app.name\'); // config(\'app.name\')'))->toBeFalse();
});
