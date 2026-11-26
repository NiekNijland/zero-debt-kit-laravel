<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;

/**
 * The classes, interfaces and traits of a set of sources, with what a rule about lineage needs: the parent, the
 * interfaces, the traits, the methods, the properties (promoted ones too) and the attributes. The project's own types
 * are read from the parsed sources, so an example in a sandbox works without the application; a type the sources do
 * not hold (a framework class) is read through the autoloader. A guard that asks "is this class queued", "does it
 * declare a method" goes through this one reader, so inheritance, a sub-interface and a trait are spelled the same way
 * in every guard (`04-guards.md` C1, "Class relations").
 *
 * @phpstan-type Info array{
 *     name: string,
 *     path: string,
 *     line: int,
 *     kind: 'class'|'interface'|'trait',
 *     abstract: bool,
 *     parents: list<string>,
 *     uses: list<string>,
 *     methods: list<string>,
 *     callable: list<string>,
 *     properties: array<string, 'default'|'instance'>,
 *     attributes: list<string>,
 * }
 */
final class ClassGraph
{
    /**
     * @param array<string, Info> $types by the resolved name; an anonymous class is keyed by its path and line
     */
    private function __construct(private readonly array $types)
    {
    }

    /**
     * @param array<string, string> $sources source code by the path that a finding names (empty for a lone example)
     */
    public static function of(array $sources): self
    {
        $types = [];

        foreach ($sources as $path => $source) {
            $statements = PhpSource::resolved($source);

            foreach ((new NodeFinder())->findInstanceOf($statements, ClassLike::class) as $type) {
                $info = self::describe($type, $path);
                $types[$type->namespacedName === null ? $info['name'] . '@' . $path : $info['name']] = $info;
            }
        }

        return new self($types);
    }

    /**
     * @return list<Info> the classes that are not abstract, the anonymous ones included
     */
    public function concreteClasses(): array
    {
        return array_values(array_filter($this->types, static fn (array $info): bool => $info['kind'] === 'class' && ! $info['abstract']));
    }

    /**
     * @param Info $info
     * @param list<string> $targets
     *
     * @return string|null the first of the targets that the type reaches through a parent, an interface or a sub-interface
     */
    public function reaches(array $info, array $targets): ?string
    {
        foreach ($this->ancestors($info['parents']) as $ancestor) {
            if (in_array($ancestor, $targets, true)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Whether the type, a parent class or a trait it uses (at any depth) declares the method. A method that an
     * interface only declares does not count: the class must carry it. With `$callable` the method must also be one that code
     * outside the class can call (public, not static, not abstract): the form a framework calls on a queued job.
     *
     * @param Info $info
     */
    public function hasMethod(array $info, string $method, bool $callable = false): bool
    {
        foreach ($this->lineage($info) as $name => $type) {
            if ($type !== null && in_array(strtolower($method), $type[$callable ? 'callable' : 'methods'], true)) {
                return true;
            }

            if ($type === null && (class_exists($name) || trait_exists($name)) && method_exists($name, $method)) {
                if (! $callable) {
                    return true;
                }

                foreach (new ReflectionClass($name)->getMethods(ReflectionMethod::IS_PUBLIC) as $public) {
                    if (strcasecmp($public->getName(), $method) === 0 && ! $public->isStatic() && ! $public->isAbstract()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * A class outside the parsed tree is read from its own file, found through the autoloader, so a property of a vendor class goes through the same
     * parser as the project's and not through a reflection of its members.
     *
     * @return 'default'|'instance'|null
     */
    private function vendorProperty(string $name, string $property): ?string
    {
        $file = new ReflectionClass($name)->getFileName();

        if ($file === false) {
            return null;
        }

        $parsed = self::of([$file => (string) file_get_contents($file)])->types[$name] ?? null;

        return $parsed === null ? null : new self([$name => $parsed, ...$this->types])->property($parsed, $property);
    }

    /**
     * The property as the class, a parent or a trait declares it: 'instance' when it is promoted or has no default
     * (set per object), 'default' when a default stands on it, null when nothing declares it.
     *
     * @param Info $info
     *
     * @return 'default'|'instance'|null
     */
    public function property(array $info, string $property): ?string
    {
        $found = null;

        foreach ($this->lineage($info) as $name => $type) {
            $declared = $type['properties'][$property] ?? null;

            if ($type === null && (class_exists($name) || trait_exists($name))) {
                $declared = $this->vendorProperty($name, $property);
            }

            if ($declared === 'instance') {
                return 'instance';
            }

            $found ??= $declared;
        }

        return $found;
    }

    /**
     * Whether the type, a parent class or a trait carries an attribute of the name (the last segment of its resolved name).
     *
     * @param Info $info
     */
    public function hasAttribute(array $info, string $shortName): bool
    {
        foreach ($this->lineage($info) as $type) {
            foreach ($type['attributes'] ?? [] as $attribute) {
                if (str_ends_with('\\' . $attribute, '\\' . $shortName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string> every class and interface that the names reach, the names included
     */
    private function ancestors(array $names): array
    {
        $found = [];
        $queue = $names;

        while ($queue !== []) {
            $name = ltrim((string) array_shift($queue), '\\');

            if (in_array($name, $found, true)) {
                continue;
            }

            $found[] = $name;

            if (isset($this->types[$name])) {
                array_push($queue, ...$this->types[$name]['parents']);
            } elseif (class_exists($name) || interface_exists($name)) {
                array_push($queue, ...array_values(class_parents($name) ?: []), ...array_values(class_implements($name) ?: []));
            }
        }

        return $found;
    }

    /**
     * The type itself, then its parent classes and used traits at any depth. An entry is null for a type the sources do not hold.
     *
     * @param Info $info
     *
     * @return array<string, Info|null>
     */
    private function lineage(array $info): array
    {
        $lineage = [$info['name'] => $info];
        $queue = [...$info['parents'], ...$info['uses']];

        while ($queue !== []) {
            $name = ltrim((string) array_shift($queue), '\\');

            if (array_key_exists($name, $lineage)) {
                continue;
            }

            $type = $this->types[$name] ?? null;
            $lineage[$name] = $type;

            if ($type !== null && $type['kind'] !== 'interface') {
                array_push($queue, ...$type['parents'], ...$type['uses']);
            }
        }

        return $lineage;
    }

    /**
     * @return Info
     */
    private static function describe(ClassLike $type, string $path): array
    {
        $parents = [];

        if ($type instanceof Class_) {
            $parents = [...($type->extends instanceof Name ? [$type->extends->toString()] : []), ...array_map(static fn (Name $name): string => $name->toString(), $type->implements)];
        } elseif ($type instanceof Interface_) {
            $parents = array_map(static fn (Name $name): string => $name->toString(), $type->extends);
        }

        $uses = [];

        foreach ($type->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $uses[] = $trait->toString();
            }
        }

        $properties = [];

        foreach ($type->getProperties() as $property) {
            foreach ($property->props as $item) {
                $properties[$item->name->toString()] = $item->default === null ? 'instance' : 'default';
            }
        }

        foreach ($type->getMethod('__construct')?->params ?? [] as $parameter) {
            if ($parameter->flags !== 0 && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $properties[$parameter->var->name] = 'instance';
            }
        }

        $attributes = [];

        foreach ($type->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $attributes[] = $attribute->name->toString();
            }
        }

        return [
            'name' => $type->namespacedName?->toString() ?? 'an anonymous class on line ' . $type->getStartLine(),
            'path' => $path,
            'line' => $type->getStartLine(),
            'kind' => $type instanceof Interface_ ? 'interface' : ($type instanceof Class_ ? 'class' : 'trait'),
            'abstract' => $type instanceof Class_ && $type->isAbstract(),
            'parents' => $parents,
            'uses' => $uses,
            'methods' => $type instanceof Interface_ ? [] : array_map(static fn (ClassMethod $method): string => $method->name->toLowerString(), $type->getMethods()),
            'callable' => $type instanceof Interface_ ? [] : array_values(array_map(static fn (ClassMethod $method): string => $method->name->toLowerString(), array_filter($type->getMethods(), static fn (ClassMethod $method): bool => $method->isPublic() && ! $method->isStatic() && ! $method->isAbstract()))),
            'properties' => $properties,
            'attributes' => $attributes,
        ];
    }
}
