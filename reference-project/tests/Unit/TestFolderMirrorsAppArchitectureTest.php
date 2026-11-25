<?php

declare(strict_types=1);

use Tests\Support\Unit\TestFolderMirrorsAppArchitectureHelpers;

it('reports a test that sits away from the class it covers or from any folder of app', function (): void {
    $root = TestFolderMirrorsAppArchitectureHelpers::sandbox([
        'app/Http/Controllers/Order/ShowController.php' => "<?php\n",
        'app/Http/Controllers/Order/UpdateController.php' => "<?php\n",
        'app/Modules/Orders/Actions/CancelOrder.php' => "<?php\n",
        'app/Modules/Orders/Support/OrderNumber.php' => "<?php\n",
        'app/Models/Tenant/Order.php' => "<?php\n",
        'tests/Feature/Http/Controllers/Order/ShowControllerTest.php' => "<?php\n",
        'tests/Feature/Http/Controllers/Order/OrderFlowTest.php' => "<?php\n",
        'tests/Feature/Modules/Orders/Actions/CancelOrderTest.php' => "<?php\n",
        'tests/Feature/Modules/Orders/CancelOrderTest.php' => "<?php\n",
        'tests/Feature/Models/Tenant/OrderTest.php' => "<?php\n",
        'tests/Feature/Models/OrderTest.php' => "<?php\n",
        'tests/Feature/Orders/FlowTest.php' => "<?php\n",
        'tests/Feature/Http/Controllers/Order/Nested/FlowTest.php' => "<?php\n",
        'tests/Feature/Database/Migrations/AddOrdersMigrationTest.php' => "<?php\n",
        'tests/Feature/Config/QueueConfigTest.php' => "<?php\n",
        'tests/Feature/Routing/RouteFilesTest.php' => "<?php\n",
        'tests/Feature/ExampleTest.php' => "<?php\n",
        'tests/Feature/UpdateControllerTest.php' => "<?php\n",
        'tests/Unit/ArchHttpTest.php' => "<?php\n",
        'tests/Unit/Modules/Orders/Support/OrderNumberTest.php' => "<?php\n",
        'tests/Unit/Modules/Orders/Support/Helper.php' => "<?php\n",
        'tests/Integration/Checkout/CheckoutEndToEndTest.php' => "<?php\n",
        'tests/Concerns/Isolation.php' => "<?php\n",
    ]);

    try {
        expect(TestFolderMirrorsAppArchitectureHelpers::violations($root))->toBe([
            'tests/Feature/Orders/FlowTest.php: app/Orders does not exist, the test belongs in the folder of the code it covers',
            'tests/Feature/Http/Controllers/Order/Nested/FlowTest.php: app/Http/Controllers/Order/Nested does not exist, the test belongs in the folder of the code it covers',
            'tests/Feature/Modules/Orders/CancelOrderTest.php: the class CancelOrder lives in app/Modules/Orders/Actions, the test belongs in the same folder of tests/Feature',
            'tests/Feature/Models/OrderTest.php: the class Order lives in app/Models/Tenant, the test belongs in the same folder of tests/Feature',
            'tests/Feature/UpdateControllerTest.php: the class UpdateController lives in app/Http/Controllers/Order, the test belongs in the same folder of tests/Feature',
            'tests/Integration/Checkout/CheckoutEndToEndTest.php: app/Checkout does not exist, the test belongs in the folder of the code it covers',
        ]);
    } finally {
        TestFolderMirrorsAppArchitectureHelpers::remove($root);
    }
});

it('lets every test sit in the folder of the class it covers, or in a folder of app', function (): void {
    expect(TestFolderMirrorsAppArchitectureHelpers::violations(dirname(__DIR__, 2)))->toBe([]);
});
