<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class BannedTestCallGuard
{
    /** Tests without the booted application have no $this->travel(): they, and the tier traits that reset the clock, may use setTestNow(). */
    private const array CLOCK_EXEMPT = ['tests/Unit/', 'tests/Concerns/'];

    private const array CLOCK_CLASSES = [
        'Carbon',
        'CarbonImmutable',
        'Date',
        'Illuminate\Support\Carbon',
        'Illuminate\Support\Facades\Date',
        'Carbon\Carbon',
        'Carbon\CarbonImmutable',
    ];

    private const array BINDINGS = ['instance', 'bind', 'singleton', 'scoped', 'bindif', 'singletonif', 'extend'];

    private const array APP_FACADES = ['Illuminate\Support\Facades\App', 'App'];

    private const array PEST_TEST_FUNCTIONS = ['it', 'test', 'describe', 'arch', 'todo'];

    private const array LOOSE_EXPECTATIONS = ['tobetruthy' => 'toBeTruthy(): assert the exact value with toBeTrue(), toBe() or not->toBeEmpty()', 'tobefalsy' => 'toBeFalsy(): assert the exact value with toBeFalse(), toBeNull() or toBeEmpty()'];

    private const array HANDLING_SWITCHES = [
        'withoutexceptionhandling' => 'withoutExceptionHandling(): assert the response (assertServerError(), assertSessionHasNoErrors()), or call the class directly and expect(fn () => ...)->toThrow()',
        'withoutmiddleware' => 'withoutMiddleware(): send the request through its middleware, as a signed-in user with the permission the route asks for',
    ];

    private const array TEST_CLOSURE_FUNCTIONS = ['it', 'test', 'describe', 'beforeeach', 'aftereach', 'beforeall', 'afterall'];

    private const array NAMED_STATUSES = [
        200 => 'assertOk',
        201 => 'assertCreated',
        202 => 'assertAccepted',
        204 => 'assertNoContent',
        301 => 'assertMovedPermanently',
        302 => 'assertFound',
        304 => 'assertNotModified',
        307 => 'assertTemporaryRedirect',
        308 => 'assertPermanentRedirect',
        400 => 'assertBadRequest',
        401 => 'assertUnauthorized',
        402 => 'assertPaymentRequired',
        403 => 'assertForbidden',
        404 => 'assertNotFound',
        405 => 'assertMethodNotAllowed',
        406 => 'assertNotAcceptable',
        408 => 'assertRequestTimeout',
        409 => 'assertConflict',
        410 => 'assertGone',
        415 => 'assertUnsupportedMediaType',
        422 => 'assertUnprocessable',
        424 => 'assertFailedDependency',
        429 => 'assertTooManyRequests',
        500 => 'assertInternalServerError',
        503 => 'assertServiceUnavailable',
    ];

    public function __construct(
        private readonly PhpFileInspector $inspector,
        private readonly string $root,
    ) {
    }

    /**
     * Every banned call of the test suite, per file, as `line N: the call: what to write instead`.
     *
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/tests') as $file) {
            $relative = substr($file, strlen($this->root) + 1);
            $found = $this->inFile($file, $relative);

            if ($found !== []) {
                $violations[$relative] = $found;
            }
        }

        ksort($violations);

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function inFile(string $file, string $relative): array
    {
        $found = [];

        foreach ($this->inspector->calls((string) file_get_contents($file)) as $call) {
            $reason = $this->reasonToBan($call, $relative);

            if ($reason !== null) {
                $found[] = 'line ' . $call['line'] . ': ' . $reason;
            }
        }

        return [...$found, ...$this->chainedOnATest((string) file_get_contents($file))];
    }

    /**
     * @return list<string>
     */
    private function chainedOnATest(string $source): array
    {
        $found = [];
        $statements = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];

        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            if (! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall) || ! $call->name instanceof Identifier) {
                continue;
            }

            $name = $call->name->toLowerString();
            $root = $this->chainRoot($call->var);
            $line = 'line ' . $call->name->getStartLine() . ': ';

            if ($name === 'only' && in_array($root, self::PEST_TEST_FUNCTIONS, true)) {
                $found[] = $line . '->only() on a test: remove it, a focused test skips every other test of the run; run one test with --filter';
            }

            if ($name === 'withoutmiddleware' && $root !== null) {
                $found[] = $line . self::HANDLING_SWITCHES[$name];
            }

            $status = $call->isFirstClassCallable() ? null : ($call->getArgs()[0]->value ?? null);

            if ($name === 'assertstatus' && $status instanceof Int_ && isset(self::NAMED_STATUSES[$status->value])) {
                $found[] = $line . 'assertStatus(' . $status->value . '): write ' . self::NAMED_STATUSES[$status->value] . '()';
            }
        }

        return [...$found, ...$this->oldTestSpellings($statements)];
    }

    /**
     * @param array<Node> $statements
     * @return list<string>
     */
    private function oldTestSpellings(array $statements): array
    {
        $found = [];

        foreach ((new NodeFinder())->findInstanceOf($statements, FuncCall::class) as $call) {
            if (! $call->name instanceof Name || $call->isFirstClassCallable() || $call->getArgs() === []) {
                continue;
            }

            $name = $call->name->toLowerString();
            $arguments = $call->getArgs();
            $description = $arguments[0]->value;
            $closure = $arguments[count($arguments) - 1]->value;
            $line = 'line ' . $call->getStartLine() . ': ';

            if ($name === 'test' && $description instanceof String_ && str_starts_with($description->value, 'it ')) {
                $found[] = $line . 'test(\'it ...\'): write it(\'...\'), Pest puts "it" in front of the description';
            }

            if (in_array($name, self::TEST_CLOSURE_FUNCTIONS, true) && $closure instanceof Closure && ! ($closure->returnType instanceof Identifier && $closure->returnType->toLowerString() === 'void')) {
                $found[] = $line . 'a closure of ' . $name . '() without a return type: write function (): void';
            }
        }

        return $found;
    }

    private function chainRoot(Node $expression): ?string
    {
        while ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall || $expression instanceof PropertyFetch) {
            $expression = $expression->var;
        }

        if ($expression instanceof Variable) {
            return $expression->name === 'this' ? '$this' : null;
        }

        return $expression instanceof FuncCall && $expression->name instanceof Name ? $expression->name->getLast() : null;
    }

    /**
     * @param array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>} $call
     */
    private function reasonToBan(array $call, string $relative): ?string
    {
        if ($call['kind'] === 'static' && $call['name'] === 'set' && in_array($call['class'], ['Illuminate\Support\Facades\Config', 'Config'], true)) {
            return 'Config::set(): write config([\'key\' => $value])';
        }

        if ($call['kind'] === 'method' && $call['name'] === 'set' && $call['receiver'] === 'config()') {
            return 'config()->set(): write config([\'key\' => $value])';
        }

        if ($call['kind'] === 'static' && $call['name'] === 'settestnow' && in_array($call['class'], self::CLOCK_CLASSES, true) && ! $this->isClockExempt($relative)) {
            return 'setTestNow(): use $this->travelTo(), $this->freezeTime() or test()->travelTo(); only tests/Unit has no test clock';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === '->app' && in_array($call['name'], self::BINDINGS, true)) {
            return '$this->app->' . $call['name'] . '(): write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === '$this' && $call['name'] === 'instance') {
            return '$this->instance(): write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'static' && $call['name'] === 'instance' && in_array($call['class'], self::APP_FACADES, true)) {
            return 'App::instance(): write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === 'app()' && $call['name'] !== 'instance' && in_array($call['name'], self::BINDINGS, true) && $this->returnsReadyObject($call['arguments'][1] ?? '')) {
            return 'app()->' . $call['name'] . '() of a ready object: write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'static' && $call['name'] !== 'instance' && in_array($call['class'], self::APP_FACADES, true) && in_array($call['name'], self::BINDINGS, true) && $this->returnsReadyObject($call['arguments'][1] ?? '')) {
            return 'App::' . $call['name'] . '() of a ready object: write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'function' && $call['name'] === 'expect' && in_array($call['arguments'], [['true'], ['\\true']], true)) {
            return 'expect(true): assert the state the code leaves behind, or end the test with ->throwsNoExceptions()';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === '->not' && $call['name'] === 'tothrow') {
            return '->not->toThrow(): call the code directly so an exception fails the test, and assert the state it leaves behind';
        }

        if ($call['kind'] !== 'static' && isset(self::LOOSE_EXPECTATIONS[$call['name']])) {
            return self::LOOSE_EXPECTATIONS[$call['name']];
        }

        if ($call['kind'] !== 'static' && $call['name'] === 'withoutexceptionhandling') {
            return self::HANDLING_SWITCHES[$call['name']];
        }

        if ($call['kind'] === 'function' && $call['name'] === 'withoutmiddleware') {
            return self::HANDLING_SWITCHES[$call['name']];
        }

        if ($call['kind'] === 'method' && $call['name'] === 'tothrow' && $this->dropsItsResult($call['arguments'][0] ?? '')) {
            return 'toThrow() with a closure whose result Pest drops: assert inside the closure with expect()';
        }

        return null;
    }

    private function dropsItsResult(string $closure): bool
    {
        return preg_match('/^(static )?(fn|function)\s*\((?:(?!=>)[^{])*?\)\s*:\s*bool\b/', $closure) === 1
            || preg_match('/^(static )?fn\s*\([^)]*\)\s*(:\s*\S+\s*)?=>(?!\s*\\\\?expect\()/', $closure) === 1;
    }

    private function isClockExempt(string $relative): bool
    {
        return array_any(self::CLOCK_EXEMPT, static fn (string $prefix): bool => str_starts_with($relative, $prefix));
    }

    private function returnsReadyObject(string $closure): bool
    {
        return preg_match('/^(static )?fn\(\)(: \S+)? => \$\w+$/', $closure) === 1;
    }
}
