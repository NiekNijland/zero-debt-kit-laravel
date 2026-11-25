<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RegistryGuard;

it('detects an implementer the registry misses, an entry that is no implementer and an entry with another name', function (): void {
    $violations = RegistryGuard::mismatches(
        ['App\\A\\Services\\PostShippingService', 'App\\B\\Services\\CourierShippingService', 'App\\C\\Services\\PickupPoint'],
        ['App\\A\\Services\\PostShippingService', 'App\\C\\Services\\PickupPoint', 'App\\D\\Services\\FreightShippingService', 'App\\A\\Services\\PostShippingService'],
        'App\\Contracts\\Missing',
    );

    expect($violations)->toBe([
        'App\\B\\Services\\CourierShippingService implements ShippingMethod but is not in ShippingMethods::ALL, so the checkout never offers it: add it to that list',
        'App\\C\\Services\\PickupPoint is in ShippingMethods::ALL but is not named XShippingService: rename it',
        'App\\D\\Services\\FreightShippingService is in ShippingMethods::ALL but does not implement ShippingMethod: implement it in a service named XShippingService, or take it off the list',
        'App\\A\\Services\\PostShippingService is in ShippingMethods::ALL twice: list it once',
    ]);
});

it('accepts a registry that names every implementer once', function (): void {
    $methods = ['App\\A\\Services\\PostShippingService', 'App\\B\\Services\\CourierShippingService'];

    expect(RegistryGuard::mismatches($methods, $methods, 'App\\Contracts\\Missing'))->toBe([]);
});

it('lists every shipping method of app in ShippingMethods::ALL, and nothing else', function (): void {
    expect(new RegistryGuard(new PhpFileInspector(), dirname(__DIR__, 2))->violations())->toBe([]);
});
