<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\UniqueJobGuard;

dataset('unique jobs without their own key', [
    'ShouldBeUnique' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUnique;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ExportJob implements ShouldBeUnique, ShouldQueue { public function handle(): void {} }\n", 'App\\ExportJob implements ShouldBeUnique'],
    'ShouldBeUniqueUntilProcessing' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUniqueUntilProcessing;\nfinal class FulfillJob implements ShouldBeUniqueUntilProcessing { public int \$uniqueFor = 60; }\n", 'App\\FulfillJob implements ShouldBeUniqueUntilProcessing'],
    'a fully qualified contract' => ["<?php\nnamespace App;\nabstract class BaseJob implements \\Illuminate\\Contracts\\Queue\\ShouldBeUnique {}\n", 'App\\BaseJob implements ShouldBeUnique'],
    'an aliased contract' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUnique as Unique;\nfinal class AliasJob implements Unique { public string \$uniqueId = 'x'; }\n", 'App\\AliasJob implements ShouldBeUnique'],
]);

it('detects a unique job that does not declare uniqueId()', function (string $source, string $reason): void {
    $violations = new UniqueJobGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('declare public function uniqueId(): string');
})->with('unique jobs without their own key');

it('accepts a unique job with uniqueId(), a job that is not unique and a class named after the contract', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Contracts\Queue\ShouldBeUnique;
        use Illuminate\Contracts\Queue\ShouldQueue;
        final class ExportJob implements ShouldBeUnique, ShouldQueue { public function uniqueId(): string { return 'x'; } }
        final class PlainJob implements ShouldQueue { public function handle(): void {} }
        final class OtherJob implements \App\Contracts\ShouldBeUnique {}
        PHP;

    expect(new UniqueJobGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source))->toBe([]);
});

it('gives every unique job in app its own uniqueId()', function (): void {
    expect(new UniqueJobGuard(new PhpFileInspector(), dirname(__DIR__, 2))->violations())->toBe([]);
});
