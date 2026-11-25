<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\PhpFileInspector;

final class NoSingleUseTraitsArchitectureHelpers
{
    /**
     * Every trait declared in the sources with the number of distinct classes, enums or traits that use it; a use inside
     * an anonymous class counts once per anonymous class.
     *
     * @param list<string> $sources
     * @return array<string, int>
     */
    public static function traitUsers(array $sources): array
    {
        $declared = [];
        $users = [];
        $finder = new NodeFinder();

        foreach ($sources as $index => $source) {
            $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);

            foreach ($finder->findInstanceOf($statements, ClassLike::class) as $class) {
                if ($class instanceof Trait_ && $class->namespacedName !== null) {
                    $declared[] = $class->namespacedName->toString();
                }

                $user = $class->namespacedName?->toString() ?? $index . '@' . $class->getStartLine();

                foreach ($class->stmts as $statement) {
                    if (! $statement instanceof TraitUse) {
                        continue;
                    }

                    foreach ($statement->traits as $trait) {
                        $users[$trait->toString()][$user] = true;
                    }
                }
            }
        }

        $counts = [];

        foreach ($declared as $trait) {
            $counts[$trait] = count($users[$trait] ?? []);
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param list<string> $sources
     * @return list<string>
     */
    public static function singleUseTraits(array $sources): array
    {
        return array_keys(array_filter(self::traitUsers($sources), static fn (int $users): bool => $users === 1));
    }

    /**
     * @return list<string>
     */
    public static function singleUseTraitsInApp(): array
    {
        $files = new PhpFileInspector()->phpFiles(dirname(__DIR__, 3) . '/app');

        return self::singleUseTraits(array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
    }
}
