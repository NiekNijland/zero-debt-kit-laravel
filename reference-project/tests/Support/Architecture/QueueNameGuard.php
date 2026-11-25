<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final readonly class QueueNameGuard
{
    private const array QUEUE_SETTERS = ['onqueue', 'allonqueue'];

    private const string ONE_WAY = 'name the queue with a case of App\Modules\Observability\Enums\Queue (Queue::X), which has a Horizon worker in config/horizon.php';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/app') as $file) {
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
        $statements = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];
        $finder = new NodeFinder();
        $violations = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) as $call) {
            if (! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall) || ! $call->name instanceof Identifier) {
                continue;
            }

            $first = $call->args[0] ?? null;

            if (in_array($call->name->toLowerString(), self::QUEUE_SETTERS, true) && $first instanceof Node\Arg && self::isLiteral($first->value)) {
                $violations[$call->getStartLine() . ' a'] = $call->getStartLine() . ': ' . $call->name->toString() . '() with a queue name as text: ' . self::ONE_WAY;
            }
        }

        foreach ($finder->findInstanceOf($statements, Property::class) as $property) {
            foreach ($property->props as $declared) {
                if ($declared->name->toString() === 'queue' && self::isLiteral($declared->default)) {
                    $violations[$declared->getStartLine() . ' b'] = $declared->getStartLine() . ': $queue with a queue name as text: call $this->onQueue(Queue::X) in the constructor; ' . self::ONE_WAY;
                }
            }
        }

        foreach ($finder->findInstanceOf($statements, Assign::class) as $assign) {
            if ($assign->var instanceof PropertyFetch && $assign->var->name instanceof Identifier && $assign->var->name->toString() === 'queue' && self::isLiteral($assign->expr)) {
                $violations[$assign->getStartLine() . ' c'] = $assign->getStartLine() . ': ->queue set to a queue name as text: ' . self::ONE_WAY;
            }
        }

        foreach ($finder->findInstanceOf($statements, ClassMethod::class) as $method) {
            if ($method->name->toLowerString() !== 'viaqueues') {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts ?? [], Node\ArrayItem::class) as $item) {
                if (self::isLiteral($item->value)) {
                    $violations[$item->getStartLine() . ' d'] = $item->getStartLine() . ': viaQueues() with a queue name as text: ' . self::ONE_WAY;
                }
            }
        }

        ksort($violations, SORT_NATURAL);

        return array_values($violations);
    }

    private static function isLiteral(?Node $value): bool
    {
        return $value instanceof String_ || $value instanceof InterpolatedString;
    }
}
