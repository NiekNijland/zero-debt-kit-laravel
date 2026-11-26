<?php

declare(strict_types=1);

use Tests\Support\ArchNamespaces;

uses()->group('arch');

// An arch() rule over a namespace with a wildcard throws when no folder matches: the rule is on from the first day and checks the first class when it arrives.
$app = new ArchNamespaces(dirname(__DIR__, 2), 'App', 'app');

arch('controllers are final readonly')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('actions are final readonly')
    ->expect($app->existing('App\Modules\*\Actions'))
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();
