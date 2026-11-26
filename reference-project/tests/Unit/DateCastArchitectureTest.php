<?php

declare(strict_types=1);

use Tests\Support\Architecture\DateCastGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('date casts', [
    'datetime cast' => ['<?php return [\'at\' => \'datetime\'];', true],
    'date cast with a format' => ['<?php return [\'at\' => \'date:Y-m-d\'];', true],
    'immutable cast' => ['<?php return [\'at\' => \'immutable_datetime\'];', false],
    'immutable date with a format' => ['<?php return [\'at\' => \'immutable_date:Y-m-d\'];', false],
    'a rule list that names date' => ['<?php return [\'required\', \'date\'];', false],
    'a rule string' => ['<?php return [\'at\' => \'date|required\'];', false],
]);

it('detects a mutable date cast and accepts the immutable ones and a validation rule', function (string $source, bool $mutable): void {
    $violations = new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source);

    expect($violations !== [])->toBe($mutable)
        ->and($mutable ? $violations[0] : '')->toContain($mutable ? 'cast it as immutable_datetime or immutable_date' : '');
})->with('date casts');

it('reads the model folder below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Models/A.php' => "<?php\nreturn ['at' => 'datetime'];\n", 'app/Models/B.php' => "<?php\nreturn ['at' => 'immutable_datetime'];\n"], prefix: '.checkout-');

    try {
        $violations = new DateCastGuard(new PhpFileInspector(), $root, 'app/Models')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Models/A.php: a date is cast as datetime or date');
});

it('casts every model date as immutable', function (): void {
    expect(new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->violations())->toBe([]);
});
