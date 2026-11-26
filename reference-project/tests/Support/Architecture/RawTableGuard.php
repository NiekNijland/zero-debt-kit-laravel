<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * A table or a statement reached without a model. A guard with one allowed file: the file is an argument, so the allowance is counted where the test passes it.
 */
final readonly class RawTableGuard
{
    private const array RAW_METHODS = ['table', 'select', 'selectone', 'selectresultsets', 'scalar', 'statement', 'unprepared', 'raw', 'insert', 'update', 'delete', 'affectingstatement', 'cursor'];

    private const array FACADES = ['DB', 'Illuminate\Support\Facades\DB'];

    private const string MESSAGE = 'a raw table or statement without a model: reach the table through its Eloquent model; raw SQL belongs in a migration or in the one allowed lock class';

    /**
     * @param list<string> $directories folders under the root that the tree scan reads, relative to it; some may be absent
     * @param list<string> $allowedFiles files relative to the root that may hold raw SQL (an advisory lock has no Eloquent form)
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $directories,
        private array $allowedFiles,
    ) {
    }

    /**
     * @return list<string> `line N: message` per raw call in the source
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
                $relative = substr($file, strlen($this->root) + 1);

                if (in_array($relative, $this->allowedFiles, true)) {
                    continue;
                }

                foreach ($this->lines((string) file_get_contents($file)) as $line) {
                    $violations[] = $relative . ':' . $line . ': ' . self::MESSAGE;
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
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $lines = [];

        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall) as $call) {
            $onFacade = $call instanceof StaticCall && self::isFacade($call);
            $onConnection = $call instanceof MethodCall && $call->var instanceof StaticCall && self::isFacade($call->var) && self::name($call->var) === 'connection';

            if (($onFacade || $onConnection) && in_array(self::name($call), self::RAW_METHODS, true)) {
                $lines[] = $call->getStartLine();
            }
        }

        sort($lines);

        return $lines;
    }

    private static function isFacade(StaticCall $call): bool
    {
        return $call->class instanceof Name && in_array(ltrim($call->class->toString(), '\\'), self::FACADES, true);
    }

    private static function name(StaticCall|MethodCall $call): string
    {
        return $call->name instanceof Identifier ? mb_strtolower($call->name->toString()) : '';
    }
}
