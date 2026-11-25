<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class UniqueJobGuard
{
    private const array UNIQUE_CONTRACTS = [ShouldBeUnique::class, ShouldBeUniqueUntilProcessing::class];

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
            $contracts = array_intersect(array_map(static fn (Name $name): string => ltrim($name->toString(), '\\'), $class->implements), self::UNIQUE_CONTRACTS);

            if ($contracts === [] || $class->getMethod('uniqueId') !== null) {
                continue;
            }

            $name = $class->namespacedName?->toString() ?? 'an anonymous class on line ' . $class->getStartLine();
            $violations[] = $name . ' implements ' . basename(str_replace('\\', '/', reset($contracts)))
                . ' without uniqueId(): declare public function uniqueId(): string and return the key of the record the job works on, so the lock is per record and not per class';
        }

        return $violations;
    }
}
