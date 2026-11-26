<?php

declare(strict_types=1);

use Tests\Support\ArchNamespaces;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('namespaces with no folder', [
    'a wildcard with no matching folder' => ['App\Modules\*\Exceptions'],
    'a plain namespace with no folder' => ['App\Modules\Orders\Exceptions'],
    'a wildcard whose module has another folder' => ['App\Modules\*\Mail'],
]);

it('answers an empty list for a namespace no folder matches, so an arch() rule over it passes', function (string $namespace): void {
    $root = SourceSandbox::create(['app/Modules/Orders/Actions/Cancel.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $answer = new ArchNamespaces($root, 'App', 'app')->existing($namespace);
    } finally {
        SourceSandbox::remove($root);
    }

    expect($answer)->toBe([]);
})->with('namespaces with no folder');

dataset('namespaces with a folder', [
    'a wildcard that matches a module folder' => ['App\Modules\*\Actions'],
    'a plain namespace' => ['App\Modules\Orders\Actions'],
    'the root namespace' => ['App\Modules'],
]);

it('answers the namespace itself when a folder matches it', function (string $namespace): void {
    $root = SourceSandbox::create(['app/Modules/Orders/Actions/Cancel.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $answer = new ArchNamespaces($root, 'App', 'app')->existing($namespace);
    } finally {
        SourceSandbox::remove($root);
    }

    expect($answer)->toBe($namespace);
})->with('namespaces with a folder');

it('refuses a namespace outside the application, which a typo would otherwise turn into a rule that checks nothing', function (): void {
    expect(fn (): array|string => new ArchNamespaces(dirname(__DIR__, 2), 'App', 'app')->existing('Apps\Modules\*\Actions'))
        ->toThrow(InvalidArgumentException::class, 'is not below App');
});
