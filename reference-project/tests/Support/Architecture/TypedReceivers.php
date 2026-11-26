<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use WeakMap;

/**
 * The receivers of a method call that hold an object of a type by declaration: a parameter or a property typed with one
 * of the names (the type must be resolved by the name resolver first; a nullable, union or intersection type counts when
 * one member is a name), a plain variable assigned from such a receiver (`$d = $this->db`), and the container string form
 * `app('db')`. It is how a guard reads "an injected dispatcher / logger / connection" without a booted application.
 *
 * A name is read in its own scope: a typed parameter holds in its function (and in a closure that `use`s it or an arrow
 * function inside it), a typed property holds in its class, so an untyped `$d` of another class of the same file is not
 * read. Blind spots (the guide sentence of a rule names them): a receiver that is untyped, a property declared in a parent
 * class, a getter such as `$this->getConnection()`, and an alias made by anything but a plain assignment.
 */
final readonly class TypedReceivers
{
    /**
     * @param WeakMap<Node, true> $held the variable and property nodes that hold an object of a listed type
     * @param list<string> $containerKeys
     * @param list<string> $types
     */
    private function __construct(
        private array $types,
        private WeakMap $held,
        private array $containerKeys,
    ) {
    }

    /**
     * @param array<array-key, Node> $statements resolved statements
     * @param list<string> $types fully qualified type names, without a leading backslash
     * @param list<string> $containerKeys the strings that `app()` resolves to such an object (`'events'`, `'log'`, `'db'`)
     */
    public static function in(array $statements, array $types, array $containerKeys = []): self
    {
        $held = new WeakMap();
        $reader = new self($types, $held, $containerKeys);

        (new NodeTraverser(new class($reader, $held, $types) extends NodeVisitorAbstract
        {
            /** @var list<list<string>> property names that hold the type, one entry per class on the way down */
            private array $properties = [];

            /** @var list<list<string>> variable names that hold the type, one entry per function on the way down */
            private array $variables = [[]];

            /**
             * @param WeakMap<Node, true> $held
             * @param list<string> $types
             */
            public function __construct(private readonly TypedReceivers $reader, private readonly WeakMap $held, private readonly array $types)
            {
            }

            #[Override]
            public function enterNode(Node $node): null
            {
                if ($node instanceof ClassLike) {
                    $this->properties[] = $this->propertiesOf($node);
                }

                if ($node instanceof FunctionLike) {
                    $this->variables[] = $this->variablesOf($node);
                }

                if ($node instanceof Assign && $node->var instanceof Variable && is_string($node->var->name)) {
                    $this->assign($node->var->name, $node->expr);
                }

                if (($node instanceof Variable || $node instanceof PropertyFetch) && $this->holds($node)) {
                    $this->held[$node] = true;
                }

                return null;
            }

            #[Override]
            public function leaveNode(Node $node): null
            {
                if ($node instanceof ClassLike) {
                    array_pop($this->properties);
                }

                if ($node instanceof FunctionLike) {
                    array_pop($this->variables);
                }

                return null;
            }

            private function holds(Expr $receiver): bool
            {
                return match (true) {
                    $receiver instanceof Variable => is_string($receiver->name) && in_array($receiver->name, $this->variables[array_key_last($this->variables)], true),
                    $receiver instanceof PropertyFetch => $receiver->var instanceof Variable && $receiver->var->name === 'this'
                        && $receiver->name instanceof Identifier && $this->properties !== []
                        && in_array($receiver->name->toString(), $this->properties[array_key_last($this->properties)], true),
                    default => $this->reader->holds($receiver),
                };
            }

            private function assign(string $name, Expr $value): void
            {
                $depth = array_key_last($this->variables);
                $this->variables[$depth] = array_values(array_diff($this->variables[$depth], [$name]));

                if (($value instanceof Variable || $value instanceof PropertyFetch || $value instanceof FuncCall) && $this->holds($value)) {
                    $this->variables[$depth][] = $name;
                }
            }

            /**
             * @return list<string>
             */
            private function propertiesOf(ClassLike $class): array
            {
                $names = [];

                foreach ($class->stmts as $statement) {
                    if ($statement instanceof Property && TypedReceivers::typeHolds($statement->type, $this->types)) {
                        foreach ($statement->props as $item) {
                            $names[] = $item->name->toString();
                        }
                    }

                    if ($statement instanceof ClassMethod && $statement->name->toLowerString() === '__construct') {
                        foreach ($statement->params as $parameter) {
                            if ($parameter->flags !== 0 && TypedReceivers::typeHolds($parameter->type, $this->types) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                                $names[] = $parameter->var->name;
                            }
                        }
                    }
                }

                return $names;
            }

            /**
             * @return list<string> the typed parameters, and what a closure takes in with `use` or an arrow function takes from outside
             */
            private function variablesOf(FunctionLike $function): array
            {
                $outer = $this->variables[array_key_last($this->variables)];
                $names = $function instanceof ArrowFunction ? $outer : [];

                if ($function instanceof Closure) {
                    foreach ($function->uses as $use) {
                        if (is_string($use->var->name) && in_array($use->var->name, $outer, true)) {
                            $names[] = $use->var->name;
                        }
                    }
                }

                foreach ($function->getParams() as $parameter) {
                    if ($parameter instanceof Param && TypedReceivers::typeHolds($parameter->type, $this->types) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                        $names[] = $parameter->var->name;
                    }
                }

                return $names;
            }
        }))->traverse($statements);

        return $reader;
    }

    public function holds(Expr $receiver): bool
    {
        return match (true) {
            $receiver instanceof Variable, $receiver instanceof PropertyFetch => isset($this->held[$receiver]),
            $receiver instanceof FuncCall => $receiver->name instanceof Name && $receiver->name->toLowerString() === 'app' && $this->resolvesFromContainer(($receiver->getArgs()[0] ?? null)?->value),
            default => false,
        };
    }

    /**
     * @param list<string> $types
     * @internal for the visitor of this class
     */
    public static function typeHolds(?Node $type, array $types): bool
    {
        return match (true) {
            $type instanceof Name => in_array(ltrim($type->toString(), '\\'), $types, true),
            $type instanceof NullableType => self::typeHolds($type->type, $types),
            $type instanceof UnionType, $type instanceof IntersectionType => array_any($type->types, static fn (Node $member): bool => self::typeHolds($member, $types)),
            default => false,
        };
    }

    /**
     * `app('events')` (a key the caller lists) and `app(Dispatcher::class)` (a type the caller lists).
     */
    private function resolvesFromContainer(?Expr $argument): bool
    {
        return match (true) {
            $argument instanceof String_ => in_array($argument->value, $this->containerKeys, true),
            $argument instanceof ClassConstFetch => $argument->class instanceof Name && $argument->name instanceof Identifier
                && $argument->name->toLowerString() === 'class' && in_array(ltrim($argument->class->toString(), '\\'), $this->types, true),
            default => false,
        };
    }
}
