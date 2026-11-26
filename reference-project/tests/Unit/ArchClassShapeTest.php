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
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\StringBackedEnumGuard;
use Tests\Support\ClassNames;

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

test('the dev-only TypeScript generator stays out of runtime code', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $allowed = [ArchChecks::sourceOf(AppServiceProvider::class), ArchChecks::sourceOf(TypeScriptTransformerServiceProvider::class)];
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        $path = substr($file, strlen($root) + 1);

        if (in_array($path, $allowed, true) || str_starts_with($path, 'app/Support/TypeScript/')) {
            continue;
        }

        $uses = array_filter(
            $inspector->referencedNames($file),
            static fn (string $name): bool => str_starts_with($name, 'Spatie\LaravelTypeScriptTransformer\\')
                || (str_starts_with($name, 'Spatie\TypeScriptTransformer\\') && ! str_starts_with($name, 'Spatie\TypeScriptTransformer\Attributes\\')),
        );

        if ($uses !== []) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

arch('errors reach Sentry through report(), not through its facade')
    ->expect(Sentry::class)
    ->toOnlyBeUsedIn([AppServiceProvider::class]);

test('enum cases are UPPER_CASE', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $enums = 0;
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        if (preg_match('/^enum\s/m', (string) file_get_contents($file)) !== 1) {
            continue;
        }

        foreach (array_keys($inspector->declaredTypes($file)) as $type) {
            if (! enum_exists($type)) {
                continue;
            }

            $enums++;

            foreach ($type::cases() as $case) {
                if ($case->name !== strtoupper($case->name)) {
                    $offenders[] = $type . '::' . $case->name;
                }
            }
        }
    }

    expect($enums)->toBeGreaterThan(0)
        ->and($offenders)->toBe([]);
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

test('a TypeScript type is named after its PHP class', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $attributed = 0;
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        if (! str_contains((string) file_get_contents($file), '#[TypeScript')) {
            continue;
        }

        foreach (array_keys($inspector->declaredTypes($file)) as $type) {
            foreach ((new ReflectionClass(ClassNames::of($type)))->getAttributes(TypeScript::class) as $attribute) {
                $attributed++;

                if ($attribute->getArguments() !== []) {
                    $offenders[] = $type;
                }
            }
        }
    }

    expect($attributed)->toBeGreaterThan(0)
        ->and($offenders)->toBe([]);
});
