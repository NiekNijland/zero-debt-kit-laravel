<?php

declare(strict_types=1);

use App\Modules\Shipments\Contracts\ShippingMethod;
use App\Providers\AppServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\ServiceProvider;
use Sentry\Laravel\Facade as Sentry;
use Spatie\LaravelData\Data;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\EnumCaseGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\StringBackedEnumGuard;
use Tests\Support\Architecture\TypeScriptAttributeGuard;
use Tests\Support\Architecture\TypeScriptGeneratorGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

arch()->preset()->php();
arch('form requests extend FormRequest, carry the Request suffix and define rules()')
    ->expect('App\Http\Requests')
    ->classes()
    ->toExtend(FormRequest::class)
    ->toHaveSuffix('Request')
    ->toHaveMethod('rules');

arch('middleware handles the request')
    ->expect('App\Http\Middleware')
    ->classes()
    ->toHaveMethod('handle');

arch('providers extend ServiceProvider, carry the ServiceProvider suffix and are used by providers only')
    ->expect('App\Providers')
    ->classes()
    ->toExtend(ServiceProvider::class)
    ->toHaveSuffix('ServiceProvider')
    ->toOnlyBeUsedIn('App\Providers');

arch('enums sit in an Enums folder and are enums')
    ->expect('App\Modules\*\Enums')
    ->toBeEnums();

arch('exceptions are throwable')
    ->expect('App\Modules\*\Exceptions')
    ->classes()
    ->toImplement(Throwable::class);

arch('commands extend Command and handle')
    ->expect('App\Modules\*\Commands')
    ->classes()
    ->toExtend(Command::class)
    ->toHaveMethod('handle');

arch('mailables extend Mailable')
    ->expect('App\Modules\*\Mail')
    ->classes()
    ->toExtend(Mailable::class);

arch('notifications extend Notification')
    ->expect('App\Modules\*\Notifications')
    ->classes()
    ->toExtend(Notification::class);

arch('jobs are queued')
    ->expect('App\Modules\*\Jobs')
    ->classes()
    ->toImplement(ShouldQueue::class);

arch('debugging and environment calls stay out of the code')
    ->expect(['dd', 'ddd', 'dump', 'env', 'exit', 'ray'])
    ->not->toBeUsed();

arch('no weak hashing, shell execution or dynamic evaluation')
    ->expect([
        'md5',
        'sha1',
        'uniqid',
        'rand',
        'mt_rand',
        'tempnam',
        'str_shuffle',
        'shuffle',
        'array_rand',
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'create_function',
        'extract',
        'mb_parse_str',
        'dl',
        'assert',
    ])
    ->not->toBeUsed();

arch('the application never unserializes; only tests restore queued payloads')
    ->expect('unserialize')
    ->not->toBeUsedIn(['App', 'Database']);

arch('actions')
    ->expect('App\Modules\*\Actions')
    ->toHaveMethod('handle');

arch('shipping methods are named XShippingService')
    ->expect('App')
    ->classes()
    ->implementing(ShippingMethod::class)
    ->toHaveSuffix('ShippingService');

dataset('uses of the TypeScript generator in runtime code', [
    'the transformer package' => ["<?php\nuse Spatie\\TypeScriptTransformer\\TypeScriptTransformerConfig;\n"],
    'the Laravel bridge' => ["<?php\nuse Spatie\\LaravelTypeScriptTransformer\\Transformers\\X;\n"],
    'a qualified name in the code' => ["<?php\nnamespace App;\nfinal class A { public function b(): void { new \\Spatie\\TypeScriptTransformer\\Collectors\\Collector(); } }\n"],
]);

it('detects the dev-only TypeScript generator in runtime code', function (string $source): void {
    $guard = new TypeScriptGeneratorGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], [], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\');
    $violations = $guard->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('only its provider and its own folder name it');
})->with('uses of the TypeScript generator in runtime code');

it('accepts the TypeScript attribute and a package with a similar name', function (): void {
    $guard = new TypeScriptGeneratorGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], [], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\');

    expect($guard->inSource("<?php\nuse Spatie\\TypeScriptTransformer\\Attributes\\TypeScript;\nuse Spatie\\TypeScriptTransformerHelpers\\X;\n#[TypeScript]\nfinal class A {}\n"))->toBe([]);
});

it('reads the folder under a dot directory and skips the files that configure the generator', function (): void {
    $source = "<?php\nuse Spatie\\TypeScriptTransformer\\TypeScriptTransformerConfig;\n";
    $root = SourceSandbox::create(['app/Providers/Gen.php' => $source, 'app/Support/TypeScript/Own.php' => $source, 'app/Http/Bad.php' => $source], prefix: '.checkout-');

    try {
        $violations = new TypeScriptGeneratorGuard(new PhpFileInspector(), $root, 'app', ['app/Providers/Gen.php'], ['app/Support/TypeScript/'], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Http/Bad.php: the dev-only TypeScript generator');
});

it('keeps the dev-only TypeScript generator out of runtime code', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AppServiceProvider::class, $root), ClassSourcePath::of(TypeScriptTransformerServiceProvider::class, $root)];
    $guard = new TypeScriptGeneratorGuard(new PhpFileInspector(), $root, 'app', $allowed, ['app/Support/TypeScript/'], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\');

    expect($guard->violations())->toBe([]);
});

arch('errors reach Sentry through report(), not through its facade')
    ->expect(Sentry::class)
    ->toOnlyBeUsedIn([AppServiceProvider::class]);

dataset('enum cases that are not UPPER_CASE', [
    'camel case' => ["<?php\nenum Status: string { case Placed = 'p'; }\n", 'the enum case Placed is not UPPER_CASE: write it as PLACED'],
    'a mixed case in a second case' => ["<?php\nenum Status: string { case PLACED = 'p'; case shippedLate = 's'; }\n", 'shippedLate is not UPPER_CASE: write it as SHIPPED_LATE'],
]);

it('detects an enum case that is not UPPER_CASE', function (string $source, string $words): void {
    $violations = new EnumCaseGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('enum cases that are not UPPER_CASE');

it('accepts UPPER_CASE cases and a constant in a class that is not an enum', function (): void {
    $guard = new EnumCaseGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app');

    expect($guard->inSource("<?php\nenum Status: string { case PLACED = 'p'; case SHIPPED_LATE = 's'; const Default = self::PLACED; }\nfinal class A { public const Mixed = 1; }\n"))->toBe([]);
});

it('reads an enum below a checkout under a dot directory and fails on a folder without one', function (): void {
    $root = SourceSandbox::create(['app/Status.php' => "<?php\nenum Status: string { case Placed = 'p'; }\n", 'app/Plain.php' => "<?php\nfinal class Plain {}\n", 'none/Plain.php' => "<?php\nfinal class Plain {}\n"], prefix: '.checkout-');

    try {
        $violations = new EnumCaseGuard(new PhpFileInspector(), $root, 'app')->violations();
        $none = new EnumCaseGuard(new PhpFileInspector(), $root, 'none')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Status.php: line 2: the enum case Placed')
        ->and($none)->toHaveCount(1)
        ->and($none[0])->toContain('no enum was read');
});

it('writes every enum case of app in UPPER_CASE', function (): void {
    expect(new EnumCaseGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});

it('detects an enum that is not backed by a string', function (string $source, string $reason): void {
    $violations = new StringBackedEnumGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('enum X: string');
})->with([
    'a pure enum' => ["<?php\nnamespace App;\nenum Status { case PLACED; case SHIPPED; }\n", 'App\\Status has no backing type'],
    'an integer enum' => ["<?php\nnamespace App;\nenum Level: int { case LOW = 1; }\n", 'App\\Level is backed by int'],
]);

it('backs every enum in app by a string', function (): void {
    $guard = new StringBackedEnumGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app');

    expect($guard->inSource("<?php\nnamespace App;\nenum Status: string { case PLACED = 'placed'; }\nfinal class Enum {}\n"))->toBe([])
        ->and($guard->violations())->toBe([]);
});

arch('every Data object carries the Data suffix')
    ->expect('App')
    ->classes()
    ->extending(Data::class)
    ->toHaveSuffix('Data');

arch('a class in a Data namespace carries the Data suffix')
    ->expect('App\Modules\*\Data')
    ->classes()
    ->toHaveSuffix('Data');

dataset('TypeScript attributes that rename the type', [
    'a name' => ["<?php\n#[TypeScript('Other')]\nfinal class A {}\n"],
    'a named argument' => ["<?php\n#[\\Spatie\\TypeScriptTransformer\\Attributes\\TypeScript(name: 'Other')]\nfinal class A {}\n"],
]);

it('detects a TypeScript attribute that renames the type', function (string $source): void {
    $violations = new TypeScriptAttributeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'TypeScript')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('drop the argument, the TypeScript type is named after the PHP class');
})->with('TypeScript attributes that rename the type');

it('accepts the bare attribute and another attribute with an argument', function (): void {
    $guard = new TypeScriptAttributeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'TypeScript');
    $source = "<?php\n#[TypeScript]\nfinal class A {}\n#[Other('x')]\nfinal class B {}\n";

    expect($guard->inSource($source))->toBe([])
        ->and($guard->attributeCount($source))->toBe(1);
});

it('reads the attributes below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/A.php' => "<?php\n#[TypeScript('X')]\nfinal class A {}\n"], prefix: '.checkout-');

    try {
        $violations = new TypeScriptAttributeGuard(new PhpFileInspector(), $root, 'app', 'TypeScript')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/A.php: line 2: #[TypeScript] renames the type');
});

it('names every TypeScript type after its PHP class', function (): void {
    expect(new TypeScriptAttributeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'TypeScript')->violations())->toBe([]);
});
