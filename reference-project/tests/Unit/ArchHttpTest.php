<?php

declare(strict_types=1);

use Tests\Support\Architecture\ControllerAuthorizationGuard;
use Tests\Support\Architecture\ControllerNameGuard;
use Tests\Support\Architecture\HttpRefusalGuard;
use Tests\Support\Architecture\ImportSpellingGuard;
use Tests\Support\Architecture\InertiaPageGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RedirectGuard;
use Tests\Support\Architecture\RequestValidationGuard;
use Tests\Support\Architecture\RouteParameterGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('only invokable controllers')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveMethod('__invoke');

dataset('requests validated by hand', [
    'validate on the request' => ['<?php $request->validate([]);', 1],
    'validateWithBag and the helper' => ['<?php $request->validateWithBag(\'x\', []); request()->validate([]);', 2],
    'the validator facade' => ['<?php Validator::make($a, $b)->validate(); \Illuminate\Support\Facades\Validator::validate($a, $b);', 2],
]);

it('detects a request that is validated outside a form request', function (string $source, int $count): void {
    $violations = new RequestValidationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [])->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('write the rules in a form request');
})->with('requests validated by hand');

it('accepts a validate call that is not on the request or the validator facade', function (): void {
    $guard = new RequestValidationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', []);

    expect($guard->inSource('<?php $a = Auth::guard(\'web\')->validate($credentials); $b = $rule->validate($a, $b, $fail); $this->validate($input);'))->toBe([]);
});

it('skips the form requests and reads a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Http/Requests/A.php' => "<?php\n\$request->validate([]);\n", 'app/Other.php' => "<?php\n\$request->validate([]);\n"], prefix: '.checkout-');

    try {
        $violations = new RequestValidationGuard(new PhpFileInspector(), $root, 'app', ['app/Http/Requests/'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Other.php: line 2: a request is validated by hand');
});

it('validates a request in a form request and nowhere else', function (): void {
    expect(new RequestValidationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', ['app/Http/Requests/'])->violations())->toBe([]);
});

dataset('actions that check or refuse access', [
    'Gate::authorize' => ['<?php Gate::authorize(\'update\', $a);', 1],
    'authorize through a chain and on this' => ['<?php Gate::forUser($u)->authorize(\'update\', $a); $this->authorize(\'x\');', 2],
    'abort_unless with a permission check, abort_if with 403' => ['<?php abort_unless($a->can(\'x\'), 404); abort_if($a, 403); abort_unless($a, Response::HTTP_FORBIDDEN);', 3],
    'abort_unless with a message' => ['<?php abort_unless($a, 403, \'No access\'); abort_if($b, Response::HTTP_FORBIDDEN, \'x\');', 2],
    'an ownership check' => ['<?php abort_unless($a->user_id === $b->id, 404);', 1],
    'abort with 401 and 403' => ['<?php abort(403); abort(Response::HTTP_FORBIDDEN, \'x\'); \\abort(401);', 3],
    'an HttpException with 401 or 403' => ['<?php throw new HttpException(401, \'x\'); throw new \\Symfony\\Component\\HttpKernel\\Exception\\HttpException(Response::HTTP_FORBIDDEN);', 2],
    'hand-built refusals' => ['<?php throw new AuthorizationException(); throw new AccessDeniedHttpException(); throw new UnauthorizedHttpException(\'Bearer\');', 3],
    'throw_if and throw_unless' => ['<?php throw_unless($a, AccessDeniedHttpException::class, \'x\'); throw_if($b, \\Illuminate\\Auth\\Access\\AuthorizationException::class); \\throw_if($c, HttpException::class, 403);', 3],
]);

it('detects an action that checks or refuses access itself', function (string $source, int $count): void {
    $violations = new ControllerAuthorizationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers')->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('put the permission on the route, the rule about a model in its policy');
})->with('actions that check or refuse access');

dataset('actions that refuse nothing', [
    'a not found' => ['<?php abort_unless($model instanceof Model, 404); abort_if($a === null, Response::HTTP_NOT_FOUND); abort(404);'],
    'other statuses' => ['<?php throw new HttpException(409); throw new NotFoundHttpException(); $a = new Response(403);'],
    'throw_if with other exceptions' => ['<?php throw_unless($a, NotFoundHttpException::class); throw_if($b, HttpException::class, 409); throw_if($c, $exception);'],
    'a gate read' => ['<?php $a = Gate::allows(\'update\', $b); $c = Gate::forUser($u)->allows(\'view\', $d);'],
]);

it('accepts a not found, other statuses and a read of the gate', function (string $source): void {
    expect(new ControllerAuthorizationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers')->inSource($source))->toBe([]);
})->with('actions that refuse nothing');

it('reads the controllers below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Http/Controllers/A.php' => "<?php\nGate::authorize('x');\n"], prefix: '.checkout-');

    try {
        $violations = new ControllerAuthorizationGuard(new PhpFileInspector(), $root, 'app/Http/Controllers')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Http/Controllers/A.php: line 2: an action checks or refuses access itself');
});

it('checks authorization on the route, not in the action of a controller', function (): void {
    expect(new ControllerAuthorizationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers')->violations())->toBe([]);
});

dataset('HTTP refusals outside the HTTP layer', [
    'abort with 403 and 401' => ['<?php abort(403); \\abort(401, \'x\'); abort(Response::HTTP_FORBIDDEN);', 3],
    'abort_if and abort_unless' => ['<?php abort_if($a, 403); abort_unless($b, Response::HTTP_UNAUTHORIZED, \'x\');', 2],
    'HTTP exceptions' => ['<?php throw new AccessDeniedHttpException(); throw new UnauthorizedHttpException(\'Bearer\'); throw new HttpException(403);', 3],
    'throw_if and throw_unless' => ['<?php throw_unless($a, AccessDeniedHttpException::class); throw_if($b, HttpException::class, Response::HTTP_FORBIDDEN);', 2],
]);

it('detects an HTTP refusal outside the HTTP layer', function (string $source, int $count): void {
    $violations = new HttpRefusalGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [])->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('throw a domain exception that extends the authorization exception');
})->with('HTTP refusals outside the HTTP layer');

it('accepts a not found, a conflict and a domain exception', function (): void {
    $guard = new HttpRefusalGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', []);

    expect($guard->inSource('<?php abort(404); abort_if($a, 422); abort_unless($b, Response::HTTP_NOT_FOUND); throw new HttpException(409);'))->toBe([])
        ->and($guard->inSource('<?php throw new AuthorizationException(); throw new OrderAlreadyShippedException(); throw_unless($a, NotFoundHttpException::class);'))->toBe([]);
});

it('skips the controllers and the middleware and reads a checkout under a dot directory', function (): void {
    $source = "<?php\nabort(403);\n";
    $root = SourceSandbox::create(['app/Http/Controllers/A.php' => $source, 'app/Http/Middleware/B.php' => $source, 'app/Domain/C.php' => $source], prefix: '.checkout-');

    try {
        $violations = new HttpRefusalGuard(new PhpFileInspector(), $root, 'app', ['app/Http/Controllers/', 'app/Http/Middleware/'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Domain/C.php: line 2: an HTTP refusal outside the HTTP layer');
});

it('refuses with a typed exception outside the HTTP layer', function (): void {
    expect(new HttpRefusalGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', ['app/Http/Controllers/', 'app/Http/Middleware/'])->violations())->toBe([]);
});

dataset('redirects through the helper', [
    'the helper route' => ['<?php return redirect()->route(\'home\');', 1],
    'a chain on a new line' => ['<?php return redirect()' . "\n" . '->route(\'login\')->withErrors([]);', 1],
    'back with a fallback and the facade' => ['<?php return redirect()->back(fallback: \'/\'); return Redirect::route(\'home\');', 2],
    'backslashes and capitals' => ['<?php return \\redirect()->route(\'home\'); return \\Illuminate\\Support\\Facades\\Redirect::route(\'home\'); return \\Redirect::back(); return REDIRECT()->back();', 4],
    'an aliased facade' => ['<?php use Illuminate\\Support\\Facades\\Redirect as R; return R::route(\'home\');', 1],
]);

it('detects a redirect that goes through redirect()', function (string $source, int $count): void {
    $violations = new RedirectGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('write to_route(\'name\') for a named route and back() to go back');
})->with('redirects through the helper');

it('accepts to_route(), back() and the other redirect methods', function (): void {
    $guard = new RedirectGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app']);

    expect($guard->inSource('<?php return to_route(\'home\'); return back(); return redirect()->to($url); return redirect()->away($url); return redirect()->intended(\'/\'); return redirect(\'/\');'))->toBe([]);
});

it('reads the folders below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/A.php' => "<?php\nreturn redirect()->route('a');\n", 'routes/web.php' => "<?php\nreturn to_route('a');\n"], prefix: '.checkout-');

    try {
        $violations = new RedirectGuard(new PhpFileInspector(), $root, ['app', 'routes'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/A.php: line 2: a redirect through redirect()');
});

it('redirects with to_route() and back() in controllers and route files', function (): void {
    expect(new RedirectGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'])->violations())->toBe([]);
});

dataset('controller names that fail', [
    'in the controller root' => ['App\Http\Controllers\ShowController', 'a controller sits in the controller root'],
    'without the suffix' => ['App\Http\Controllers\Order\Show', 'a controller is named <Verb>Controller'],
    'a verb that is not on the list' => ['App\Http\Controllers\Order\FooController', 'the verb Foo is not one of Show, Store'],
]);

it('detects a controller that is not named <Verb>Controller in a folder of what it acts on', function (string $type, string $words): void {
    $guard = new ControllerNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', ['Show', 'Store'], 'App\Http\Controllers');

    expect($guard->inType($type))->toContain($words);
})->with('controller names that fail');

it('accepts a verb controller in a folder, and names a verb that no controller carries and a folder with no controller', function (): void {
    $guard = new ControllerNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', ['Show', 'Store'], 'App\Http\Controllers');
    $root = SourceSandbox::create([
        'c/Order/ShowController.php' => "<?php\nnamespace App\\Http\\Controllers\\Order;\nfinal class ShowController {}\n",
        'empty/.gitkeep' => '',
    ], prefix: '.checkout-');

    try {
        $stale = new ControllerNameGuard(new PhpFileInspector(), $root, 'c', ['Show', 'Store'], 'App\Http\Controllers')->violations();
        $empty = new ControllerNameGuard(new PhpFileInspector(), $root, 'empty', ['Show'], 'App\Http\Controllers')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($guard->inType('App\Http\Controllers\Order\ShowController'))->toBeNull()
        ->and($stale)->toBe(['the verb Store is on the list and no controller carries it: remove it from the list'])
        ->and($empty[0])->toContain('no controller was read');
});

it('names every controller <Verb>Controller in a folder named after what it acts on', function (): void {
    $verbs = [
        'Cancel', 'Create', 'Destroy', 'Download', 'Edit', 'Export', 'Import', 'Index', 'Receive', 'Restore', 'Search', 'Send', 'Show', 'Store',
        'Update',
    ];

    expect(new ControllerNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', $verbs, 'App\Http\Controllers')->violations())->toBe([]);
});

dataset('imports in the wrong spelling', [
    'a controller by its own import' => ['controller', "<?php\nuse App\\Http\\Controllers\\Order\\ShowController;\n", 'import the folder and write Folder\Noun\VerbController::class'],
    'a controller under an alias' => ['controller', "<?php\nuse App\\Http\\Controllers\\Order\\ShowController as Show;\n", 'a route file imports a controller'],
    'the Inertia response as Response' => ['page', "<?php\nuse Inertia\\Response;\n", 'import it as InertiaResponse'],
    'the Inertia response under another alias' => ['page', "<?php\nuse Inertia\\Response as Page;\n", 'import it as InertiaResponse'],
]);

it('detects a controller import in a route file and the Inertia response under another name', function (string $kind, string $source, string $words): void {
    $guard = new ImportSpellingGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'App\Http\Controllers', 'Inertia\Response', 'InertiaResponse');
    $violations = $kind === 'controller' ? $guard->controllerImportInSource($source) : $guard->pageImportInSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('imports in the wrong spelling');

it('accepts the folder import and the Inertia response as InertiaResponse', function (): void {
    $guard = new ImportSpellingGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'App\Http\Controllers', 'Inertia\Response', 'InertiaResponse');

    expect($guard->controllerImportInSource("<?php\nuse App\\Http\\Controllers\\Order;\nuse App\\Http\\Requests\\StoreRequest;\n"))->toBe([])
        ->and($guard->pageImportInSource("<?php\nuse Inertia\\Response as InertiaResponse;\nuse Illuminate\\Http\\Response;\n"))->toBe([]);
});

it('reads the Inertia imports below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/A.php' => "<?php\nuse Inertia\\Response;\n", 'app/B.php' => "<?php\nuse Inertia\\Response as InertiaResponse;\n"], prefix: '.checkout-');

    try {
        $violations = new ImportSpellingGuard(new PhpFileInspector(), $root, 'App\Http\Controllers', 'Inertia\Response', 'InertiaResponse')->pageImports('app');
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/A.php: the Inertia response is imported under another name');
});

it('imports a controller folder in the route files and the Inertia response as InertiaResponse', function (): void {
    $guard = new ImportSpellingGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'App\Http\Controllers', 'Inertia\Response', 'InertiaResponse');

    expect($guard->controllerImports([...$guard->files('routes/*.php'), 'bootstrap/app.php']))->toBe([])
        ->and($guard->pageImports('app'))->toBe([]);
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
    $violations = new InertiaPageGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'resources/js/Pages')->inSource($source);

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
    $guard = new InertiaPageGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'resources/js/Pages');
    $accepted = "<?php\nuse Inertia\\Inertia;\nInertia::render('Dashboard');\nInertia::flash('success', 'x');\n\$view->render('Missing');\ninertia()->share('a', 1);\n\$route->defaults('props', []);\n";

    expect($guard->inSource($accepted))->toBe([])
        ->and($guard->violations())->toBe([]);
});
