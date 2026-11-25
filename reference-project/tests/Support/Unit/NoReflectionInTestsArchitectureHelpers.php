<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\PhpFileInspector;

final class NoReflectionInTestsArchitectureHelpers
{
    /** Reflection classes that hand out a private member or a private constant. */
    private const array MEMBER_REFLECTION = ['ReflectionMethod', 'ReflectionProperty', 'ReflectionClassConstant', 'ReflectionObject'];

    /**
     * What a test may read from `new ReflectionClass(...)`: the public structure of a class, called on the new instance
     * or on the variable it is assigned to (then every call on that variable in the same function counts). `getMethods`
     * only with the `ReflectionMethod::IS_PUBLIC` filter, `getProperties` only with `ReflectionProperty::IS_PUBLIC`.
     */
    private const array PUBLIC_STRUCTURE = [
        'getAttributes', 'implementsInterface', 'isSubclassOf', 'getName', 'getShortName', 'getNamespaceName', 'getInterfaceNames',
        'getParentClass', 'getTraitNames', 'isFinal', 'isAbstract', 'isInterface', 'isEnum', 'isReadOnly', 'isInstantiable', 'getFileName',
        'isAnonymous', 'hasMethod', 'getConstructor', 'newInstance', 'newInstanceArgs', 'getMethods', 'getProperties',
    ];

    /**
     * @return list<string> `line: what` per way the source reaches into a class
     */
    public static function violations(string $source): array
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];
        $visitor = new class() extends NodeVisitorAbstract
        {
            /** @var list<string> */
            public array $found = [];

            #[Override]
            public function enterNode(Node $node): null
            {
                $this->found = [...$this->found, ...NoReflectionInTestsArchitectureHelpers::violationsOf($node)];

                return null;
            }
        };

        (new NodeTraverser(new NameResolver(), new ParentConnectingVisitor(), $visitor))->traverse($statements);

        return $visitor->found;
    }

    /**
     * @return list<string>
     */
    public static function violationsOf(Node $node): array
    {
        $line = $node->getStartLine();

        if ($node instanceof New_ && $node->class instanceof Name) {
            $class = ltrim($node->class->toString(), '\\');

            if (in_array($class, self::MEMBER_REFLECTION, true)) {
                return ["{$line}: new {$class}"];
            }

            if ($class === 'ReflectionClass' && ! self::readsPublicStructure($node)) {
                return ["{$line}: ReflectionClass beyond its public structure"];
            }
        }

        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->name instanceof Identifier
            && in_array($node->name->toString(), ['setAccessible', 'bindTo'], true)) {
            return ["{$line}: ->{$node->name->toString()}()"];
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && ltrim($node->class->toString(), '\\') === 'Closure'
            && $node->name instanceof Identifier && $node->name->toString() === 'bind') {
            return ["{$line}: Closure::bind()"];
        }

        return [];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function violationsInTests(): array
    {
        $root = dirname(__DIR__, 3);
        $violations = [];

        foreach (new PhpFileInspector()->phpFiles($root . '/tests') as $file) {
            $found = self::violations((string) file_get_contents($file));

            if ($found !== []) {
                $violations[substr($file, strlen($root) + 1)] = $found;
            }
        }

        ksort($violations);

        return $violations;
    }

    private static function readsPublicStructure(New_ $new): bool
    {
        $parent = $new->getAttribute('parent');

        if ($parent instanceof Assign && $parent->expr === $new && $parent->var instanceof Variable && is_string($parent->var->name)) {
            return self::variableReadsPublicStructure($parent, $parent->var->name);
        }

        return $parent instanceof MethodCall && $parent->var === $new && self::isPublicStructureRead($parent);
    }

    private static function variableReadsPublicStructure(Assign $assign, string $variable): bool
    {
        $scope = $assign->getAttribute('parent');

        while ($scope instanceof Node && ! $scope instanceof FunctionLike) {
            $scope = $scope->getAttribute('parent');
        }

        if (! $scope instanceof FunctionLike) {
            return false;
        }

        $calls = (new NodeFinder())->find(
            $scope->getStmts() ?? [],
            static fn (Node $node): bool => $node instanceof MethodCall && $node->var instanceof Variable && $node->var->name === $variable,
        );

        return array_all($calls, static fn (Node $call): bool => $call instanceof MethodCall && self::isPublicStructureRead($call));
    }

    private static function isPublicStructureRead(MethodCall $call): bool
    {
        if (! $call->name instanceof Identifier) {
            return false;
        }

        $method = $call->name->toString();

        $filters = ['getMethods' => 'ReflectionMethod', 'getProperties' => 'ReflectionProperty'];

        if (! isset($filters[$method])) {
            return in_array($method, self::PUBLIC_STRUCTURE, true);
        }

        $filter = $call->getArgs()[0]->value ?? null;

        return $filter instanceof ClassConstFetch && $filter->class instanceof Name && ltrim($filter->class->toString(), '\\') === $filters[$method]
            && $filter->name instanceof Identifier && $filter->name->toString() === 'IS_PUBLIC';
    }
}
