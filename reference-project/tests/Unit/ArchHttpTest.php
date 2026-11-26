<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Tests\Support\ArchChecks;
use Tests\Support\Architecture\InertiaPageGuard;
use Tests\Support\Architecture\PhpCallReader;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RouteParameterGuard;
use Tests\Support\ClassNames;
use Tests\Support\Globs;
use Tests\Support\InstancesOf;

uses()->group('arch');

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('only invokable controllers')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveMethod('__invoke');

test('validation lives in form requests: nothing else validates a request', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $calls = static fn (string $source): array => ArchChecks::requestValidationLines($inspector->calls($source));

    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        $class = 'App\\' . str_replace('/', '\\', substr($file, strlen($root . '/app/'), -4));

        if (str_starts_with($class, 'App\Http\Requests\\')) {
            continue;
        }

        foreach (ArchChecks::requestValidationLines($inspector->callsIn($file)) as $line) {
            $offenders[] = $class . ' line ' . $line;
        }
    }

    expect($offenders)->toBe([])
        ->and($calls('<?php $request->validate([]);'))->toHaveCount(1)
        ->and($calls('<?php $request->validateWithBag(\'x\', []); request()->validate([]);'))->toHaveCount(2)
        ->and($calls('<?php Validator::make($a, $b)->validate(); \Illuminate\Support\Facades\Validator::validate($a, $b);'))->toHaveCount(2)
        ->and($calls('<?php $a = Auth::guard(\'web\')->validate($credentials); $b = $rule->validate($a, $b, $fail); $this->validate($input);'))->toBe([]);
});

test('controllers check authorization on the route, not in the action', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $lines = static fn (string $source): array => [
        ...ArchChecks::authorizationLines($inspector->calls($source)),
        ...ArchChecks::deniedInstantiationLines(PhpCallReader::instantiations($source)),
    ];

    // A permission goes on the route, a rule about a model goes in its policy and on the route, a rule about the input goes in the form request,
    // a check on a row the action locks goes in the action, and the credential of a webhook goes in a middleware.
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app/Http/Controllers') as $file) {
        $class = 'App\Http\Controllers\\' . str_replace('/', '\\', substr($file, strlen($root . '/app/Http/Controllers/'), -4));

        foreach ($lines((string) file_get_contents($file)) as $line) {
            $offenders[] = $class . ' line ' . $line;
        }
    }

    expect($offenders)->toBe([])
        ->and($lines('<?php Gate::authorize(\'update\', $a);'))->toHaveCount(1)
        ->and($lines('<?php Gate::forUser($u)->authorize(\'update\', $a); $this->authorize(\'x\');'))->toHaveCount(2)
        ->and($lines('<?php abort_unless($a->can(\'x\'), 404); abort_if($a, 403); abort_unless($a, Response::HTTP_FORBIDDEN);'))->toHaveCount(3)
        ->and($lines('<?php abort_unless($a, 403, \'No access\'); abort_if($b, Response::HTTP_FORBIDDEN, \'x\');'))->toHaveCount(2)
        ->and($lines('<?php abort_unless($a->user_id === $b->id, 404);'))->toHaveCount(1)
        ->and($lines('<?php abort_unless($model instanceof Model, 404); abort_if($a === null, Response::HTTP_NOT_FOUND); abort(404);'))->toBe([])
        ->and($lines('<?php abort(403); abort(Response::HTTP_FORBIDDEN, \'x\'); \\abort(401);'))->toHaveCount(3)
        ->and($lines('<?php throw new HttpException(401, \'x\'); throw new \\Symfony\\Component\\HttpKernel\\Exception\\HttpException(Response::HTTP_FORBIDDEN);'))->toHaveCount(2)
        ->and($lines('<?php throw new AuthorizationException(); throw new AccessDeniedHttpException(); throw new UnauthorizedHttpException(\'Bearer\');'))->toHaveCount(3)
        ->and($lines('<?php throw new HttpException(409); throw new NotFoundHttpException(); $a = new Response(403);'))->toBe([])
        ->and($lines('<?php throw_unless($a, AccessDeniedHttpException::class, \'x\'); throw_if($b, \\Illuminate\\Auth\\Access\\AuthorizationException::class); \\throw_if($c, HttpException::class, 403);'))->toHaveCount(3)
        ->and($lines('<?php throw_unless($a, NotFoundHttpException::class); throw_if($b, HttpException::class, 409); throw_if($c, $exception);'))->toBe([])
        ->and($lines('<?php $a = Gate::allows(\'update\', $b); $c = Gate::forUser($u)->allows(\'view\', $d);'))->toBe([]);
});

test('outside the HTTP layer a refusal is a typed exception, not an HTTP answer', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $lines = static fn (string $source): array => ArchChecks::httpRefusalLines($inspector->calls($source), PhpCallReader::instantiations($source));

    // Controllers have the stricter rule above; a middleware answers the HTTP request itself, so abort(401) or abort(403) is its refusal.
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        $relative = substr($file, strlen($root . '/app/'));

        if (str_starts_with($relative, 'Http/Controllers/') || str_starts_with($relative, 'Http/Middleware/')) {
            continue;
        }

        foreach ($lines((string) file_get_contents($file)) as $line) {
            $offenders[] = 'app/' . $relative . ' line ' . $line;
        }
    }

    expect($offenders)->toBe([])
        ->and($lines('<?php abort(403); \\abort(401, \'x\'); abort(Response::HTTP_FORBIDDEN);'))->toHaveCount(3)
        ->and($lines('<?php abort_if($a, 403); abort_unless($b, Response::HTTP_UNAUTHORIZED, \'x\');'))->toHaveCount(2)
        ->and($lines('<?php throw new AccessDeniedHttpException(); throw new UnauthorizedHttpException(\'Bearer\'); throw new HttpException(403);'))->toHaveCount(3)
        ->and($lines('<?php throw_unless($a, AccessDeniedHttpException::class); throw_if($b, HttpException::class, Response::HTTP_FORBIDDEN);'))->toHaveCount(2)
        ->and($lines('<?php abort(404); abort_if($a, 422); abort_unless($b, Response::HTTP_NOT_FOUND); throw new HttpException(409);'))->toBe([])
        ->and($lines('<?php throw new AuthorizationException(); throw new OrderAlreadyShippedException(); throw_unless($a, NotFoundHttpException::class);'))->toBe([]);
});

test('controllers and route files redirect with to_route() and back(), not through redirect()', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $calls = static fn (string $source): array => ArchChecks::redirectLines($inspector->calls($source));
    $offenders = [];

    foreach (['app', 'bootstrap', 'routes'] as $directory) {
        foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
            foreach (ArchChecks::redirectLines($inspector->callsIn($file)) as $line) {
                $offenders[] = substr($file, strlen($root) + 1) . ' line ' . $line;
            }
        }
    }

    expect($offenders)->toBe([])
        ->and($calls('<?php return redirect()->route(\'home\');'))->toHaveCount(1)
        ->and($calls('<?php return redirect()' . "\n" . '->route(\'login\')->withErrors([]);'))->toHaveCount(1)
        ->and($calls('<?php return redirect()->back(fallback: \'/\'); return Redirect::route(\'home\');'))->toHaveCount(2)
        ->and($calls('<?php return \\redirect()->route(\'home\'); return \\Illuminate\\Support\\Facades\\Redirect::route(\'home\'); return \\Redirect::back(); return REDIRECT()->back();'))->toHaveCount(4)
        ->and($calls('<?php use Illuminate\\Support\\Facades\\Redirect as R; return R::route(\'home\');'))->toHaveCount(1)
        ->and($calls('<?php return to_route(\'home\'); return back(); return redirect()->to($url); return redirect()->away($url); return redirect()->intended(\'/\'); return redirect(\'/\');'))->toBe([]);
});

test('a controller is named <Verb>Controller and sits in a folder named after what it acts on', function (): void {
    $verbs = [
        'Cancel', 'Create', 'Destroy', 'Download', 'Edit', 'Export', 'Import', 'Index', 'Receive', 'Restore', 'Search', 'Send', 'Show', 'Store',
        'Update',
    ];
    $inspector = new PhpFileInspector();
    $controllers = 0;
    $used = [];
    $offenders = [];

    foreach ($inspector->phpFiles(dirname(__DIR__, 2) . '/app/Http/Controllers') as $file) {
        foreach (array_keys($inspector->declaredTypes($file)) as $type) {
            $controllers++;
            $reflection = new ReflectionClass(ClassNames::of($type));
            $verb = substr($reflection->getShortName(), 0, -strlen('Controller'));
            $inFolder = $reflection->getNamespaceName() !== 'App\Http\Controllers';

            if (! $inFolder || ! str_ends_with($reflection->getShortName(), 'Controller') || ! in_array($verb, $verbs, true)) {
                $offenders[] = $type;
            } else {
                $used[$verb] = true;
            }
        }
    }

    expect($controllers)->toBeGreaterThan(0)
        ->and($offenders)->toBe([])
        ->and(array_values(array_diff($verbs, array_keys($used))))->toBe([]);
});

test('route files import a controller folder and write Folder\Noun\VerbController::class', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach ([...Globs::of($root . '/routes/*.php'), $root . '/bootstrap/app.php'] as $file) {
        if (preg_match('/^use App\\\\Http\\\\Controllers\\\\[\w\\\\]*Controller(?: as \w+)?;$/m', (string) file_get_contents($file)) === 1) {
            $offenders[] = substr($file, strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});

test('the Inertia response is imported as InertiaResponse', function (): void {
    $root = dirname(__DIR__, 2);
    $pattern = '/^use Inertia\\\\Response(?! as InertiaResponse;)(?: as \w+)?;$/m';
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)) as $file) {
        $file = InstancesOf::one($file, SplFileInfo::class);
        if ($file->getExtension() === 'php' && preg_match($pattern, (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([])
        ->and(preg_match($pattern, "use Inertia\\Response;\n"))->toBe(1)
        ->and(preg_match($pattern, "use Inertia\\Response as Page;\n"))->toBe(1)
        ->and(preg_match($pattern, "use Inertia\\Response as InertiaResponse;\n"))->toBe(0)
        ->and(preg_match($pattern, "use Illuminate\\Http\\Response;\n"))->toBe(0);
});

it('detects a route parameter that is not camelCase', function (string $source, string $reason): void {
    $violations = new RouteParameterGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'routes')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason);
})->with([
    'snake case' => ["<?php\nRoute::get('/orders/{order}/lines/{order_line}', Order\\Lines\\ShowController::class);\n", '2: the route parameter {order_line} is not camelCase: name it {orderLine}'],
    'kebab case with a binding field' => ["<?php\nRoute::delete('/tax-rates/{tax-rate:uuid}', TaxRates\\DestroyController::class);\n", '2: the route parameter {tax-rate} is not camelCase: name it {taxRate}'],
    'an optional parameter in a prefix' => ["<?php\nRoute::prefix('{Tenant?}/settings')->group(function (): void {});\n", '2: the route parameter {Tenant} is not camelCase: name it {tenant}'],
    'a broadcast channel' => ["<?php\nBroadcast::channel('stock-level.{stock-level}', static fn (): bool => true);\n", '2: the route parameter {stock-level} is not camelCase: name it {stockLevel}'],
]);

it('names every route parameter in camelCase', function (): void {
    $guard = new RouteParameterGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'routes');
    $accepted = "<?php\nRoute::get('/orders/{order}/lines/{line:uuid}/{variantId?}', Order\\ShowController::class)->where('line', '[a-z]{2,8}');\nRoute::get('shipments/track/{code}', Shipments\\ShowController::class);\n";

    expect($guard->inSource($accepted))->toBe([])
        ->and($guard->violations())->toBe([]);
});

it('detects an Inertia page without a file', function (string $source, string $reason): void {
    $violations = new InertiaPageGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('resources/js/Pages');
})->with([
    'a page that does not exist' => ["<?php\nuse Inertia\\Inertia;\nreturn Inertia::render('Order/DoesNotExist', []);\n", '3: the Inertia page Order/DoesNotExist has no file: add resources/js/Pages/Order/DoesNotExist.tsx'],
    'the helper' => ["<?php\nreturn inertia('Missing/Page');\n", '2: the Inertia page Missing/Page has no file'],
    'a page in a route default' => ["<?php\nRoute::get('/x', InertiaController::class)->defaults('component', 'MissingPage')->defaults('props', []);\n", '2: the Inertia page MissingPage has no file'],
    'a page named by a variable' => ["<?php\nuse Inertia\\Inertia;\nreturn Inertia::render(\$page);\n", '3: an Inertia page that is not named by a string'],
    'a page named by a built string' => ["<?php\nuse Inertia\\Inertia;\nreturn Inertia::render('Order/' . \$view, ['a' => 1]);\n", '3: an Inertia page that is not named by a string'],
]);

it('renders only Inertia pages that have a file', function (): void {
    $guard = new InertiaPageGuard(new PhpFileInspector(), dirname(__DIR__, 2));
    $accepted = "<?php\nuse Inertia\\Inertia;\nInertia::render('Dashboard');\nInertia::flash('success', 'x');\n\$view->render('Missing');\ninertia()->share('a', 1);\n\$route->defaults('props', []);\n";

    expect($guard->inSource($accepted))->toBe([])
        ->and($guard->violations())->toBe([]);
});
