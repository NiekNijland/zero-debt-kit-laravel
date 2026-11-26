<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Tests\Support\Architecture\BareDataReturnGuard;

uses()->group('arch');

dataset('routes that write', [
    'a post route' => ['<?php Route::post("b", B::class)->name("b");', ['B']],
    'a match with put' => ['<?php Route::match(["put"], "d", D::class);', ['D']],
    'a delete route in a group' => ['<?php Route::middleware("x")->group(function () { Route::delete("e", E::class); });', ['E']],
    'a closure is no controller' => ['<?php Route::patch("f", fn () => 1);', []],
    'a get route and a match with get and head' => ['<?php Route::get("a", A::class); Route::match(["get", "head"], "c", C::class);', []],
]);

it('reads the controller of every route that writes and of no other', function (string $source, array $controllers): void {
    expect(new BareDataReturnGuard(dirname(__DIR__, 2), 'routes')->writingControllers($source))->toBe($controllers);
})->with('routes that write');

it('calls a bare Data object, or a union that holds a collection, bare, and a JsonResponse or a redirect not', function (): void {
    $guard = new BareDataReturnGuard(dirname(__DIR__, 2), 'routes');

    expect($guard->isBare(new ReflectionFunction(static fn (): Data => throw new LogicException())->getReturnType()))->toBeTrue()
        ->and($guard->isBare(new ReflectionFunction(static fn (): JsonResponse|DataCollection => throw new LogicException())->getReturnType()))->toBeTrue()
        ->and($guard->isBare(new ReflectionFunction(static fn (): JsonResponse => throw new LogicException())->getReturnType()))->toBeFalse()
        ->and($guard->isBare(new ReflectionFunction(static fn (): Illuminate\Http\RedirectResponse|JsonResponse => throw new LogicException())->getReturnType()))->toBeFalse();
});

it('gives every route that writes an explicit status', function (): void {
    expect(new BareDataReturnGuard(dirname(__DIR__, 2), 'routes')->violations())->toBe([]);
});
