<?php

declare(strict_types=1);

use Tests\Support\Architecture\EventDispatchGuard;
use Tests\Support\Architecture\PhpFileInspector;

dataset('events of the application handed to the dispatcher', [
    'the facade' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new OrderPlacedEvent(\$order));\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'the facade with named arguments' => ["<?php\nnamespace App\\Actions;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new \\App\\Events\\OrderPlacedEvent(order: \$order, user: \$user));\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'the global alias' => ["<?php\nnamespace App\\Actions;\n\\Event::dispatch(new \\App\\Events\\OrderPlacedEvent());\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'the helper' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nevent(new OrderPlacedEvent(\$order));\n", 'event(new OrderPlacedEvent(...))'],
    'an event in the same namespace' => ["<?php\nnamespace App\\Events;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new OrderPlacedEvent(\$order));\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
]);

it('detects an event of the application that is built for the dispatcher', function (string $source, string $written): void {
    $violations = new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($written)
        ->and($violations[0])->toContain('write OrderPlacedEvent::dispatch(...)');
})->with('events of the application handed to the dispatcher');

it('accepts the static form, an event built earlier, an event of a vendor and a dispatcher that is not the event facade', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Actions;
        use App\Events\OrderPlacedEvent;
        use Illuminate\Auth\Events\Lockout;
        use Illuminate\Support\Facades\Bus;
        use Illuminate\Support\Facades\Event;
        OrderPlacedEvent::dispatch($order);
        Event::dispatch($event);
        Event::dispatch(clone $event);
        Event::dispatch(new Lockout($request));
        Bus::dispatch(new \App\Jobs\ExportInvoiceJob($order));
        $this->analytics->event(new \App\ValueObjects\Purchase());
        PHP;

    expect(new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('dispatches every event of the application in app through its own dispatch()', function (): void {
    expect(new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
