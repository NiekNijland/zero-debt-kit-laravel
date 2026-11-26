<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * A concrete class that is unique through the contract (declared itself, inherited from a parent, or reached through a
 * sub-interface of the contract) supplies its own key: `uniqueId()`, from the class, a parent or a trait, or a
 * `$uniqueId` property that is set per instance (promoted, or declared without a default). A property with a default
 * is one lock per class, which is what the method is there to avoid. An abstract class is skipped: its children are
 * checked with everything they inherit. A parent the scanned tree does not hold is read through the autoloader.
 */
final readonly class UniqueJobGuard
{
    private const array UNIQUE_CONTRACTS = [ShouldBeUnique::class, ShouldBeUniqueUntilProcessing::class];

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
        $sources = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            $sources[substr($file, strlen($this->root) + 1)] = (string) file_get_contents($file);
        }

        return $this->check($sources);
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        return $this->check(['' => $source]);
    }

    /**
     * @param array<string, string> $sources source code by the path that a finding names (empty for a lone example)
     *
     * @return list<string>
     */
    private function check(array $sources): array
    {
        $types = [];
        $concrete = [];

        foreach ($sources as $path => $source) {
            $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);

            foreach ((new NodeFinder())->findInstanceOf($statements, ClassLike::class) as $type) {
                $info = self::describe($type);

                if ($type->namespacedName instanceof Name) {
                    $types[$type->namespacedName->toString()] = $info;
                }

                if ($type instanceof Class_ && ! $type->isAbstract()) {
                    $concrete[] = [$type->namespacedName?->toString() ?? 'an anonymous class on line ' . $type->getStartLine(), $info, $path];
                }
            }
        }

        $violations = [];

        foreach ($concrete as [$name, $info, $path]) {
            $contract = self::contractOf($info, $types);

            if ($contract === null) {
                continue;
            }

            $key = self::suppliesKey($info, $types);

            if ($key === 'yes') {
                continue;
            }

            $violations[] = ($path === '' ? '' : $path . ': ') . $name . ' implements ' . basename(str_replace('\\', '/', $contract))
                . ($key === 'default'
                    ? ' with a $uniqueId property that has a default, so the lock is per class: set it per instance (promote it in the constructor) or declare public function uniqueId(): string'
                    : ' without uniqueId(): declare public function uniqueId(): string and return the key of the record the job works on, so the lock is per record and not per class');
        }

        return $violations;
    }

    /**
     * @return array{extends: list<string>, uses: list<string>, methods: list<string>, key: 'default'|'instance'|null}
     */
    private static function describe(ClassLike $type): array
    {
        $extends = [];

        if ($type instanceof Class_) {
            $extends = [...($type->extends instanceof Name ? [$type->extends->toString()] : []), ...array_map(static fn (Name $name): string => $name->toString(), $type->implements)];
        } elseif ($type instanceof Interface_) {
            $extends = array_map(static fn (Name $name): string => $name->toString(), $type->extends);
        }

        $uses = [];

        foreach ($type->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $uses[] = $trait->toString();
            }
        }

        $key = null;

        foreach ($type->getProperties() as $property) {
            foreach ($property->props as $item) {
                if ($item->name->toString() === 'uniqueId') {
                    $key = $item->default === null ? 'instance' : 'default';
                }
            }
        }

        $constructor = $type->getMethod('__construct');

        foreach ($constructor?->params ?? [] as $parameter) {
            if ($parameter instanceof Param && $parameter->flags !== 0 && $parameter->var instanceof Variable && $parameter->var->name === 'uniqueId') {
                $key = 'instance';
            }
        }

        return [
            'extends' => $extends,
            'uses' => $uses,
            'methods' => $type instanceof Interface_ ? [] : array_map(static fn (ClassMethod $method): string => $method->name->toLowerString(), $type->getMethods()),
            'key' => $key,
        ];
    }

    /**
     * The contract the class is unique through, or null.
     *
     * @param array{extends: list<string>, uses: list<string>, methods: list<string>, key: 'default'|'instance'|null} $info
     * @param array<string, array{extends: list<string>, uses: list<string>, methods: list<string>, key: 'default'|'instance'|null}> $types
     */
    private static function contractOf(array $info, array $types): ?string
    {
        foreach (self::ancestors($info['extends'], $types) as $ancestor) {
            if (in_array($ancestor, self::UNIQUE_CONTRACTS, true)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * @param list<string> $names
     * @param array<string, array{extends: list<string>, uses: list<string>, methods: list<string>, key: 'default'|'instance'|null}> $types
     *
     * @return list<string> every class and interface that the names reach, the names included
     */
    private static function ancestors(array $names, array $types): array
    {
        $found = [];
        $queue = $names;

        while ($queue !== []) {
            $name = ltrim((string) array_shift($queue), '\\');

            if (in_array($name, $found, true)) {
                continue;
            }

            $found[] = $name;

            if (isset($types[$name])) {
                array_push($queue, ...$types[$name]['extends']);
            } elseif (class_exists($name) || interface_exists($name)) {
                array_push($queue, ...array_values(class_parents($name) ?: []), ...array_values(class_implements($name) ?: []));
            }
        }

        return $found;
    }

    /**
     * 'yes' when the class, a parent or a used trait supplies the key, 'default' when only a property with a default does, 'no' otherwise.
     *
     * @param array{extends: list<string>, uses: list<string>, methods: list<string>, key: 'default'|'instance'|null} $info
     * @param array<string, array{extends: list<string>, uses: list<string>, methods: list<string>, key: 'default'|'instance'|null}> $types
     *
     * @return 'yes'|'default'|'no'
     */
    private static function suppliesKey(array $info, array $types): string
    {
        $default = false;
        $queue = [$info];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (in_array('uniqueid', $current['methods'], true) || $current['key'] === 'instance') {
                return 'yes';
            }

            $default = $default || $current['key'] === 'default';

            foreach ([...$current['extends'], ...$current['uses']] as $name) {
                $name = ltrim($name, '\\');

                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;

                if (isset($types[$name])) {
                    $queue[] = $types[$name];
                } elseif ((class_exists($name) || trait_exists($name)) && method_exists($name, 'uniqueId')) {
                    return 'yes';
                }
            }
        }

        return $default ? 'default' : 'no';
    }
}
