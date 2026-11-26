<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

/**
 * A concrete class that is unique through the contract (declared itself, inherited from a parent, or reached through a
 * sub-interface of the contract) supplies its own key: `uniqueId()`, from the class, a parent or a trait, or a
 * `$uniqueId` property that is set per instance (promoted, or declared without a default). A property with a default
 * is one lock per class, which is what the method is there to avoid. An abstract class is skipped: its children are
 * checked with everything they inherit. A parent the scanned tree does not hold is read through the autoloader
 * (`ClassGraph`).
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
        $graph = ClassGraph::of($sources);
        $violations = [];

        foreach ($graph->concreteClasses() as $class) {
            $contract = $graph->reaches($class, self::UNIQUE_CONTRACTS);

            if ($contract === null || $graph->hasMethod($class, 'uniqueId', callable: true) || $graph->property($class, 'uniqueId') === 'instance') {
                continue;
            }

            $violations[] = ($class['path'] === '' ? '' : $class['path'] . ': ') . $class['name'] . ' implements ' . basename(str_replace('\\', '/', $contract))
                . ($graph->property($class, 'uniqueId') === 'default'
                    ? ' with a $uniqueId property that has a default, so the lock is per class: set it per instance (promote it in the constructor) or declare public function uniqueId(): string'
                    : ' without uniqueId(): declare public function uniqueId(): string and return the key of the record the job works on, so the lock is per record and not per class');
        }

        return $violations;
    }
}
