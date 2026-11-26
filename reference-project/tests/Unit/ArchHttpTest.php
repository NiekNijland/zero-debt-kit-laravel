<?php

declare(strict_types=1);

uses()->group('arch');

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('only invokable controllers')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveMethod('__invoke');
