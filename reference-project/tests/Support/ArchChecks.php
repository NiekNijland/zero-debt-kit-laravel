<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;
use Tests\Support\Architecture\PhpFileInspector;

final class ArchChecks
{
    /**
     * @param class-string $class
     */
    public static function sourceOf(string $class): string
    {
        return substr((string) new ReflectionClass($class)->getFileName(), strlen(dirname(__DIR__, 2)) + 1);
    }

    public static function lastSegment(?string $name): string
    {
        return basename(str_replace('\\', '/', $name ?? ''));
    }

    private const string OWNERSHIP_CHECK = '/Gate\s*::|->\s*can\s*\(|->\s*cannot\s*\(|user_id|isOwnedBy|belongsToCustomer|getCurrentId|getKey\s*\(\s*\)/';

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     */
    public static function readsConfigUntyped(array $calls): bool
    {
        foreach ($calls as $call) {
            $written = ltrim($call['written'], '\\');

            if ($call['kind'] === 'function' && ! str_contains($written, '\\') && mb_strtolower($written) === 'config') {
                return true;
            }

            if ($call['kind'] === 'static' && in_array($written, ['Config', Config::class], true) && $call['name'] === 'get') {
                return true;
            }

            if ($call['kind'] === 'method' && $call['name'] === 'get' && $call['receiver'] === '->config') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @return list<int>
     */
    public static function containerCallLines(array $calls): array
    {
        $lines = [];

        foreach ($calls as $call) {
            $written = ltrim($call['written'], '\\');

            if ($call['kind'] === 'function' && ! str_contains($written, '\\') && in_array(mb_strtolower($written), ['app', 'resolve'], true)) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'static' && in_array($written, ['App', App::class], true) && in_array($call['name'], ['make', 'makewith', 'call', 'resolve'], true)) {
                $lines[] = $call['classLine'];
            }

            if ($call['kind'] === 'method' && in_array($call['name'], ['make', 'makewith', 'call', 'resolve'], true) && in_array($call['receiver'], ['->container', '->app', '$container', '$app'], true)) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'static' && $written === 'Container' && $call['name'] === 'getinstance') {
                $lines[] = $call['classLine'];
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @return list<int>
     */
    public static function requestValidationLines(array $calls): array
    {
        $lines = [];

        foreach ($calls as $call) {
            if ($call['kind'] === 'static' && in_array(self::lastSegment($call['written']), ['Validator', 'ValidatorFacade'], true) && in_array($call['name'], ['make', 'validate'], true)) {
                $lines[] = $call['classLine'];
            }

            if ($call['kind'] === 'method' && in_array($call['name'], ['validate', 'validatewithbag'], true) && in_array($call['receiver'], ['$request', 'request()', '->request()', '::request()'], true)) {
                $lines[] = $call['line'];
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @return list<int>
     */
    public static function authorizationLines(array $calls): array
    {
        $lines = [];

        foreach ($calls as $call) {
            if (in_array($call['kind'], ['method', 'nullsafe', 'static'], true) && $call['name'] === 'authorize') {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'function' && ltrim(mb_strtolower($call['written']), '\\') === 'abort') {
                $status = trim($call['arguments'][0] ?? '', '() ');

                if (in_array($status, ['401', '403'], true) || str_ends_with($status, 'HTTP_FORBIDDEN') || str_ends_with($status, 'HTTP_UNAUTHORIZED')) {
                    $lines[] = $call['line'];
                }
            }

            if ($call['kind'] === 'function' && in_array(mb_strtolower($call['written']), ['abort_if', 'abort_unless'], true)) {
                $status = trim($call['arguments'][1] ?? '', '() ');
                $denies = in_array($status, ['401', '403'], true) || str_ends_with($status, 'HTTP_FORBIDDEN') || str_ends_with($status, 'HTTP_UNAUTHORIZED');
                $ownership = preg_match(self::OWNERSHIP_CHECK, implode(' ', $call['arguments'])) === 1;

                if ($denies || $ownership) {
                    $lines[] = $call['line'];
                }
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * The lines that build a 401 or 403 answer by hand: an AuthorizationException, an AccessDeniedHttpException, an UnauthorizedHttpException or an
     * HttpException with 401 or 403, with `new` or through `throw_if()` and `throw_unless()`.
     *
     * @param list<array{line: int, class: string, written: string, arguments: list<string>}> $instantiations
     * @return list<int>
     */
    public static function deniedInstantiationLines(array $instantiations): array
    {
        $lines = [];

        foreach ($instantiations as $instantiation) {
            $class = self::lastSegment($instantiation['class']);
            $status = trim($instantiation['arguments'][0] ?? '', '() ');
            $denies = in_array($status, ['401', '403'], true) || str_ends_with($status, 'HTTP_FORBIDDEN') || str_ends_with($status, 'HTTP_UNAUTHORIZED');

            if (in_array($class, ['AuthorizationException', 'AccessDeniedHttpException', 'UnauthorizedHttpException'], true) || ($class === 'HttpException' && $denies)) {
                $lines[] = $instantiation['line'];
            }
        }

        return $lines;
    }

    /**
     * The lines outside the HTTP layer that refuse with an HTTP answer instead of a typed exception: `abort()`, `abort_if()` or `abort_unless()` with 401
     * or 403, and an AccessDeniedHttpException, an UnauthorizedHttpException or an HttpException with 401 or 403. A refusal there is a domain exception
     * that extends AuthorizationException, which Laravel answers with 403.
     *
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @param list<array{line: int, class: string, written: string, arguments: list<string>}> $instantiations
     * @return list<int>
     */
    public static function httpRefusalLines(array $calls, array $instantiations): array
    {
        $lines = [];

        foreach ($calls as $call) {
            $function = $call['kind'] === 'function' ? ltrim(mb_strtolower($call['written']), '\\') : '';
            $status = match ($function) {
                'abort' => $call['arguments'][0] ?? '',
                'abort_if', 'abort_unless' => $call['arguments'][1] ?? '',
                default => '',
            };

            if (self::deniesAccess($status)) {
                $lines[] = $call['line'];
            }
        }

        foreach ($instantiations as $instantiation) {
            $class = self::lastSegment($instantiation['class']);

            if (in_array($class, ['AccessDeniedHttpException', 'UnauthorizedHttpException'], true) || ($class === 'HttpException' && self::deniesAccess($instantiation['arguments'][0] ?? ''))) {
                $lines[] = $instantiation['line'];
            }
        }

        sort($lines);

        return $lines;
    }

    private static function deniesAccess(string $status): bool
    {
        $status = trim($status, '() ');

        return in_array($status, ['401', '403'], true) || str_ends_with($status, 'HTTP_FORBIDDEN') || str_ends_with($status, 'HTTP_UNAUTHORIZED');
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @return list<int>
     */
    public static function redirectLines(array $calls): array
    {
        $lines = [];

        foreach ($calls as $call) {
            if ($call['kind'] === 'method' && in_array($call['name'], ['route', 'back'], true) && $call['receiver'] === 'redirect()') {
                $lines[] = $call['classLine'];
            }

            if ($call['kind'] === 'static' && in_array($call['name'], ['route', 'back'], true) && mb_strtolower(self::lastSegment($call['class'])) === 'redirect') {
                $lines[] = $call['classLine'];
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     */
    public static function loadsRouteFile(array $calls): bool
    {
        return array_any($calls, static fn (array $call): bool => $call['name'] === 'loadroutesfrom');
    }

    public static function checksRoleName(PhpFileInspector $inspector, string $file): bool
    {
        $roleChecks = ['hasrole', 'hasanyrole', 'hasallroles', 'hasexactroles'];

        return array_any($inspector->callsIn($file), static fn (array $call): bool => in_array($call['kind'], ['method', 'nullsafe'], true) && in_array($call['name'], $roleChecks, true))
            || array_any($inspector->stringLiterals($file), static fn (string $value): bool => preg_match('/(^|\|)(role|role_or_permission):/', $value) === 1);
    }

    public static function castsDateMutably(PhpFileInspector $inspector, string $file): bool
    {
        return array_any($inspector->keyedArrayStrings($file), static fn (string $value): bool => preg_match('/^(datetime|date)(:|$)/', $value) === 1);
    }

    public static function methodAtLine(string $file, int $line): ?string
    {
        foreach (new NodeFinder()->findInstanceOf(self::parse($file), ClassMethod::class) as $method) {
            if ($method->getStartLine() <= $line && $line <= $method->getEndLine()) {
                return $method->name->toString();
            }
        }

        return null;
    }

    public static function declaredTable(string $file): ?string
    {
        foreach (new NodeFinder()->findInstanceOf(self::parse($file), PropertyItem::class) as $property) {
            if ($property->name->toString() === 'table' && $property->default instanceof String_) {
                return $property->default->value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function constantNames(string $file): array
    {
        $names = [];

        foreach (new NodeFinder()->findInstanceOf(self::parse($file), ClassConst::class) as $constants) {
            foreach ($constants->consts as $constant) {
                $names[] = $constant->name->toString();
            }
        }

        return $names;
    }

    /**
     * @param class-string $class
     */
    public static function invokeReturnType(string $class): ?ReflectionType
    {
        foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__invoke') {
                return $method->getReturnType();
            }
        }

        return null;
    }

    /**
     * @return array<Node>
     */
    private static function parse(string $file): array
    {
        return new ParserFactory()->createForHostVersion()->parse((string) file_get_contents($file)) ?? [];
    }
}
