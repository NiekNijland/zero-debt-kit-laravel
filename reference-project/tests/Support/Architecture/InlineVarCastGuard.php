<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

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
use PhpParser\NodeVisitorAbstract;

/**
 * A `@var` docblock on a statement, or on an expression inside a function body, tells the analyser a type it never checks.
 * On a declaration (property, constant, class, function) it declares a type and passes; a parameter carries its type as `@param`.
 */
final readonly class InlineVarCastGuard
{
    private const string MESSAGE = 'an inline @var narrows a type the analyser never checks: narrow it with a typed helper, a typed return or an assertion the code runs, and keep @var for a property or a constant';

    /**
     * @param list<string> $directories folders under the root that the tree scan reads, relative to it; some may be absent
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $directories,
    ) {
    }

    /**
     * @return list<string> `line N: message` per inline @var in the source
     */
    public function inSource(string $source): array
    {
        return array_map(static fn (int $line): string => 'line ' . $line . ': ' . self::MESSAGE, $this->lines($source));
    }

    /**
     * @return list<string> `file:line: message` relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->directories as $directory) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $directory, mayBeAbsent: true) as $file) {
                foreach ($this->lines((string) file_get_contents($file)) as $line) {
                    $violations[] = substr($file, strlen($this->root) + 1) . ':' . $line . ': ' . self::MESSAGE;
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
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

        PhpSource::traverse(PhpSource::parse($source), $visitor);

        $lines = array_values(array_unique($visitor->lines));
        sort($lines);

        return $lines;
    }
}
