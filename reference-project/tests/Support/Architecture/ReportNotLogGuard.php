<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Log\Logger;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;

/**
 * A log call that handles a caught exception, and a tenant id written into a log context by hand. A log call is read on the
 * Log facade, on `logger()` and on an injected logger (`TypedReceivers`: a parameter or property typed with the logger
 * contract or class). Blind spots, named: an untyped logger receiver, and a closure in a catch block that never mentions the
 * caught variable (it is read as a callback the catch only stores).
 */
final readonly class ReportNotLogGuard
{
    private const array LOG_FACADES = ['Log', 'Illuminate\Support\Facades\Log'];

    private const array LOGGERS = [LoggerInterface::class, Logger::class];

    private const array SEVERE_LEVELS = ['warning', 'error', 'critical', 'alert', 'emergency'];

    private const string SEVERE_MESSAGE = 'a log call of level warning or higher that handles a caught exception: report the exception with report() and let the handler log it';

    private const string TENANT_MESSAGE = 'a tenant_id key written into a log context by hand: the log context adds the tenant, leave the key out';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string> `line N: message` per violation in the source, in line order
     */
    public function inSource(string $source): array
    {
        $statements = self::statements($source);
        $loggers = TypedReceivers::in($statements, self::LOGGERS, ['log']);
        $found = [];

        foreach (self::severeLogInCatchLines($statements, $loggers) as $line) {
            $found[$line . ':a'] = 'line ' . $line . ': ' . self::SEVERE_MESSAGE;
        }

        foreach (self::tenantIdLogKeyLines($statements, $loggers) as $line) {
            $found[$line . ':b'] = 'line ' . $line . ': ' . self::TENANT_MESSAGE;
        }

        ksort($found, SORT_NATURAL);

        return array_values($found);
    }

    /**
     * @return list<string> `file:` before each message of the source, relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $message) {
                $violations[] = substr($file, strlen($this->root) + 1) . ': ' . $message;
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param list<Node> $statements
     * @return list<int>
     */
    private static function severeLogInCatchLines(array $statements, TypedReceivers $loggers): array
    {
        $lines = [];

        foreach ((new NodeFinder())->findInstanceOf($statements, Catch_::class) as $catch) {
            foreach (self::callsOfTheCatch($catch) as $call) {
                if (in_array(self::level($call, $loggers), self::SEVERE_LEVELS, true)) {
                    $lines[] = $call->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @param list<Node> $statements
     * @return list<int>
     */
    private static function tenantIdLogKeyLines(array $statements, TypedReceivers $loggers): array
    {
        $lines = [];
        $finder = new NodeFinder();

        foreach ($finder->find($statements, static fn (Node $node): bool => ($node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof FuncCall) && self::onLog($node, $loggers)) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall && ! $call instanceof FuncCall) {
                continue;
            }

            foreach ($finder->findInstanceOf($call->args, Node\ArrayItem::class) as $item) {
                if ($item->key instanceof String_ && $item->key->value === 'tenant_id') {
                    $lines[] = $item->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * The calls in a catch block. A closure or arrow function in it is read only when it mentions the caught variable (it
     * handles the exception later, `fn () => Log::error($e->getMessage())`); one that never does is a callback the catch only
     * stores.
     *
     * @return list<StaticCall|MethodCall|NullsafeMethodCall|FuncCall>
     */
    private static function callsOfTheCatch(Catch_ $catch): array
    {
        $caught = $catch->var instanceof Variable && is_string($catch->var->name) ? $catch->var->name : null;
        $visitor = new class($caught) extends NodeVisitorAbstract
        {
            /** @var list<StaticCall|MethodCall|NullsafeMethodCall|FuncCall> */
            public array $calls = [];

            public function __construct(private readonly ?string $caught)
            {
            }

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\FunctionLike && ! $this->mentionsCaught($node)) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof FuncCall) {
                    $this->calls[] = $node;
                }

                return null;
            }

            private function mentionsCaught(Node $function): bool
            {
                return $this->caught !== null
                    && (new NodeFinder())->findFirst([$function], fn (Node $node): bool => $node instanceof Variable && $node->name === $this->caught) instanceof Node;
            }
        };

        PhpSource::traverse($catch->stmts, $visitor);

        return $visitor->calls;
    }

    /**
     * The lower-case level a log call writes at: `Log::error()`, `Log::channel('x')->error()`, `logger()->error()`, an injected
     * logger's `->error()` and `Log::log('error', ...)`; null when the call is not a log call or names no level.
     */
    private static function level(StaticCall|MethodCall|NullsafeMethodCall|FuncCall $call, TypedReceivers $loggers): ?string
    {
        if ($call instanceof FuncCall || ! $call->name instanceof Identifier || ! self::onLog($call, $loggers)) {
            return null;
        }

        $name = $call->name->toLowerString();

        if ($name !== 'log') {
            return $name;
        }

        $first = ($call->getArgs()[0] ?? null)?->value;

        return $first instanceof String_ ? mb_strtolower($first->value) : null;
    }

    /**
     * Whether the call is made on the Log facade, on `logger()` or on an injected logger, however long the chain
     * (`Log::channel('x')->withContext([...])->error()`). `logger('message', [...])` with arguments is a log call itself.
     */
    private static function onLog(StaticCall|MethodCall|NullsafeMethodCall|FuncCall $call, TypedReceivers $loggers): bool
    {
        if ($call instanceof FuncCall) {
            return self::isLoggerHelper($call) && $call->getArgs() !== [];
        }

        $receiver = $call instanceof StaticCall ? $call : $call->var;

        while ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) {
            $receiver = $receiver->var;
        }

        return ($receiver instanceof StaticCall && $receiver->class instanceof Name && in_array(ltrim($receiver->class->toString(), '\\'), self::LOG_FACADES, true))
            || ($receiver instanceof FuncCall && self::isLoggerHelper($receiver))
            || $loggers->holds($receiver);
    }

    private static function isLoggerHelper(FuncCall $call): bool
    {
        return $call->name instanceof Name && mb_strtolower($call->name->getLast()) === 'logger';
    }

    /**
     * @return list<Node>
     */
    private static function statements(string $source): array
    {
        return PhpSource::resolved($source);
    }
}
