<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http\Controllers\Order;

use App\Models\Tenant\Order;
use App\Models\Tenant\OrderLine;

final class OrderShowControllerHelpers
{
    public static function orderWithLines(int $lines): Order
    {
        $order = Order::factory()->create();
        OrderLine::factory()->count($lines)->create(['order_id' => $order->id]);

        return $order;
    }
}
