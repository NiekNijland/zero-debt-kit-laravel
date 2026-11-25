<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class PhpSource
{
    /**
     * @return list<Node\Stmt> the statements of a file with every name resolved
     */
    public static function statements(string $file): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $statements = $parser->parse((string) file_get_contents($file)) ?? [];

        $traverser = new NodeTraverser(new NameResolver());

        return array_values(array_filter(
            $traverser->traverse($statements),
            static fn (Node $node): bool => $node instanceof Node\Stmt,
        ));
    }
}
