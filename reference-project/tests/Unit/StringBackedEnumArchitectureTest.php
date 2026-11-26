<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\StringBackedEnumGuard;

uses()->group('arch');

it('detects an enum that is not backed by a string', function (string $source, string $reason): void {
    $violations = new StringBackedEnumGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('enum X: string');
})->with([
    'a pure enum' => ["<?php\nnamespace App;\nenum Status { case PLACED; case SHIPPED; }\n", 'App\\Status has no backing type'],
    'an integer enum' => ["<?php\nnamespace App;\nenum Level: int { case LOW = 1; }\n", 'App\\Level is backed by int'],
]);

it('accepts an enum backed by a string and a class that is only called Enum', function (): void {
    $guard = new StringBackedEnumGuard(new PhpFileInspector(), sys_get_temp_dir(), 'app');

    expect($guard->inSource("<?php\nnamespace App;\nenum Status: string { case PLACED = 'placed'; }\nfinal class Enum {}\n"))->toBe([]);
});

it('backs every enum in app by a string', function (): void {
    expect(new StringBackedEnumGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
