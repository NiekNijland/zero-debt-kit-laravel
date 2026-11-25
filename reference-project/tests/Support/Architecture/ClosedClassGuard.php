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
        'open-class' => 'is not final: declare it `final`. A real shared base is `abstract`; a concrete class that has to stay open is a decision row of the list of open classes, with its reason',
        'extended-concrete' => 'is concrete and another class extends it: make the parent `abstract` and move what only one child uses into that child, or fold the children into the parent',
        'stale-open' => 'is on the list of open classes and is final, abstract or gone: delete the line',
    ];

    /**
     * @param string $source the folder of the application code, relative to the root
     * @param list<string> $open the counted list of open classes, by resolved name: each one is a decision with a reason, never an exemption that grows
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
        private array $open = [],
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

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            try {
                $statements = PhpSource::statements($file);
            } catch (Error $error) {
                $found[] = 'unreadable ' . substr($file, strlen($this->root) + 1) . ': ' . $error->getMessage();

                continue;
            }

            foreach (new NodeFinder()->findInstanceOf($statements, Class_::class) as $class) {
                if (! $class->namespacedName instanceof Name) {
                    continue;
                }

                $name = $class->namespacedName->toString();

                if ($class->extends instanceof Name) {
                    $extended[$class->extends->toString()] = true;
                }

                if (! $class->isAbstract()) {
                    $concrete[$name] = $class->isFinal();
                }
            }
        }

        foreach ($concrete as $name => $final) {
            if (! $final && ! in_array($name, $this->open, true)) {
                $found[] = "open-class {$name}";
            }

            if (isset($extended[$name]) && ! in_array($name, $this->open, true)) {
                $found[] = "extended-concrete {$name}";
            }
        }

        foreach ($this->open as $name) {
            if (($concrete[$name] ?? true) === true) {
                $found[] = "stale-open {$name}";
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
