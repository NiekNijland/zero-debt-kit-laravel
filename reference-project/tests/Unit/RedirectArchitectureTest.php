<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RedirectGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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
