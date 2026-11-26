<?php

declare(strict_types=1);

use App\Support\RouteMatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

uses()->group('arch');

arch('routes are registered only in routes/ and bootstrap/app.php')
    ->expect([Route::class, Router::class])
    ->toOnlyBeUsedIn([RouteMatcher::class, 'Tests']);
