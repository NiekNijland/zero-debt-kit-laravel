<?php

declare(strict_types=1);

use Tests\Support\Unit\ThinControllersArchitectureHelpers;

uses()->group('arch');

dataset('controller business logic', [
    'transaction' => ["<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::transaction(fn () => null);\n"],
    'fully qualified DB facade' => ["<?php\n\\Illuminate\\Support\\Facades\\DB::table('orders')->get();\n"],
    'lock' => ["<?php\n\$order = Order::query()->lockForUpdate()->find(1);\n"],
    'save' => ["<?php\n\$user->save();\n"],
    'update on a model' => ["<?php\n\$coupon->update(['ends_at' => now()]);\n"],
    'static create' => ["<?php\nCoupon::create([]);\n"],
    'create on a query' => ["<?php\nCoupon::query()->create([]);\n"],
    'delete' => ["<?php\n\$order->delete();\n"],
    'force delete' => ["<?php\n\$order->forceDelete();\n"],
    'restore' => ["<?php\n\$order->restore();\n"],
    'attach' => ["<?php\n\$user->roles()->attach(1);\n"],
    'sync' => ["<?php\n\$user->roles()->sync([1]);\n"],
    'detach' => ["<?php\n\$user->roles()->detach(1);\n"],
    'insert' => ["<?php\nCoupon::query()->insert([]);\n"],
    'upsert' => ["<?php\nCoupon::query()->upsert([], ['id']);\n"],
    'increment' => ["<?php\n\$order->increment('count');\n"],
    'decrement' => ["<?php\n\$order->decrement('count');\n"],
    'update or create' => ["<?php\nTaxRate::query()->updateOrCreate([], []);\n"],
    'nullsafe delete' => ["<?php\n\$order?->delete();\n"],
    'policy method named like a write' => ["<?php\n\$policy->update(\$user, \$product);\n"],
    'method injected service' => ["<?php\nfinal class C { public function __invoke(\\App\\Modules\\Products\\Services\\ProductService \$service): void { \$service->delete(1); } }\n"],
    'property that is not an action or a service' => ["<?php\nfinal class C { public function __construct(private \\App\\Models\\Tenant\\Order \$order) {} public function __invoke(): void { \$this->order->delete(); } }\n"],
]);

it('detects business logic in a controller', function (string $source): void {
    expect(ThinControllersArchitectureHelpers::violationsIn($source))->toHaveCount(1);
})->with('controller business logic');

it('detects an __invoke that is too long', function (): void {
    $body = str_repeat("        \$a = 1;\n", ThinControllersArchitectureHelpers::MAX_INVOKE_LINES - 2);
    $source = "<?php\nfinal class C\n{\n    public function __invoke(): void\n    {\n{$body}    }\n}\n";
    $shorter = "<?php\nfinal class C\n{\n    public function __invoke(): void\n    {\n" . str_repeat("        \$a = 1;\n", ThinControllersArchitectureHelpers::MAX_INVOKE_LINES - 3) . "    }\n}\n";

    expect(ThinControllersArchitectureHelpers::violationsIn($source))->toHaveCount(1)
        ->and(ThinControllersArchitectureHelpers::violationsIn($shorter))->toBe([]);
});

it('accepts a controller that calls an action or a service it gets through its constructor', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Http\Controllers\Order;
        use App\Modules\Orders\Actions\DeleteOrder;
        use App\Modules\Orders\Actions\UpdateOrder;
        use App\Modules\Products\Services\ProductService;
        use App\Modules\Users\Actions\CreateUser;
        final readonly class DestroyController
        {
            private CreateUser $create;
            public function __construct(
                private DeleteOrder $deleteOrder,
                private UpdateOrder $update,
                private ProductService $products,
                CreateUser $create,
            ) {
                $this->create = $create;
            }
            public function __invoke(Order $order): RedirectResponse
            {
                $this->deleteOrder->handle($order);
                $this->update->handle($order);
                $this->products->delete($order->product);
                $this->products?->update($order->product);
                $this->create->create($order);
                $orders = Order::query()->where('id', 1)->get();
                Inertia::flash('success', 'done');
                return back();
            }
        }
        PHP;

    expect(ThinControllersArchitectureHelpers::violationsIn($source))->toBe([]);
});

it('keeps controllers thin: no database writes, locks, transactions or long __invoke methods', function (): void {
    expect(ThinControllersArchitectureHelpers::offendersInControllers())->toBe([]);
});
