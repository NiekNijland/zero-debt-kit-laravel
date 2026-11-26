<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

/**
 * The receivers of a method call that hold an object of a type by declaration: a parameter or a property typed with one
 * of the names (the type must be resolved by the name resolver first), and the container string form `app('db')`. It is
 * how a guard reads "an injected dispatcher / logger / connection" without a booted application: a receiver that is
 * untyped is not read (the guide sentence names that blind spot).
 */
final readonly class TypedReceivers
{
    /**
     * @param list<string> $variables
     * @param list<string> $properties
     * @param list<string> $containerKeys
     */
    private function __construct(
        private array $variables,
        private array $properties,
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
        $variables = [];
        $properties = [];
        $holds = static fn (?Node $type): bool => $type instanceof Name && in_array(ltrim($type->toString(), '\\'), $types, true);
        $finder = new NodeFinder();

        foreach ($finder->findInstanceOf($statements, Param::class) as $parameter) {
            if ($holds($parameter->type) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $variables[] = $parameter->var->name;

                if ($parameter->flags !== 0) {
                    $properties[] = $parameter->var->name;
                }
            }
        }

        foreach ($finder->findInstanceOf($statements, Property::class) as $property) {
            if ($holds($property->type)) {
                foreach ($property->props as $item) {
                    $properties[] = $item->name->toString();
                }
            }
        }

        return new self($variables, $properties, $containerKeys);
    }

    public function holds(Expr $receiver): bool
    {
        return match (true) {
            $receiver instanceof Variable => is_string($receiver->name) && in_array($receiver->name, $this->variables, true),
            $receiver instanceof PropertyFetch => $receiver->var instanceof Variable && $receiver->var->name === 'this'
                && $receiver->name instanceof Identifier && in_array($receiver->name->toString(), $this->properties, true),
            $receiver instanceof FuncCall => $receiver->name instanceof Name && $receiver->name->toLowerString() === 'app'
                && ($receiver->getArgs()[0] ?? null)?->value instanceof String_
                && in_array($receiver->getArgs()[0]->value->value, $this->containerKeys, true),
            default => false,
        };
    }
}
