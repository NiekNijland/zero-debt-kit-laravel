<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Tests\Support\Architecture\BootstrapRegistrationGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('registrations that fail', [
    'a listener imported in a provider' => ['provider', "<?php\nuse App\\Modules\\Orders\\Listeners\\SendMail;\n", 'listeners are found by discovery'],
    'a bootstrap file without discovery' => ['bootstrap', "<?php\n\$middleware->group('tenant', [A::class, B::class]);\n", 'the discovery of listeners is gone'],
    'the pair named twice' => ['bootstrap', "<?php\n->withEvents(discover: [x])\n\$middleware->group('tenant', [A::class, B::class]);\n\$middleware->append(A::class);\n", 'A::class is named 2 times'],
    'the pair in no group' => ['bootstrap', "<?php\n->withEvents(discover: [x])\n\$middleware->append(A::class);\n\$middleware->append(B::class);\n", 'the group tenant is gone'],
]);

it('detects a listener registered by hand and a tenant middleware pair that left its group', function (string $kind, string $source, string $words): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', '->withEvents(discover: [x])', ['A::class', 'B::class'], 'tenant');
    $violations = $kind === 'provider' ? $guard->providerInSource($source) : $guard->bootstrapInSource($source);

    expect($violations)->not->toBe([])
        ->and(implode("\n", $violations))->toContain($words);
})->with('registrations that fail');

it('accepts a provider that imports no listener and a bootstrap file with discovery and the pair once in one group', function (): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', '->withEvents(discover: [x])', ['A::class', 'B::class'], 'tenant');

    expect($guard->providerInSource("<?php\nuse App\\Modules\\Orders\\Models\\Order;\nuse App\\Listeners\\Other;\n"))->toBe([])
        ->and($guard->bootstrapInSource("<?php\n->withEvents(discover: [x])\n\$middleware->group('tenant', [A::class, B::class]);\n"))->toBe([]);
});

it('reads the providers and the bootstrap file below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['providers/P.php' => "<?php\nuse App\\Modules\\Orders\\Listeners\\L;\n", 'boot/app.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new BootstrapRegistrationGuard($root, 'providers', 'boot/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', 'D', ['A::class'], 'tenant')->violations();
        $missing = new BootstrapRegistrationGuard($root, 'providers', 'boot/none.php', 'X', 'D', [], 'tenant')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations[0])->toStartWith('providers/P.php: a provider imports a listener of a module')
        ->and($violations)->toContain('boot/app.php: the discovery of listeners is gone: add D, or a listener is never registered')
        ->and($missing[count($missing) - 1])->toContain('the bootstrap file does not exist');
});

it('finds listeners by discovery and keeps the tenant middleware pair in one group', function (): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', "->withEvents(discover: [app_path('Modules/*/Listeners')])", ['AbortIfNotTenantHost::class', 'NeedsTenant::class'], 'tenant');

    expect($guard->violations())->toBe([]);
});
