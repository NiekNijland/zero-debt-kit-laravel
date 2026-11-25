<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\QueuedClassLimitsGuard;

dataset('queued classes without their limits', [
    'a job without either' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ExportJob implements ShouldQueue { public function handle(): void {} }\n", ['App\\ExportJob is queued without $timeout', 'App\\ExportJob is queued without $tries']],
    'a job without a timeout' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ExportJob implements ShouldQueue { public int \$tries = 3; }\n", ['App\\ExportJob is queued without $timeout']],
    'a job without tries' => ["<?php\nnamespace App;\nfinal class ExportJob implements \\Illuminate\\Contracts\\Queue\\ShouldQueue { public int \$timeout = 60; }\n", ['App\\ExportJob is queued without $tries']],
    'a notification under an alias of the contract' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue as Queued;\nclass ShippedNotification extends Notification implements Queued { public int \$tries = 1; public int \$backoff = 5; }\n", ['App\\ShippedNotification is queued without $timeout']],
    'a job that retries until a moment without a timeout' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ChargeJob implements ShouldQueue { public function retryUntil(): \\DateTimeInterface { return now()->addHour(); } }\n", ['App\\ChargeJob is queued without $timeout']],
    'a listener that is queued after the commit' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueueAfterCommit;\nfinal class StartExport implements ShouldQueueAfterCommit { public int \$tries = 5; public function handle(): void {} }\n", ['App\\StartExport is queued without $timeout']],
    'a listener that is queued after the commit without either' => ["<?php\nnamespace App;\nfinal readonly class StartExport implements \\Illuminate\\Contracts\\Queue\\ShouldQueueAfterCommit { public function handle(): void {} }\n", ['App\\StartExport is queued without $timeout', 'App\\StartExport is queued without $tries']],
    'a class whose parent is queued' => ["<?php\nnamespace App;\nuse Illuminate\\Foundation\\Console\\QueuedCommand;\nfinal class NightlyCommand extends QueuedCommand {}\n", ['App\\NightlyCommand is queued without $timeout', 'App\\NightlyCommand is queued without $tries']],
]);

it('detects a queued class that does not declare its timeout or its tries', function (string $source, array $reasons): void {
    $violations = new QueuedClassLimitsGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source);

    expect($violations)->toHaveCount(count($reasons));

    foreach ($reasons as $index => $reason) {
        expect($violations[$index])->toContain($reason)->toContain('declare public int');
    }
})->with('queued classes without their limits');

it('accepts a queued class with both, a listener queued after the commit with both, one that inherits them, one that retries until a moment and a class that is not queued', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Contracts\Queue\ShouldQueue;
        final class ExportJob implements ShouldQueue { public int $tries = 3; public int $timeout = 60; }
        final class ChargeJob implements ShouldQueue { public int $timeout = 60; public function retryUntil(): \DateTimeInterface { return now()->addHour(); } }
        final class StartExport implements \Illuminate\Contracts\Queue\ShouldQueueAfterCommit { public int $timeout = 60; public int $tries = 5; public function handle(): void {} }
        final class RelayListener extends \Illuminate\Events\CallQueuedListener {}
        final class PlainListener { public function handle(): void {} }
        final class OtherJob implements \App\Contracts\ShouldQueue {}
        PHP;

    expect(new QueuedClassLimitsGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source))->toBe([]);
});

it('gives every queued class in app its own timeout and tries', function (): void {
    expect(new QueuedClassLimitsGuard(new PhpFileInspector(), dirname(__DIR__, 2))->violations())->toBe([]);
});
