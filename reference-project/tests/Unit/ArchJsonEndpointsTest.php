<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpCallReader;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\PhpNewArgumentTypes;
use Tests\Support\ClassNames;
use Tests\Support\Globs;

uses()->group('arch');

test('JSON endpoints return a Data object: no response()->json, nothing but Data in a controller JsonResponse, no array return', function (): void {
    $root = dirname(__DIR__, 2);
    $findings = static function (string $source): array {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $name = static fn (?PhpToken $token): string => $token?->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) === true ? strtolower(ArchChecks::lastSegment($token->text)) : '';
        $lines = [];

        foreach ($tokens as $index => $token) {
            $isHelper = $name($token) === 'response' && ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION]) !== true && ($tokens[$index + 1] ?? null)?->text === '(' && ($tokens[$index + 2] ?? null)?->text === ')' && ($tokens[$index + 3] ?? null)?->is(T_OBJECT_OPERATOR) === true && $name($tokens[$index + 4] ?? null) === 'json';
            $isFacade = $name($token) === 'response' && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true && $name($tokens[$index + 2] ?? null) === 'json';

            if ($isHelper || $isFacade) {
                $lines[] = $token->line;
            }
        }

        return $lines;
    };
    $notData = static fn (?string $type): bool => $type === null || array_any(
        explode('|', $type),
        static fn (string $part): bool => ! is_a($part, BaseData::class, true) && ! is_a($part, BaseDataCollectable::class, true),
    );
    $payloadsOfOthers = [
        App\Http\Controllers\Carrier\Webhooks\ReceiveController::class,
        App\Http\Controllers\Payment\Webhooks\ReceiveController::class,
    ];
    $controllers = $root . '/app/Http/Controllers';
    $inspector = new PhpFileInspector();
    $offenders = [];
    $listed = [];

    foreach ($inspector->phpFiles($root . '/app') as $path) {
        $isController = str_starts_with($path, $controllers . '/');
        $lines = $findings((string) file_get_contents($path));

        if ($lines === [] && ! $isController) {
            continue;
        }

        $types = array_keys($inspector->declaredTypes($path));
        $returns = $isController && $types !== [] ? ArchChecks::invokeReturnType(ClassNames::of($types[0])) : null;
        $isPage = $returns instanceof ReflectionUnionType && array_any($returns->getTypes(), static fn (ReflectionType $type): bool => $type instanceof ReflectionNamedType && $type->getName() === Inertia\Response::class);

        if ($isController && ! $isPage) {
            foreach (PhpNewArgumentTypes::firstArgumentTypesOfNew($path, JsonResponse::class) as $argument) {
                if ($notData($argument['type'])) {
                    $lines[] = $argument['line'];
                }
            }
        }

        if ($lines !== [] && array_intersect($types, $payloadsOfOthers) !== []) {
            $listed = [...$listed, ...array_intersect($types, $payloadsOfOthers)];

            continue;
        }

        foreach ($lines as $line) {
            $offenders[] = substr($path, strlen($root) + 1) . ' line ' . $line;
        }

        if ($returns instanceof ReflectionNamedType && $returns->getName() === 'array') {
            $offenders[] = $types[0] . ' returns an array';
        }
    }

    $typeOf = static function (string $source): array {
        $path = sys_get_temp_dir() . '/json-response-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($path, $source);

        try {
            return array_column(PhpNewArgumentTypes::firstArgumentTypesOfNew($path, JsonResponse::class), 'type');
        } finally {
            unlink($path);
        }
    };
    $wrap = static fn (string $body, string $parameters = ''): string => "<?php namespace App\\Http\\Controllers; use Illuminate\\Http\\JsonResponse; use App\\Modules\\Products\\Data\\ProductData as XData; use Illuminate\\Support\\Arr; final class C { public function __construct(private readonly \\stdClass \$service) {} public function __invoke({$parameters}): JsonResponse { {$body} } }";

    expect($offenders)->toBe([])
        ->and(array_values(array_unique($listed)))->toEqualCanonicalizing($payloadsOfOthers)
        ->and($findings('<?php return response()->json([]); return \\response()->json($x); return Response::json($x); return \\Illuminate\\Support\\Facades\\Response::json($x);'))->toHaveCount(4)
        ->and($findings('<?php return new JsonResponse($data, 201); return new JsonResponse(status: 201); return WebhookResponse::json($x); return $this->response()->json();'))->toBe([])
        ->and($typeOf($wrap('return new JsonResponse([\'a\' => 1]);')))->toBe(['array'])
        ->and($typeOf($wrap('$rows = [1]; return new JsonResponse($rows);')))->toBe(['array'])
        ->and($typeOf($wrap('return new JsonResponse(new XData(1), 409);')))->toBe(['App\\Modules\\Products\\Data\\ProductData'])
        ->and($typeOf($wrap('$data = XData::from($x); return new JsonResponse($data);')))->toBe(['App\\Modules\\Products\\Data\\ProductData'])
        ->and($typeOf($wrap('return new JsonResponse(data: XData::from($x));')))->toBe(['App\\Modules\\Products\\Data\\ProductData'])
        ->and($notData($typeOf($wrap('return new JsonResponse(XData::collect($x));'))[0]))->toBeTrue()
        ->and($typeOf($wrap('return new JsonResponse(XData::collect($x, \\Spatie\\LaravelData\\DataCollection::class));')))->toBe(['Spatie\\LaravelData\\DataCollection'])
        ->and($typeOf($wrap('return new JsonResponse(Arr::only($x, [\'a\']));')))->toBe([null])
        ->and($typeOf($wrap('return new JsonResponse($x);')))->toBe([null])
        ->and($typeOf($wrap('return new JsonResponse($x);', 'XData $x')))->toBe(['App\\Modules\\Products\\Data\\ProductData'])
        ->and($typeOf($wrap('return new JsonResponse($this->service->run());')))->toBe([null])
        ->and($typeOf($wrap('return new JsonResponse(status: 201); return new JsonResponse();')))->toBe([])
        ->and($notData('array'))->toBeTrue()
        ->and($notData(null))->toBeTrue()
        ->and($notData(DataCollection::class . '|' . PaginatedDataCollection::class))->toBeFalse()
        ->and($notData(DataCollection::class . '|array'))->toBeTrue();
});

test('a controller of a POST, PUT, PATCH or DELETE route never returns a bare Data object: the status is explicit', function (): void {
    $root = dirname(__DIR__, 2);
    $bare = static function (?ReflectionType $type): bool {
        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [$type] as $part) {
            if ($part instanceof ReflectionNamedType && ! $part->isBuiltin() && (
                is_subclass_of($part->getName(), BaseData::class)
                || is_subclass_of($part->getName(), BaseDataCollectable::class)
                || in_array($part->getName(), [DataCollection::class, PaginatedDataCollection::class], true)
            )) {
                return true;
            }
        }

        return false;
    };
    $offenders = [];
    $checked = 0;

    foreach (Globs::of($root . '/routes' . '/*.php') as $file) {
        foreach (PhpCallReader::nonGetRouteControllers($file) as $route) {
            $checked++;

            if (class_exists($route['controller']) && $bare(ArchChecks::invokeReturnType($route['controller']))) {
                $offenders[] = basename($file) . ' line ' . $route['line'] . ': ' . $route['controller'];
            }
        }
    }

    $fixture = tempnam(sys_get_temp_dir(), 'routes');
    file_put_contents($fixture, '<?php use Illuminate\Support\Facades\Route; Route::get("a", A::class); Route::post("b", B::class)->name("b"); Route::match(["get", "head"], "c", C::class); Route::match(["put"], "d", D::class); Route::middleware("x")->group(function () { Route::delete("e", E::class); Route::patch("f", fn () => 1); });');
    $fixtureRoutes = PhpCallReader::nonGetRouteControllers($fixture);
    unlink($fixture);

    expect($checked)->toBeGreaterThan(0)
        ->and($offenders)->toBe([])
        ->and(array_column($fixtureRoutes, 'controller'))->toBe(['B', 'D', 'E'])
        ->and($bare((new ReflectionFunction(static fn (): App\Modules\Products\Data\ProductImageData => throw new LogicException()))->getReturnType()))->toBeTrue()
        ->and($bare((new ReflectionFunction(static fn (): JsonResponse|DataCollection => throw new LogicException()))->getReturnType()))->toBeTrue()
        ->and($bare((new ReflectionFunction(static fn (): JsonResponse => throw new LogicException()))->getReturnType()))->toBeFalse()
        ->and($bare((new ReflectionFunction(static fn (): Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse => throw new LogicException()))->getReturnType()))->toBeFalse();
});
