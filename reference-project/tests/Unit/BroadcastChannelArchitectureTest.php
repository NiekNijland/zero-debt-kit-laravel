<?php

declare(strict_types=1);

use Tests\Support\Architecture\BroadcastChannelGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('broadcast channels out of line', [
    'a channel that is not registered' => [
        ['app/Events/DoneEvent.php' => "<?php\nnamespace App\\Events;\nuse Illuminate\\Broadcasting\\PrivateChannel;\nfinal class DoneEvent { public function broadcastOn(): array { return [new PrivateChannel('invoice.' . \$this->key)]; } }\n"],
        'app/Events/DoneEvent.php:4: the broadcast channel invoice is not registered',
    ],
    'an interpolated presence channel that is not registered' => [
        ['app/Events/DoneEvent.php' => "<?php\nnamespace App\\Events;\nuse Illuminate\\Broadcasting\\PresenceChannel;\nnew PresenceChannel(\"counter.{\$id}\");\n"],
        'app/Events/DoneEvent.php:4: the broadcast channel counter is not registered',
    ],
    'a public channel by its full name' => [
        ['app/Events/DoneEvent.php' => "<?php\nnamespace App\\Events;\nnew \\Illuminate\\Broadcasting\\Channel('news');\n"],
        'app/Events/DoneEvent.php:3: the broadcast channel news is not registered',
    ],
    'a name built from a variable alone' => [
        ['app/Events/DoneEvent.php' => "<?php\nnamespace App\\Events;\nuse Illuminate\\Broadcasting\\PrivateChannel;\nnew PrivateChannel(\$this->channel);\n"],
        'app/Events/DoneEvent.php:4: a broadcast channel whose name does not start with text',
    ],
    'a registered channel without a test' => [
        ['routes/channels.php' => "<?php\nuse Illuminate\\Support\\Facades\\Broadcast;\nBroadcast::channel('warehouse.{warehouse}', static fn (): bool => true);\nBroadcast::channel('shipment.{shipment}', static fn (): bool => true);\n", 'tests/Feature/Routing/WarehouseChannelTest.php' => "<?php\n"],
        'routes/channels.php: the channel shipment has no authorization test: add tests/Feature/Routing/ShipmentChannelTest.php',
    ],
]);

it('detects a broadcast channel that is not registered or not tested', function (array $files, string $reason): void {
    $root = SourceSandbox::create(['app/.gitkeep' => '', ...Narrow::stringMap($files)]);

    try {
        $violations = new BroadcastChannelGuard(new PhpFileInspector(), $root, 'app', 'routes/channels.php', 'tests/Feature/Routing')->violations();

        expect($violations)->toHaveCount(1)
            ->and($violations[0])->toContain($reason)
            ->and($violations[0])->toContain('routes/channels.php');
    } finally {
        SourceSandbox::remove($root);
    }
})->with('broadcast channels out of line');

it('accepts a registered channel with its test and a class that is only named Channel', function (): void {
    $root = SourceSandbox::create([
        'routes/channels.php' => "<?php\nuse Illuminate\\Support\\Facades\\Broadcast;\nBroadcast::channel('warehouse.{warehouse}', static fn (): bool => true);\n",
        'tests/Feature/Routing/WarehouseChannelTest.php' => "<?php\n",
        'app/Events/DoneEvent.php' => "<?php\nnamespace App\\Events;\nuse App\\Support\\Channel;\nuse Illuminate\\Broadcasting\\PrivateChannel;\nnew PrivateChannel('warehouse.' . \$key);\nnew PrivateChannel(\"warehouse.{\$key}\");\nnew Channel(\$anything);\n",
    ]);

    try {
        expect(new BroadcastChannelGuard(new PhpFileInspector(), $root, 'app', 'routes/channels.php', 'tests/Feature/Routing')->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('names the file relative to the root when the checkout sits under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Events/DoneEvent.php' => "<?php\nnamespace App\\Events;\nuse Illuminate\\Broadcasting\\PrivateChannel;\nnew PrivateChannel('invoice.' . \$key);\n"], prefix: '.checkout-');

    try {
        $violations = new BroadcastChannelGuard(new PhpFileInspector(), $root, 'app', 'routes/channels.php', 'tests/Feature/Routing')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Events/DoneEvent.php:4: the broadcast channel invoice is not registered');
});

it('registers and tests every broadcast channel the application sends on', function (): void {
    $guard = new BroadcastChannelGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'routes/channels.php', 'tests/Feature/Routing');

    expect($guard->registered())->not->toBe([])
        ->and($guard->violations())->toBe([]);
});
