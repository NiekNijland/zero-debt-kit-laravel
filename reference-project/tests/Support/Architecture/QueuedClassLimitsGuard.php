<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * A queued concrete class states its limits itself: `$timeout`, and `$tries` or `retryUntil()`. The class, a parent, a
 * trait, a promoted constructor parameter and the attribute form (`#[Timeout]`, `#[Tries]`; the attributes of the
 * framework's queue namespace, verify the names against the installed release in step 1) all count. A queued class is
 * one that reaches the contract through a parent, an interface or a sub-interface (`ClassGraph`).
 */
final readonly class QueuedClassLimitsGuard
{
    private const array QUEUED = [ShouldQueue::class, ShouldQueueAfterCommit::class];

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
     * @param array<string, string> $sources
     *
     * @return list<string>
     */
    private function check(array $sources): array
    {
        $graph = ClassGraph::of($sources);
        $violations = [];

        foreach ($graph->concreteClasses() as $class) {
            if ($graph->reaches($class, self::QUEUED) === null) {
                continue;
            }

            $prefix = ($class['path'] === '' ? '' : $class['path'] . ': ') . $class['name'];

            if ($graph->property($class, 'timeout') === null && ! $graph->hasAttribute($class, 'Timeout')) {
                $violations[] = $prefix . ' is queued without $timeout: declare public int $timeout = <seconds>, at most the timeout of the supervisor of its queue in config/horizon.php, so the limit of the work stands in the class';
            }

            if ($graph->property($class, 'tries') === null && ! $graph->hasMethod($class, 'retryUntil') && ! $graph->hasAttribute($class, 'Tries')) {
                $violations[] = $prefix . ' is queued without $tries: declare public int $tries = <attempts> (1 when a second run would repeat a side effect), or retryUntil() when the work retries until a moment';
            }
        }

        return $violations;
    }
}
