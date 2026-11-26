<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final readonly class QueueNameGuard
{
    private const array QUEUE_SETTERS = ['onqueue', 'allonqueue', 'pushon', 'lateron'];

    private const string ONE_WAY = 'name the queue with a case of App\Modules\Observability\Enums\Queue (Queue::X), which has a Horizon worker in config/horizon.php';

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

        foreach ($finder->findInstanceOf($statements, Param::class) as $parameter) {
            if ($parameter->flags !== 0 && $parameter->var instanceof Variable && $parameter->var->name === 'queue' && self::isLiteral($parameter->default)) {
                $violations[$parameter->getStartLine() . ' e'] = $parameter->getStartLine() . ': a promoted $queue with a queue name as text: call $this->onQueue(Queue::X) in the constructor; ' . self::ONE_WAY;
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

    /**
     * Text, text joined to something (`'exports-' . $tenant`) and a choice between values of which one is text
     * (`$x ? 'a' : 'b'`, `$x ?? 'a'`, a `match` arm).
     */
    private static function isLiteral(?Node $value): bool
    {
        return match (true) {
            $value instanceof String_, $value instanceof InterpolatedString => true,
            $value instanceof Concat, $value instanceof Coalesce => self::isLiteral($value->left) || self::isLiteral($value->right),
            $value instanceof Ternary => self::isLiteral($value->if) || self::isLiteral($value->else),
            $value instanceof Match_ => array_any($value->arms, static fn (MatchArm $arm): bool => self::isLiteral($arm->body)),
            default => false,
        };
    }
}
