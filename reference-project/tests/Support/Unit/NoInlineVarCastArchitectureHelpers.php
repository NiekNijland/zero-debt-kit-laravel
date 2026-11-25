<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Override;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\PhpFileInspector;

final class NoInlineVarCastArchitectureHelpers
{
    public const array DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'routes'];

    /**
     * A `@var` docblock on a statement, or on an expression inside a function body, tells PHPStan a type it never checks.
     * On a declaration (property, constant, class, function) it declares a type and passes; a parameter carries its type as `@param`.
     *
     * @return list<int>
     */
    public static function inlineVarLines(string $source): array
    {
        $visitor = new class() extends NodeVisitorAbstract
        {
            /** @var list<int> */
            public array $lines = [];

            private int $functionDepth = 0;

            #[Override]
            public function enterNode(Node $node): null
            {
                if ($this->castsWithVar($node)) {
                    $this->lines[] = $node->getDocComment()?->getStartLine() ?? $node->getStartLine();
                }

                if ($node instanceof FunctionLike) {
                    $this->functionDepth++;
                }

                return null;
            }

            #[Override]
            public function leaveNode(Node $node): null
            {
                if ($node instanceof FunctionLike) {
                    $this->functionDepth--;
                }

                return null;
            }

            private function castsWithVar(Node $node): bool
            {
                $doc = $node->getDocComment();

                if ($doc === null || preg_match('/@(?:phpstan-|psalm-)?var\b/', $doc->getText()) !== 1) {
                    return false;
                }

                if ($node instanceof Stmt) {
                    return ! ($node instanceof ClassLike || $node instanceof FunctionLike || $node instanceof Property || $node instanceof ClassConst
                        || $node instanceof Const_ || $node instanceof EnumCase || $node instanceof TraitUse || $node instanceof Use_
                        || $node instanceof GroupUse || $node instanceof Namespace_ || $node instanceof Declare_);
                }

                return $this->functionDepth > 0 && ! $node instanceof Node\Param && ! $node instanceof FunctionLike;
            }
        };

        (new NodeTraverser($visitor))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);

        $lines = array_values(array_unique($visitor->lines));
        sort($lines);

        return $lines;
    }

    /**
     * @return array<string, int>
     */
    public static function inlineVarCounts(): array
    {
        $root = dirname(__DIR__, 3);
        $inspector = new PhpFileInspector();
        $counts = [];

        foreach (self::DIRECTORIES as $directory) {
            foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
                $count = count(self::inlineVarLines((string) file_get_contents($file)));

                if ($count > 0) {
                    $counts[substr($file, strlen($root) + 1)] = $count;
                }
            }
        }

        ksort($counts);

        return $counts;
    }
}
