<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\QueueNameGuard;

dataset('queue names written as text', [
    'onQueue in a constructor' => ["<?php\nnamespace App;\nfinal class ExportJob { public function __construct() { \$this->onQueue('exports'); } }\n", 'onQueue() with a queue name as text'],
    'onQueue at dispatch' => ["<?php\nnamespace App;\nExportJob::dispatch(\$id)->onQueue('default');\n", 'onQueue() with a queue name as text'],
    'onQueue on a notification' => ["<?php\nnamespace App;\n\$user?->notification()?->onQueue(\"exports\");\n", 'onQueue() with a queue name as text'],
    'allOnQueue on a chain' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Bus;\nBus::chain(\$jobs)->allOnQueue('exports')->dispatch();\n", 'allOnQueue() with a queue name as text'],
    'the queue property' => ["<?php\nnamespace App;\nfinal class ExportJob { public \$queue = 'exports'; }\n", '$queue with a queue name as text'],
    'the queue property set by hand' => ["<?php\nnamespace App;\nfinal class ExportJob { public function __construct() { \$this->queue = 'mail'; } }\n", '->queue set to a queue name as text'],
    'viaQueues of a notification' => ["<?php\nnamespace App;\nfinal class DoneNotification { public function viaQueues(): array { return ['mail' => 'default']; } }\n", 'viaQueues() with a queue name as text'],
]);

it('detects a queue that is named with text', function (string $source, string $reason): void {
    $violations = new QueueNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('Queue::X');
})->with('queue names written as text');

it('accepts a queue named by the enum and a variable that happens to be called queue', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use App\Modules\Observability\Enums\Queue;
        final class ExportJob
        {
            public function __construct(Queue $queue)
            {
                $this->onQueue(Queue::EXPORTS);
                $this->onQueue(Queue::EXPORTS->value);
                $this->onQueue($queue);
            }
            public function viaQueues(): array { return ['mail' => Queue::DEFAULT->value]; }
            public function walk(): void { $queue = ['a']; $label = 'default'; $this->line("Jobs waiting on {$label}"); }
        }
        PHP;

    expect(new QueueNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('names every queue in app with a case of the Queue enum', function (): void {
    expect(new QueueNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
