<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * The one place that parses PHP: every guard and every reader of this folder gets its tree from here, so there is one parser and one
 * way to resolve names. A guard never calls the parser factory or the name resolver itself.
 */
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

        return array_values(array_filter(
            self::resolved((string) file_get_contents($file)),
            static fn (Node $node): bool => $node instanceof Node\Stmt,
        ));
    }

    /**
     * @return list<Node\Stmt> the statements of a source text, names as written
     */
    public static function parse(string $source): array
    {
        return (new ParserFactory())->createForHostVersion()->parse($source) ?? [];
    }

    /**
     * @param bool $preserveOriginal keep the name as written next to the resolved one (a reader that reports the spelling needs it)
     *
     * @return list<Node> the statements of a source text with every name resolved
     */
    public static function resolved(string $source, bool $preserveOriginal = false): array
    {
        return self::traverse(self::parse($source), new NameResolver(null, $preserveOriginal ? ['preserveOriginalNames' => true] : []));
    }

    /**
     * The visitor entry: runs the visitors over a tree in order and returns the tree. A guard that needs a visitor of its own passes it here.
     *
     * @param array<Node> $statements
     *
     * @return list<Node>
     */
    public static function traverse(array $statements, NodeVisitor ...$visitors): array
    {
        return array_values((new NodeTraverser(...$visitors))->traverse($statements));
    }
}
