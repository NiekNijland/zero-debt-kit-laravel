<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldQueue;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class QueuedClassLimitsGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/app') as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = substr($file, strlen($this->root) + 1) . ': ' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $violations = [];

        foreach ((new NodeFinder())->findInstanceOf($statements, Class_::class) as $class) {
            if (! self::isQueued($class)) {
                continue;
            }

            $name = $class->namespacedName?->toString() ?? 'an anonymous class on line ' . $class->getStartLine();

            if (! self::declares($class, 'timeout')) {
                $violations[] = $name . ' is queued without $timeout: declare public int $timeout = <seconds>, at most the timeout of the supervisor of its queue in config/horizon.php, so the limit of the work stands in the class';
            }

            if (! self::declares($class, 'tries') && $class->getMethod('retryUntil') === null && ! self::inherits($class, 'retryUntil')) {
                $violations[] = $name . ' is queued without $tries: declare public int $tries = <attempts> (1 when a second run would repeat a side effect), or retryUntil() when the work retries until a moment';
            }
        }

        return $violations;
    }

    private static function isQueued(Class_ $class): bool
    {
        foreach ([...$class->implements, ...array_filter([$class->extends])] as $ancestor) {
            if (is_a(ltrim($ancestor->toString(), '\\'), ShouldQueue::class, true)) {
                return true;
            }
        }

        return false;
    }

    private static function declares(Class_ $class, string $property): bool
    {
        return $class->getProperty($property) !== null || self::inherits($class, $property);
    }

    private static function inherits(Class_ $class, string $member): bool
    {
        $parent = $class->extends === null ? null : ltrim($class->extends->toString(), '\\');

        return $parent !== null && class_exists($parent) && (property_exists($parent, $member) || method_exists($parent, $member));
    }
}
