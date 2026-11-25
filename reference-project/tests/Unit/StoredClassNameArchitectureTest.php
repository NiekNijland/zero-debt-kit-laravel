<?php

declare(strict_types=1);

use App\Providers\MovedClassServiceProvider;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\StoredClassNameGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

dataset('stored class names out of line', [
    'a new event that is not in the snapshot' => [
        ['app/Modules/Orders/Events/OrderPlacedEvent.php' => "<?php\n", 'tests/Support/Architecture/stored-class-names/events.php' => "<?php\nreturn [];\n"],
        [],
        'App\Modules\Orders\Events\OrderPlacedEvent is not in tests/Support/Architecture/stored-class-names/events.php: add it there, because the landlord events.name column stores it',
    ],
    'a new job in a subfolder' => [
        ['app/Modules/Products/Jobs/Images/ResizeProductImageJob.php' => "<?php\n"],
        [],
        'App\Modules\Products\Jobs\Images\ResizeProductImageJob is not in tests/Support/Architecture/stored-class-names/jobs.php',
    ],
    'an event that moved without a migration' => [
        ['app/Modules/Orders/Events/OrderPlacedEvent.php' => "<?php\n", 'tests/Support/Architecture/stored-class-names/events.php' => "<?php\nreturn ['App\\\\Modules\\\\Orders\\\\Events\\\\OrderPlacedEvent', 'App\\\\Events\\\\OrderShippedEvent'];\n"],
        [],
        'App\Events\OrderShippedEvent in tests/Support/Architecture/stored-class-names/events.php no longer exists: the landlord events.name column stores it: add a data migration that renames the rows, then replace the entry by the new name',
    ],
    'a job that was renamed without an alias' => [
        ['tests/Support/Architecture/stored-class-names/jobs.php' => "<?php\nreturn ['App\\\\Modules\\\\Orders\\\\Jobs\\\\FulfillJob'];\n"],
        [],
        'App\Modules\Orders\Jobs\FulfillJob in tests/Support/Architecture/stored-class-names/jobs.php no longer exists: queued payloads (jobs, failed_jobs, Horizon) store it: keep the old name in MovedClassServiceProvider::ALIASES for one release',
    ],
    'a mail whose alias points at a class that is gone' => [
        ['tests/Support/Architecture/stored-class-names/mail.php' => "<?php\nreturn ['App\\\\Mail\\\\WelcomeMail'];\n"],
        ['App\Mail\WelcomeMail' => 'App\Modules\Users\Mail\WelcomeMail'],
        'App\Mail\WelcomeMail in tests/Support/Architecture/stored-class-names/mail.php is an alias of App\Modules\Users\Mail\WelcomeMail, which is not a class in a Mail folder',
    ],
    'a notification listed twice' => [
        ['app/Modules/Orders/Notifications/OrderShippedNotification.php' => "<?php\n", 'tests/Support/Architecture/stored-class-names/notifications.php' => "<?php\nreturn ['App\\\\Modules\\\\Orders\\\\Notifications\\\\OrderShippedNotification', 'App\\\\Modules\\\\Orders\\\\Notifications\\\\OrderShippedNotification'];\n"],
        [],
        'tests/Support/Architecture/stored-class-names/notifications.php names a class twice',
    ],
]);

it('detects a stored class name that changed without its snapshot, migration or alias', function (array $files, array $aliases, string $reason): void {
    $root = SourceSandbox::create(Narrow::stringMap($files));

    try {
        $violations = new StoredClassNameGuard(new PhpFileInspector(), $root, Narrow::stringMap($aliases))->violations();

        expect($violations)->toHaveCount(1)
            ->and($violations[0])->toContain($reason);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('stored class names out of line');

it('accepts a listed class, an old name that an alias still carries and a class outside the stored folders', function (): void {
    $root = SourceSandbox::create([
        'app/Modules/Users/Mail/WelcomeMail.php' => "<?php\n",
        'app/Modules/Users/Services/WelcomeService.php' => "<?php\n",
        'app/Support/Mail/Layout.php' => "<?php\n",
        'tests/Support/Architecture/stored-class-names/mail.php' => "<?php\nreturn ['App\\\\Modules\\\\Users\\\\Mail\\\\WelcomeMail', 'App\\\\Mail\\\\WelcomeMail'];\n",
    ]);

    try {
        expect(new StoredClassNameGuard(new PhpFileInspector(), $root, ['App\Mail\WelcomeMail' => 'App\Modules\Users\Mail\WelcomeMail'])->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps the stored name of every event, job, mail and notification in its snapshot', function (): void {
    $guard = new StoredClassNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), MovedClassServiceProvider::ALIASES);

    expect(count($guard->declared('events')))->toBeGreaterThan(0)
        ->and(count($guard->declared('jobs')))->toBeGreaterThan(0)
        ->and(count($guard->declared('mail')))->toBeGreaterThan(0)
        ->and(count($guard->declared('notifications')))->toBeGreaterThan(0)
        ->and($guard->violations())->toBe([]);
});
