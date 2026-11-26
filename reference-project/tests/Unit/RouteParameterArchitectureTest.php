<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RouteParameterGuard;

uses()->group('arch');

it('detects a route parameter that is not camelCase', function (string $source, string $reason): void {
    $violations = new RouteParameterGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'routes')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason);
})->with([
    'snake case' => ["<?php\nRoute::get('/orders/{order}/lines/{order_line}', Order\\Lines\\ShowController::class);\n", '2: the route parameter {order_line} is not camelCase: name it {orderLine}'],
    'kebab case with a binding field' => ["<?php\nRoute::delete('/tax-rates/{tax-rate:uuid}', TaxRates\\DestroyController::class);\n", '2: the route parameter {tax-rate} is not camelCase: name it {taxRate}'],
    'an optional parameter in a prefix' => ["<?php\nRoute::prefix('{Tenant?}/settings')->group(function (): void {});\n", '2: the route parameter {Tenant} is not camelCase: name it {tenant}'],
    'a broadcast channel' => ["<?php\nBroadcast::channel('stock-level.{stock-level}', static fn (): bool => true);\n", '2: the route parameter {stock-level} is not camelCase: name it {stockLevel}'],
]);

it('accepts camelCase parameters, a binding field and a pattern that holds braces', function (): void {
    $guard = new RouteParameterGuard(new PhpFileInspector(), sys_get_temp_dir(), 'routes');
    $accepted = "<?php\nRoute::get('/orders/{order}/lines/{line:uuid}/{variantId?}', Order\\ShowController::class)->where('line', '[a-z]{2,8}');\nRoute::get('shipments/track/{code}', Shipments\\ShowController::class);\n";

    expect($guard->inSource($accepted))->toBe([]);
});

it('names every route parameter in camelCase', function (): void {
    expect(new RouteParameterGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'routes')->violations())->toBe([]);
});
