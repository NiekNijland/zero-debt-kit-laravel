<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestHelperMirrorGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('reports helpers and scenarios in the mirrored tree that no test at the mirrored path owns', function (): void {
    $class = static fn (string $namespace, string $name, string $body = ''): string => "<?php\nnamespace Tests\\Support\\" . $namespace . ";\nfinal class " . $name . ' { ' . $body . " }\n";
    $root = SourceSandbox::create([
        'tests/Feature/Http/Controllers/Order/ShowControllerTest.php' => "<?php\n",
        'tests/Feature/Http/Controllers/Order/OrderFlowTest.php' => "<?php\n",
        'tests/Unit/ArchHttpTest.php' => "<?php\n",
        'tests/Support/Feature/Http/Controllers/Order/ShowControllerHelpers.php' => $class('Feature\Http\Controllers\Order', 'ShowControllerHelpers', 'public static function scenario(): ShowReadyScenario { return new ShowReadyScenario(); }'),
        'tests/Support/Feature/Http/Controllers/Order/ShowControllerScenario.php' => $class('Feature\Http\Controllers\Order', 'ShowControllerScenario'),
        'tests/Support/Feature/Http/Controllers/Order/ShowReadyScenario.php' => $class('Feature\Http\Controllers\Order', 'ShowReadyScenario'),
        'tests/Support/Feature/Http/Controllers/Order/FakeCarrierClient.php' => $class('Feature\Http\Controllers\Order', 'FakeCarrierClient'),
        'tests/Support/Feature/Http/Controllers/Order/OrderControllersHelpers.php' => $class('Feature\Http\Controllers\Order', 'OrderControllersHelpers', 'public static function scenario(): OrphanScenario { return new OrphanScenario(); }'),
        'tests/Support/Feature/Http/Controllers/Order/OrphanScenario.php' => $class('Feature\Http\Controllers\Order', 'OrphanScenario'),
        'tests/Support/Feature/Http/Controllers/SharedScenario.php' => $class('Feature\Http\Controllers', 'SharedScenario'),
        'tests/Support/Feature/Http/Controllers/Order/OrderFlowHelpers.php' => $class('Feature\Http\Controllers', 'OrderFlowHelpers'),
        'tests/Support/Unit/ArchHelpers.php' => $class('Unit', 'ArchHelpers'),
        'tests/Support/Unit/ArchHttpHelpers.php' => $class('Unit', 'ArchHttpHelpers'),
        'tests/Support/Architecture/OrphanHelpers.php' => $class('Architecture', 'OrphanHelpers'),
        'tests/Support/Shops.php' => "<?php\nnamespace Tests\\Support;\nfinal class Shops { }\n",
        'tests/Support/SharedHelpers.php' => "<?php\nnamespace Tests\\Support;\nfinal class SharedHelpers { }\n",
    ]);

    try {
        $violations = new TestHelperMirrorGuard(new PhpFileInspector(), $root)->violations();

        expect($violations)->toHaveCount(5)
            ->and($violations[0])->toStartWith('tests/Support/Feature/Http/Controllers/Order/OrderControllersHelpers.php: there is no tests/Feature/Http/Controllers/Order/OrderControllersTest.php')
            ->and($violations[0])->toContain('OrderControllersTest.php with OrderControllersHelpers.php', 'a themed class directly in tests/Support')
            ->and($violations[1])->toBe('tests/Support/Feature/Http/Controllers/Order/OrderFlowHelpers.php: the file does not declare Tests\Support\Feature\Http\Controllers\Order\OrderFlowHelpers: the class and its namespace follow the path')
            ->and($violations[2])->toStartWith('tests/Support/Feature/Http/Controllers/Order/OrphanScenario.php: there is no tests/Feature/Http/Controllers/Order/OrphanTest.php')
            ->and($violations[3])->toStartWith('tests/Support/Feature/Http/Controllers/SharedScenario.php: there is no tests/Feature/Http/Controllers/SharedTest.php')
            ->and($violations[4])->toStartWith('tests/Support/Unit/ArchHelpers.php: there is no tests/Unit/ArchTest.php');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('gives every helper and scenario in the mirrored tree of tests/Support a test at the mirrored path', function (): void {
    expect(new TestHelperMirrorGuard(new PhpFileInspector(), dirname(__DIR__, 2))->violations())->toBe([]);
});
