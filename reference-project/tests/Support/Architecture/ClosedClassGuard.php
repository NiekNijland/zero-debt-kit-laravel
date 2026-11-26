<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Error;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

final readonly class ClosedClassGuard
{
    private const array MESSAGES = [
        'open-class' => 'is not final: declare it `final`. A real shared base is `abstract`; a concrete class that has to stay open is a decision row with its reason and carries the docblock tag `@final`, the one marker the formatter rule skips',
        'extended-concrete' => 'is concrete and another class extends it: make the parent `abstract` and move what only one child uses into that child, or fold the children into the parent',
        'stale-open' => 'carries the docblock tag `@final` and is final or abstract: delete the tag',
    ];

    /**
     * A concrete class that has to stay open carries the docblock tag `@final` (a decision row holds its reason): the tag is the one marker, in the
     * code, that both the formatter rule and this guard read, so there is no list of class names beside the code to keep in step with it.
     *
     * @param string $source the folder of the application code, relative to the root
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string> one "<rule> <class>" per finding, sorted: a rule of MESSAGES, then the resolved class name
     */
    public function violations(): array
    {
        $found = [];
        $concrete = [];
        $extended = [];
        $tagged = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            try {
                $statements = PhpSource::statements($file);
            } catch (Error $error) {
                $found[] = 'unreadable ' . substr($file, strlen($this->root) + 1) . ': ' . $error->getMessage();

                continue;
            }

            foreach (new NodeFinder()->findInstanceOf($statements, Class_::class) as $class) {
                // An anonymous class that extends a concrete class keeps that class open as much as a named one does.
                if ($class->extends instanceof Name) {
                    $extended[$class->extends->toString()] = true;
                }

                if (! $class->namespacedName instanceof Name) {
                    continue;
                }

                $name = $class->namespacedName->toString();
                $tag = preg_match('/@final(?![A-Za-z0-9_])/', (string) $class->getDocComment()) === 1;

                if (! $class->isAbstract()) {
                    $concrete[$name] = $class->isFinal();
                }

                if ($tag && ($class->isAbstract() || $class->isFinal())) {
                    $found[] = "stale-open {$name}";
                }

                if ($tag) {
                    $tagged[$name] = true;
                }
            }
        }

        foreach ($concrete as $name => $final) {
            if (! $final && ! isset($tagged[$name])) {
                $found[] = "open-class {$name}";
            }

            if (isset($extended[$name])) {
                $found[] = "extended-concrete {$name}";
            }
        }

        $found = array_values(array_unique($found));
        sort($found, SORT_STRING);

        return $found;
    }

    /**
     * @param list<string> $violations entries of violations()
     * @return list<string>
     */
    public function explain(array $violations): array
    {
        return array_map(static function (string $violation): string {
            [$rule, $name] = array_pad(explode(' ', $violation, 2), 2, '');

            return isset(self::MESSAGES[$rule]) ? "{$name} " . self::MESSAGES[$rule] . '.' : $violation;
        }, $violations);
    }
}
