<?php

declare(strict_types=1);

use App\Http\Controllers\Order\IndexController;
use App\Http\Controllers\Order\ShowController;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\ObjectLevelAccessHelpers;

it('refuses every route with a record id for a record that belongs to someone else', function (): void {
    // The facts of this project: the model and a factory of a record of another tenant, and the statuses that count as a refusal (a tenant scope answers 404,
    // a policy 403). The user holds every permission the routes ask for, so only the record can be the reason for the refusal. Reasoned, not run without
    // the application: the factory states are this project's.
    $tenant = $this->createTenantAndMakeCurrent();
    $other = Tenant::factory()->create();
    $factories = [Order::class => static fn (): Order => Order::factory()->for($other)->create()];
    $refusals = [403, 404];
    $entryPoints = ObjectLevelAccessHelpers::entryPoints(['app', 'routes']);
    $unanswered = [];
    $statuses = [];

    $this->actingAs(User::factory()->for($tenant)->withEveryPermission()->create());

    foreach ($entryPoints as $route) {
        $records = ObjectLevelAccessHelpers::foreignRecords($route, $factories);
        $name = ApplicationRoutes::identifier($route);

        if (is_string($records)) {
            $unanswered[] = $name . ' ' . $records;

            continue;
        }

        $statuses[$name] = $this->json(ObjectLevelAccessHelpers::method($route), route($name, $records))->getStatusCode();
    }

    expect($entryPoints)->not->toBe([])
        ->and($unanswered)->toBe([])
        ->and(array_filter($statuses, static fn (int $status): bool => ! in_array($status, $refusals, true)))->toBe([]);
});

it('finds the routes that take a record id, and fails one whose model has no factory', function (): void {
    $show = ApplicationRoutes::forController(['GET'], 'orders/{order}', ShowController::class);
    $index = ApplicationRoutes::forController(['GET'], 'orders', IndexController::class);

    expect(ObjectLevelAccessHelpers::recordParameters($show))->toBe(['order' => Order::class])
        ->and(ObjectLevelAccessHelpers::recordParameters($index))->toBe([])
        ->and(ObjectLevelAccessHelpers::foreignRecords($show, []))->toBe('has no factory for App\Models\Tenant\Order in the map of foreign records')
        ->and(ObjectLevelAccessHelpers::method($show))->toBe('GET')
        ->and(ObjectLevelAccessHelpers::method(ApplicationRoutes::forController(['PUT'], 'orders/{order}', ShowController::class)))->toBe('PUT');
});
