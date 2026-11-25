<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

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
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use Tests\Support\RecursiveFiles;

final class PhpFileInspector
{
    /** Resolved names: statements() runs NameResolver, so a class named Exception in another namespace is not broad. */
    private const array BROAD_EXCEPTIONS = ['Throwable', 'Exception', 'Error', 'ErrorException', 'RuntimeException', 'LogicException'];

    /** @var array<string, list<Node\Stmt>> */
    private array $parsed = [];

    /** @var array<string, list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}>> */
    private static array $calls = [];

    /**
     * Names split by where they sit: `inside` is everything within a static method whose name starts with `from`,
     * `outside` is everything else except the import statements.
     *
     * @return array{inside: list<string>, outside: list<string>}
     */
    public function referencedNamesByFactory(string $file): array
    {
        $visitor = new class() extends NodeVisitorAbstract
        {
            /** @var list<string> */
            public array $inside = [];

            /** @var list<string> */
            public array $outside = [];

            private int $depth = 0;

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Use_ || $node instanceof GroupUse) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof ClassMethod && $node->isStatic() && str_starts_with($node->name->toString(), 'from')) {
                    $this->depth++;
                }

                if ($node instanceof Name) {
                    $this->record(ltrim($node->toString(), '\\'));
                }

                if ($node instanceof String_ && preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $node->value) === 1) {
                    $this->record(ltrim($node->value, '\\'));
                }

                return null;
            }

            #[Override]
            public function leaveNode(Node $node): null
            {
                if ($node instanceof ClassMethod && $node->isStatic() && str_starts_with($node->name->toString(), 'from')) {
                    $this->depth--;
                }

                return null;
            }

            private function record(string $name): void
            {
                if ($this->depth > 0) {
                    $this->inside[] = $name;
                } else {
                    $this->outside[] = $name;
                }
            }
        };

        (new NodeTraverser($visitor))->traverse($this->statements($file));

        return ['inside' => array_values(array_unique($visitor->inside)), 'outside' => array_values(array_unique($visitor->outside))];
    }

    /**
     * @return list<string>
     */
    public function referencedNames(string $file): array
    {
        $names = [];

        foreach ($this->statements($file) as $statement) {
            foreach ((new NodeFinder())->findInstanceOf($statement, Name::class) as $name) {
                $names[] = ltrim($name->toString(), '\\');
            }

            foreach ((new NodeFinder())->findInstanceOf($statement, String_::class) as $string) {
                if (preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $string->value) === 1) {
                    $names[] = ltrim($string->value, '\\');
                }
            }

            foreach ((new NodeFinder())->findInstanceOf($statement, GroupUse::class) as $group) {
                foreach ($group->uses as $use) {
                    $names[] = ltrim($group->prefix->toString() . '\\' . $use->name->toString(), '\\');
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, list<string>>
     */
    public function declaredTypes(string $file): array
    {
        $types = [];

        foreach ($this->statements($file) as $statement) {
            $declarations = (new NodeFinder())->find(
                $statement,
                static fn (Node $node): bool => $node instanceof Class_ || $node instanceof Trait_ || $node instanceof Interface_ || $node instanceof Enum_,
            );

            foreach ($declarations as $declaration) {
                if (! $declaration instanceof ClassLike || $declaration->namespacedName === null) {
                    continue;
                }

                $related = [];

                foreach ((new NodeFinder())->findInstanceOf($declaration, Name::class) as $name) {
                    $related[] = ltrim($name->toString(), '\\');
                }

                $types[$declaration->namespacedName->toString()] = array_values(array_unique($related));
            }
        }

        return $types;
    }

    /**
     * @return list<int>
     */
    public function swallowedBroadCatchLines(string $file): array
    {
        $lines = [];

        foreach ($this->statements($file) as $statement) {
            foreach ((new NodeFinder())->findInstanceOf($statement, Catch_::class) as $catch) {
                $broad = array_filter($catch->types, static fn (Name $type): bool => in_array($type->toString(), self::BROAD_EXCEPTIONS, true)) !== [];

                if ($broad && ! $this->reportsOrRethrows($catch)) {
                    $lines[] = $catch->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @return list<string> absolute paths of the PHP files below the directory
     */
    public function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (RecursiveFiles::in($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every function, method, nullsafe method and static call of a file, kept per file for the next test that reads it. `written` is the callee as it stands in the source (a leading backslash
     * included), `class` the resolved class of a static call, `receiver` a short description of what a method is called on (`$request`, `->container`,
     * `request()` for a function call, `->request()` for a method call, `::request()` for a static call), `line` the line of the called name and
     * `classLine` the line of the class a static call names, or of the call a method is called on.
     *
     * @return list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}>
     */
    public function callsIn(string $file): array
    {
        $key = $file . '@' . (string) filemtime($file);

        return self::$calls[$key] ??= $this->calls((string) file_get_contents($file));
    }

    /**
     * @return list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}>
     */
    public function calls(string $source): array
    {
        return PhpCallReader::calls($source);
    }

    /**
     * @return list<string> the value of every string literal of a file, the literal parts of an interpolated string or heredoc included
     */
    public function stringLiterals(string $file): array
    {
        $values = [];

        foreach ($this->statements($file) as $statement) {
            foreach ((new NodeFinder())->find($statement, static fn (Node $node): bool => $node instanceof String_ || $node instanceof Node\InterpolatedStringPart) as $string) {
                if ($string instanceof String_ || $string instanceof Node\InterpolatedStringPart) {
                    $values[] = $string->value;
                }
            }
        }

        return $values;
    }

    /**
     * @return list<string> the string literals that are the value of an array item with a key (`key => 'value'`)
     */
    public function keyedArrayStrings(string $file): array
    {
        $values = [];

        foreach ($this->statements($file) as $statement) {
            foreach ((new NodeFinder())->findInstanceOf($statement, Node\ArrayItem::class) as $item) {
                if ($item->key !== null && $item->value instanceof String_) {
                    $values[] = $item->value->value;
                }
            }
        }

        return $values;
    }

    private function reportsOrRethrows(Catch_ $catch): bool
    {
        return (new NodeFinder())->findFirst($catch->stmts, static function (Node $node): bool {
            $name = match (true) {
                $node instanceof FuncCall && $node->name instanceof Name => $node->name->toString(),
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier => $node->name->toString(),
                default => null,
            };

            return $node instanceof Node\Expr\Throw_
                || $name === 'rescue'
                || ($name !== null && str_starts_with($name, 'report'))
                || ($node instanceof StaticCall && $node->class instanceof Name && $node->class->toString() === 'Illuminate\\Support\\Facades\\Exceptions')
                || ($node instanceof MethodCall && $name === 'fail' && $node->var instanceof Variable && $node->var->name === 'this');
        }) instanceof Node;
    }

    /**
     * @return list<Node\Stmt>
     */
    private function statements(string $file): array
    {
        return $this->parsed[$file] ??= PhpSource::statements($file);
    }
}
