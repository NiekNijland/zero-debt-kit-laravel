<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RequestValidationGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('requests validated by hand', [
    'validate on the request' => ['<?php $request->validate([]);', 1],
    'validateWithBag and the helper' => ['<?php $request->validateWithBag(\'x\', []); request()->validate([]);', 2],
    'the validator facade' => ['<?php Validator::make($a, $b)->validate(); \Illuminate\Support\Facades\Validator::validate($a, $b);', 2],
]);

it('detects a request that is validated outside a form request', function (string $source, int $count): void {
    $violations = new RequestValidationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [])->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('write the rules in a form request');
})->with('requests validated by hand');

it('accepts a validate call that is not on the request or the validator facade', function (): void {
    $guard = new RequestValidationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', []);

    expect($guard->inSource('<?php $a = Auth::guard(\'web\')->validate($credentials); $b = $rule->validate($a, $b, $fail); $this->validate($input);'))->toBe([]);
});

it('skips the form requests and reads a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Http/Requests/A.php' => "<?php\n\$request->validate([]);\n", 'app/Other.php' => "<?php\n\$request->validate([]);\n"], prefix: '.checkout-');

    try {
        $violations = new RequestValidationGuard(new PhpFileInspector(), $root, 'app', ['app/Http/Requests/'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Other.php: line 2: a request is validated by hand');
});

it('validates a request in a form request and nowhere else', function (): void {
    expect(new RequestValidationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', ['app/Http/Requests/'])->violations())->toBe([]);
});
