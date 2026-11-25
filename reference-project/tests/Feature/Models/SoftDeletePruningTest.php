<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Models\Tenant\Cart;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

it('keeps soft-deleted rows for 45 days by default', function (): void {
    expect(config('pruning.trashed_after_days'))->toBe(45);
});

it('prunes soft-deleted carts, product variants, product attributes and coupons after the configured number of days', function (): void {
    $tenant = $this->createTenantAndMakeCurrent();

    $liveCart = Cart::factory()->create();
    $recentCart = Cart::factory()->create();
    $oldCart = Cart::factory()->create();

    $product = Product::factory()->create();
    $liveVariant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $recentVariant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $oldVariant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $attributeOfOldVariant = ProductAttribute::factory()->create(['product_variant_id' => $oldVariant->id]);
    $liveAttribute = ProductAttribute::factory()->create(['product_variant_id' => $liveVariant->id]);
    $recentAttribute = ProductAttribute::factory()->create(['product_variant_id' => $liveVariant->id]);
    $oldAttribute = ProductAttribute::factory()->create(['product_variant_id' => $liveVariant->id]);

    $liveCoupon = Coupon::factory()->create();
    $recentCoupon = Coupon::factory()->create();
    $oldCoupon = Coupon::factory()->create();

    $this->travel(-46)->days();
    foreach ([$oldCart, $oldVariant, $oldAttribute, $oldCoupon] as $model) {
        $model->delete();
    }
    $this->travelBack();

    $this->travel(-44)->days();
    foreach ([$recentCart, $recentVariant, $recentAttribute, $recentCoupon] as $model) {
        $model->delete();
    }
    $this->travelBack();

    Tenant::forgetCurrent();
    $this->artisan('model:prune', ['--model' => [Cart::class, ProductVariant::class, ProductAttribute::class, Coupon::class]])->assertSuccessful();
    $tenant->makeCurrent();

    expect(Cart::withTrashed()->pluck('id')->sort()->values()->all())->toBe([$liveCart->id, $recentCart->id])
        ->and(ProductVariant::withTrashed()->pluck('id')->sort()->values()->all())->toBe([$liveVariant->id, $recentVariant->id])
        ->and(ProductAttribute::withTrashed()->pluck('id')->sort()->values()->all())->toBe([$liveAttribute->id, $recentAttribute->id])
        ->and(ProductAttribute::withTrashed()->whereKey($attributeOfOldVariant->id)->exists())->toBeFalse()
        ->and(Coupon::withTrashed()->pluck('id')->sort()->values()->all())->toBe([$liveCoupon->id, $recentCoupon->id])
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('prunes after the configured number of days', function (): void {
    config(['pruning.trashed_after_days' => 5]);
    $tenant = $this->createTenantAndMakeCurrent();

    $old = Cart::factory()->create();
    $recent = Cart::factory()->create();

    $this->travel(-6)->days();
    $old->delete();
    $this->travelBack();

    $this->travel(-4)->days();
    $recent->delete();
    $this->travelBack();

    Tenant::forgetCurrent();
    $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();
    $tenant->makeCurrent();

    expect(Cart::withTrashed()->pluck('id')->all())->toBe([$recent->id]);
});

it('prunes the soft-deleted rows of every tenant', function (): void {
    $first = $this->createTenantAndMakeCurrent();
    $second = Tenant::factory()->withConfig()->create();
    $trashed = [];

    foreach ([$first, $second] as $tenant) {
        $tenant->makeCurrent();
        $cart = Cart::factory()->create();
        $this->travel(-46)->days();
        $cart->delete();
        $this->travelBack();
        $trashed[] = $cart->id;
    }

    Tenant::forgetCurrent();
    $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

    expect(Cart::query()->acrossTenants()->withTrashed()->whereKey($trashed)->exists())->toBeFalse();
});

it('keeps old soft-deleted rows of models whose history is still read', function (): void {
    $tenant = $this->createTenantAndMakeCurrent();

    $models = [
        Product::factory()->create(),
        Order::factory()->create(),
        Supplier::factory()->create(),
        Customer::factory()->create(['tenant_id' => $tenant->id]),
        User::factory()->create(),
    ];

    $this->travel(-365)->days();
    foreach ($models as $model) {
        $model->delete();
    }
    $this->travelBack();

    Tenant::forgetCurrent();
    $this->artisan('model:prune')->assertSuccessful();
    $tenant->makeCurrent();

    foreach ($models as $model) {
        expect(DB::table($model->getTable())->where('id', $model->getKey())->whereNotNull('deleted_at')->exists())->toBeTrue();
    }
});
