<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;

final class PhpSource
{
    /**
     * @return list<Node\Stmt> the statements of a file with every name resolved
     */
    public static function statements(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException("the file {$file} does not exist: a guard that reads a named file fails when it is renamed, it does not read an empty tree");
        }

        $parser = (new ParserFactory())->createForHostVersion();
        $statements = $parser->parse((string) file_get_contents($file)) ?? [];

        $traverser = new NodeTraverser(new NameResolver());

        return array_values(array_filter(
            $traverser->traverse($statements),
            static fn (Node $node): bool => $node instanceof Node\Stmt,
        ));
    }
}
