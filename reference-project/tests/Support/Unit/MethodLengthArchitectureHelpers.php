<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\PhpFileInspector;

final class MethodLengthArchitectureHelpers
{
    public const int MAX_LINES = 80;

    /**
     * Methods whose declaration spans more than the limit, from the first line of the signature (attributes included)
     * to the closing brace, as `Class::method` => lines.
     *
     * @return array<string, int>
     */
    public static function longMethods(string $source, int $limit = self::MAX_LINES): array
    {
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $long = [];

        foreach ((new NodeFinder())->findInstanceOf($statements, ClassLike::class) as $class) {
            $name = $class->namespacedName?->toString() ?? 'class@' . $class->getStartLine();

            foreach ($class->getMethods() as $method) {
                $lines = $method->getEndLine() - $method->getStartLine() + 1;

                if ($lines > $limit) {
                    $long[$name . '::' . $method->name->toString()] = $lines;
                }
            }
        }

        return $long;
    }

    /**
     * @return array<string, int>
     */
    public static function longMethodsInApp(): array
    {
        $long = [];

        foreach (new PhpFileInspector()->phpFiles(dirname(__DIR__, 3) . '/app') as $file) {
            $long += self::longMethods((string) file_get_contents($file));
        }

        ksort($long);

        return $long;
    }
}
