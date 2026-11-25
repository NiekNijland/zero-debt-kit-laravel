<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

final readonly class StoredNameRegisterGuard
{
    /** @var array<string, string> the register kind and the words the guide holds for it: a guide that loses the words fails the guard */
    public const array GUIDE = [
        'queued-classes' => 'every queued job carries its class name in the queue payload',
        'closure-classes' => 'is serialized into the job payload with its defining class as scope and every class its body names',
        'aliases' => '(old name, new name, one tab, sorted by old name)',
    ];

    private const array CALLBACKS = ['catch', 'then', 'finally', 'chain'];

    /**
     * @param string $source the folder of the application code, relative to the root
     * @param string $folder the folder of the register files, one `<kind>.txt` per kind, relative to the root
     * @param string $aliases the tab-separated list of old name and new name, relative to the root; empty when no class is moving
     * @param string $appNamespace the root namespace of the application code
     * @param string $movedInto the namespace prefix of the new layout: a stored class outside it needs a line in the alias list
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
        private string $guide,
        private string $folder,
        private string $aliases,
        private string $appNamespace,
        private string $movedInto,
    ) {
    }

    /**
     * @return list<string> one line per class that is on no register, per register line that names nothing and per fault of the alias list
     */
    public function findings(): array
    {
        $text = is_file($this->root . '/' . $this->guide) ? (string) file_get_contents($this->root . '/' . $this->guide) : '';
        $findings = [];

        foreach (self::GUIDE as $words) {
            if (! str_contains($text, $words)) {
                $findings[] = "{$this->guide} no longer holds the sentence the guard reads ({$words}): restore it";
            }
        }

        [$kinds, $unreadable] = $this->kinds();
        $lines = $this->lines($this->aliases);
        $pairs = array_column(array_map(static fn (string $line): array => explode("\t", $line, 2), array_filter($lines, static fn (string $line): bool => substr_count($line, "\t") === 1)), 1, 0);
        array_push($findings, ...$unreadable, ...$this->aliasFindings($lines, $kinds));

        foreach ($kinds as $kind => $found) {
            $file = "{$this->folder}/{$kind}.txt";
            $registered = $this->lines($file);

            foreach (array_diff($this->withCounterparts($found, $pairs), $registered) as $name) {
                $findings[] = "{$file}: {$name} is missing: add the line, sorted";
            }

            foreach (array_diff($registered, $this->withCounterparts($found, $pairs)) as $name) {
                $findings[] = "{$file}: {$name} names no such class: delete the line";
            }

            if ($registered !== array_values(array_unique($registered)) || $registered !== $this->sorted($registered)) {
                $findings[] = "{$file}: the register is not sorted or holds a line twice";
            }
        }

        return $this->sorted($findings);
    }

    /**
     * @return array{array<string, list<string>>, list<string>} the stored names found in the code by kind, and a line per file the parser cannot read
     */
    private function kinds(): array
    {
        $parents = [];
        $classes = [];
        $closures = [];
        $unreadable = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            try {
                $statements = PhpSource::statements($file);
            } catch (Error $error) {
                $unreadable[] = 'unreadable ' . substr($file, strlen($this->root) + 1) . ': ' . $error->getMessage();

                continue;
            }

            foreach (new NodeFinder()->findInstanceOf($statements, Class_::class) as $class) {
                if ($class->namespacedName instanceof Name) {
                    $name = $class->namespacedName->toString();
                    $parents[$name] = array_map(static fn (Name $parent): string => $parent->toString(), array_values(array_filter([$class->extends, ...$class->implements])));
                    $classes[$name] = $class->isAbstract();
                    array_push($closures, ...$this->closureNames($name, $class));
                }
            }
        }

        $queued = array_keys(array_filter($classes, fn (bool $abstract, string $name): bool => ! $abstract && $this->isQueued($name, $parents), ARRAY_FILTER_USE_BOTH));

        return [['queued-classes' => $this->sorted($queued), 'closure-classes' => $this->sorted(array_values(array_unique($closures)))], $unreadable];
    }

    /**
     * @param array<string, list<string>> $parents
     * @param list<string> $seen
     */
    private function isQueued(string $name, array $parents, array $seen = []): bool
    {
        if ($name === ShouldQueue::class) {
            return true;
        }

        return ! in_array($name, $seen, true) && array_any($parents[$name] ?? [], fn (string $parent): bool => $this->isQueued($parent, $parents, [...$seen, $name]));
    }

    /**
     * @return list<string> the defining class and every class of the application that the body of a closure names, for each closure a queue serializes
     */
    private function closureNames(string $defining, Class_ $class): array
    {
        $names = [];

        foreach (new NodeFinder()->find($class, $this->serializesClosure(...)) as $call) {
            $arguments = $call instanceof FuncCall || $call instanceof MethodCall || $call instanceof StaticCall ? $call->getArgs() : [];

            foreach ($arguments as $argument) {
                if (! $argument->value instanceof Closure) {
                    continue;
                }

                $names[] = $defining;

                foreach (new NodeFinder()->findInstanceOf($argument->value, Name::class) as $name) {
                    if (str_starts_with($name->toString(), $this->appNamespace . '\\')) {
                        $names[] = $name->toString();
                    }
                }
            }
        }

        return $names;
    }

    private function serializesClosure(Node $node): bool
    {
        return match (true) {
            $node instanceof FuncCall => $node->name instanceof Name && $node->name->toString() === 'dispatch',
            $node instanceof StaticCall => $node->class instanceof Name && $node->class->toString() === Queue::class && $node->name instanceof Identifier && $node->name->toString() === 'push',
            $node instanceof MethodCall => $node->name instanceof Identifier && in_array($node->name->toString(), self::CALLBACKS, true) && $this->startsOnBus($node),
            default => false,
        };
    }

    private function startsOnBus(MethodCall $call): bool
    {
        $root = $call->var;

        while ($root instanceof MethodCall) {
            $root = $root->var;
        }

        return $root instanceof StaticCall && $root->class instanceof Name && $root->class->toString() === Bus::class;
    }

    /**
     * @return list<string>
     */
    private function lines(string $file): array
    {
        $path = $this->root . '/' . $file;

        return $file === '' || ! is_file($path) ? [] : array_values(array_filter(explode("\n", (string) file_get_contents($path)), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param list<string> $names
     * @param array<string, string> $pairs old name => new name
     * @return list<string> the names and the other name of each line of the alias list that holds one of them
     */
    private function withCounterparts(array $names, array $pairs): array
    {
        $other = [...$pairs, ...array_flip($pairs)];

        return $this->sorted(array_values(array_unique([...$names, ...array_values(array_intersect_key($other, array_flip($names)))])));
    }

    /**
     * @param list<string> $lines
     * @param array<string, list<string>> $kinds
     * @return list<string>
     */
    private function aliasFindings(array $lines, array $kinds): array
    {
        $findings = [];
        $olds = [];
        $news = [];
        $stored = array_merge([], ...array_values($kinds));

        foreach ($lines as $line) {
            $pair = explode("\t", $line);

            if (count($pair) !== 2 || $pair[0] === '' || $pair[1] === '') {
                $findings[] = "{$this->aliases}: {$line} is not an old name and a new name separated by one tab";

                continue;
            }

            [$olds[], $news[]] = $pair;

            if (! in_array($pair[0], $stored, true) && ! in_array($pair[1], $stored, true)) {
                $findings[] = "{$this->aliases}: {$pair[0]} and {$pair[1]} name no stored class: delete the line";
            }
        }

        if ($lines !== [] && ($olds !== $this->sorted($olds) || $olds !== array_values(array_unique($olds)) || $news !== array_values(array_unique($news)))) {
            $findings[] = "{$this->aliases}: the list is not sorted by old name, or holds an old name or a new name twice";
        }

        if ($lines === []) {
            return $findings;
        }

        foreach ($kinds as $kind => $names) {
            foreach ($names as $name) {
                if (! str_starts_with($name, $this->movedInto) && ! in_array($name, $olds, true)) {
                    $findings[] = "{$this->folder}/{$kind}.txt: {$name} is stored under the old layout and has no line in {$this->aliases}: add the line, old name and new name";
                }
            }
        }

        return $findings;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function sorted(array $lines): array
    {
        sort($lines, SORT_STRING);

        return $lines;
    }
}
