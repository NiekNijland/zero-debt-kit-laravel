<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Providers\RateLimitServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class RateLimiterGuard
{
    private const string NUMBER_IN_CONFIG = "the number of a limit lives in config/rate_limiting.php: Limit::perMinute(Config::integer('rate_limiting.<name>_per_minute'))->by(\$key), and the window is the factory (perMinute, perHour, perDay)";

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = substr($file, strlen($this->root) + 1) . ':' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $finder = new NodeFinder();
        $violations = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof New_) as $call) {
            if (! ($call instanceof StaticCall || $call instanceof New_) || ! $call->class instanceof Name || ltrim($call->class->toString(), '\\') !== Limit::class) {
                continue;
            }

            $number = $finder->findFirst($call->args, static fn (Node $node): bool => $node instanceof Int_ || $node instanceof Float_
                || ($node instanceof ClassConstFetch && $node->name instanceof Identifier && $node->name->toLowerString() !== 'class'));

            if ($number instanceof Node) {
                $violations[] = $call->getStartLine() . ': a limit with a number or a constant in the code: ' . self::NUMBER_IN_CONFIG;
            }
        }

        foreach ($finder->findInstanceOf($statements, StaticCall::class) as $call) {
            if (! $call->class instanceof Name || ltrim($call->class->toString(), '\\') !== RateLimiter::class || ! $call->name instanceof Identifier || $call->name->toLowerString() !== 'for') {
                continue;
            }

            $provider = $finder->findFirst($statements, static fn (Node $node): bool => $node instanceof Class_
                && $node->namespacedName?->toString() === RateLimitServiceProvider::class
                && $node->getStartLine() <= $call->getStartLine() && $call->getEndLine() <= $node->getEndLine());

            if (! $provider instanceof Class_) {
                $violations[] = $call->getStartLine() . ': a limiter defined outside ' . RateLimitServiceProvider::class . ': every RateLimiter::for() lives in that provider, and a route takes it with throttle:<name>';
            }

            $name = $call->args[0] ?? null;

            if ($name instanceof Arg && $name->value instanceof String_ && preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name->value->value) !== 1) {
                $violations[] = $call->getStartLine() . ': the limiter "' . $name->value->value . '" is not named in snake_case, like product_search';
            }
        }

        sort($violations, SORT_NATURAL);

        return $violations;
    }
}
