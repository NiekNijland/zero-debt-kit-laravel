<?php

declare(strict_types=1);

use Tests\Support\Architecture\HttpRefusalGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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
