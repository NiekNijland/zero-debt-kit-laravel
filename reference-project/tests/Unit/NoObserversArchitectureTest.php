<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Tests\Support\Architecture\ObserverRegistrationGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

arch('models register their events in booted(), never through an observer attribute')
    ->expect('App')
    ->not->toHaveAttribute(ObservedBy::class);

arch('the application has no observer classes')
    ->expect('App')
    ->classes()
    ->not->toHaveSuffix('Observer');

dataset('ways to register an observer', [
    'the model class' => ["<?php\nnamespace App;\nUser::observe(UserObserver::class);\n", 'line 3:'],
    'static in a model' => ["<?php\nnamespace App;\nfinal class User { protected static function booted(): void { static::observe(UserObserver::class); } }\n", 'line 3:'],
    'self in a model' => ["<?php\nnamespace App;\nfinal class User { protected static function booted(): void { self::observe(UserObserver::class); } }\n", 'line 3:'],
    'an aliased model' => ["<?php\nnamespace App;\nuse App\\Models\\User as Account;\nAccount::observe(UserObserver::class);\n", 'line 4:'],
    'a fully qualified model' => ["<?php\nnamespace App;\n\\App\\Models\\User::observe(UserObserver::class);\n", 'line 3:'],
    'a variable class' => ["<?php\nnamespace App;\n\$model::observe(UserObserver::class);\n", 'line 3:'],
    'an instance' => ["<?php\nnamespace App;\n\$user->observe(UserObserver::class);\n", 'line 3:'],
    'a nullsafe instance' => ["<?php\nnamespace App;\n\$user?->observe(UserObserver::class);\n", 'line 3:'],
    'the first-class callable form' => ["<?php\nnamespace App;\n\$register = User::observe(...);\n", 'line 3:'],
]);

it('detects every way to register an observer on a model', function (string $source, string $line): void {
    $violations = new ObserverRegistrationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith($line)
        ->and($violations[0])->toContain('observe()', 'booted()');
})->with('ways to register an observer');

dataset('code that is not an observer registration', [
    'events registered in booted' => ["<?php\nnamespace App;\nfinal class User { protected static function booted(): void { static::created(fn (User \$user) => 1); } }\n"],
    'a method of another name' => ["<?php\nnamespace App;\n\$user->observed(); User::observer();\n"],
    'observe in a comment' => ["<?php\nnamespace App;\n// User::observe(UserObserver::class);\n"],
    'observe in a string' => ["<?php\nnamespace App;\n\$text = 'User::observe(UserObserver::class)';\n"],
    'a function named observe' => ["<?php\nnamespace App;\nobserve(\$user);\n"],
]);

it('accepts events registered in booted(), a method of another name and text that only says observe', function (string $source): void {
    expect(new ObserverRegistrationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
})->with('code that is not an observer registration');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Models/User.php' => "<?php\nnamespace App\\Models;\nfinal class User { protected static function booted(): void { static::observe(UserObserver::class); } }\n",
        'app/Models/Order.php' => "<?php\nnamespace App\\Models;\nfinal class Order { protected static function booted(): void { static::created(fn () => 1); } }\n",
    ], prefix: '.checkout-');

    try {
        $violations = new ObserverRegistrationGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Models/User.php:3: an observer registered with observe()');
});

it('never registers an observer with observe()', function (): void {
    expect(new ObserverRegistrationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
