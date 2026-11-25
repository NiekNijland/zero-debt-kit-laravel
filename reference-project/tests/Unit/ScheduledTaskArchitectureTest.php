<?php

declare(strict_types=1);

use Tests\Support\Architecture\ScheduledTaskGuard;

dataset('scheduled tasks that may overlap', [
    'no lock' => ["<?php\nuse Illuminate\\Support\\Facades\\Schedule;\nSchedule::command('carts:prune')->hourly()->onOneServer();\n", 3],
    'a lock without its minutes' => ["<?php\nuse Illuminate\\Support\\Facades\\Schedule;\n\nSchedule::command('carts:prune')->daily()->onOneServer()->withoutOverlapping();\n", 4],
    'minutes that are not written out' => ["<?php\nuse Illuminate\\Support\\Facades\\Schedule;\nSchedule::command('carts:prune')->daily()->withoutOverlapping(\$minutes)->onOneServer();\n", 3],
    'a job on the global alias' => ["<?php\n\\Schedule::job(new \\App\\Jobs\\PruneCartsJob())\n    ->everyMinute()\n    ->onOneServer();\n", 2],
]);

it('detects a scheduled task without a lock against overlapping runs', function (string $source, int $line): void {
    $violations = new ScheduledTaskGuard(dirname(__DIR__, 2))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($line . ': a scheduled task without ->withoutOverlapping(<minutes>)')
        ->and($violations[0])->toContain('add it with the minutes');
})->with('scheduled tasks that may overlap');

it('accepts a scheduled task with its minutes anywhere in the chain and a statement that schedules nothing', function (): void {
    $source = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Artisan;
        use Illuminate\Support\Facades\Schedule;
        Schedule::command('carts:prune')->hourly()->onOneServer()->withoutOverlapping(30);
        Schedule::command('invoices:send')
            ->dailyAt('03:00')
            ->withoutOverlapping(360)
            ->when(static fn (): bool => true);
        Artisan::command('inspire', static fn () => null)->hourly();
        $newsletter->schedule()->daily();
        PHP;

    expect(new ScheduledTaskGuard(dirname(__DIR__, 2))->inSource($source))->toBe([]);
});

it('gives every scheduled task in routes its lock against overlapping runs', function (): void {
    expect(new ScheduledTaskGuard(dirname(__DIR__, 2))->violations())->toBe([]);
});
