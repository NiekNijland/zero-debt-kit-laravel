<?php

declare(strict_types=1);

use Tests\Support\Architecture\ControllerAuthorizationGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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
