<?php

declare(strict_types=1);

use App\Modules\Shipments\Contracts\ShippingMethod;
use App\Providers\AppServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\ServiceProvider;
use Sentry\Laravel\Facade as Sentry;
use Spatie\LaravelData\Data;
use Tests\Support\ArchNamespaces;

uses()->group('arch');

// An arch() rule over a namespace with a wildcard throws when no folder matches: the rule is on from the first day and checks the first class when it arrives.
$app = new ArchNamespaces(dirname(__DIR__, 2), 'App', 'app');

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
    ->expect($app->existing('App\Modules\*\Enums'))
    ->toBeEnums();

arch('exceptions are throwable')
    ->expect($app->existing('App\Modules\*\Exceptions'))
    ->classes()
    ->toImplement(Throwable::class);

arch('commands extend Command and handle')
    ->expect($app->existing('App\Modules\*\Commands'))
    ->classes()
    ->toExtend(Command::class)
    ->toHaveMethod('handle');

arch('mailables extend Mailable')
    ->expect($app->existing('App\Modules\*\Mail'))
    ->classes()
    ->toExtend(Mailable::class);

arch('notifications extend Notification')
    ->expect($app->existing('App\Modules\*\Notifications'))
    ->classes()
    ->toExtend(Notification::class);

arch('jobs are queued')
    ->expect($app->existing('App\Modules\*\Jobs'))
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
    ->expect($app->existing('App\Modules\*\Actions'))
    ->toHaveMethod('handle');

arch('shipping methods are named XShippingService')
    ->expect('App')
    ->classes()
    ->implementing(ShippingMethod::class)
    ->toHaveSuffix('ShippingService');

arch('errors reach Sentry through report(), not through its facade')
    ->expect(Sentry::class)
    ->toOnlyBeUsedIn([AppServiceProvider::class]);

arch('every Data object carries the Data suffix')
    ->expect('App')
    ->classes()
    ->extending(Data::class)
    ->toHaveSuffix('Data');

arch('a class in a Data namespace carries the Data suffix')
    ->expect($app->existing('App\Modules\*\Data'))
    ->classes()
    ->toHaveSuffix('Data');
