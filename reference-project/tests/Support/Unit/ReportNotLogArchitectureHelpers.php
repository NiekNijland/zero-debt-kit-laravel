<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\PhpFileInspector;

final class ReportNotLogArchitectureHelpers
{
    private const array LOG_FACADES = ['Log', 'Illuminate\Support\Facades\Log'];

    private const array SEVERE_LEVELS = ['warning', 'error', 'critical', 'alert', 'emergency'];

    /**
     * @return list<string> `file:line` of every Log::warning or more severe call directly inside a catch block, and of every literal 'tenant_id' key in a Log:: context
     */
    public static function offendersInApp(): array
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        foreach (new PhpFileInspector()->phpFiles($root . '/app') as $file) {
            $source = (string) file_get_contents($file);

            foreach ([...self::severeLogInCatchLines($source), ...self::tenantIdLogKeyLines($source)] as $line) {
                $offenders[] = substr($file, strlen($root) + 1) . ':' . $line;
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * @return list<int>
     */
    public static function severeLogInCatchLines(string $source): array
    {
        $lines = [];

        foreach ((new NodeFinder())->findInstanceOf(self::statements($source), Catch_::class) as $catch) {
            foreach (self::callsOutsideClosures($catch->stmts) as $call) {
                if (in_array(self::logMethod($call), self::SEVERE_LEVELS, true)) {
                    $lines[] = $call->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @return list<int>
     */
    public static function tenantIdLogKeyLines(string $source): array
    {
        $lines = [];
        $finder = new NodeFinder();

        foreach ($finder->find(self::statements($source), static fn (Node $node): bool => ($node instanceof StaticCall || $node instanceof MethodCall) && self::logMethod($node) !== null) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall) {
                continue;
            }

            foreach ($finder->findInstanceOf($call->args, Node\ArrayItem::class) as $item) {
                if ($item->key instanceof String_ && $item->key->value === 'tenant_id') {
                    $lines[] = $item->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @param array<Node> $statements
     * @return list<StaticCall|MethodCall>
     */
    private static function callsOutsideClosures(array $statements): array
    {
        $visitor = new class() extends NodeVisitorAbstract
        {
            /** @var list<StaticCall|MethodCall> */
            public array $calls = [];

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof StaticCall || $node instanceof MethodCall) {
                    $this->calls[] = $node;
                }

                return null;
            }
        };

        (new NodeTraverser($visitor))->traverse($statements);

        return $visitor->calls;
    }

    /**
     * The lower-case method a Log facade call ends in: `Log::error()` and `Log::channel('x')->error()` both give `error`.
     */
    private static function logMethod(StaticCall|MethodCall $call): ?string
    {
        if (! $call->name instanceof Node\Identifier) {
            return null;
        }

        $receiver = $call instanceof MethodCall ? $call->var : $call;

        if ($receiver instanceof StaticCall && $receiver->class instanceof Name && in_array($receiver->class->toString(), self::LOG_FACADES, true)) {
            return $call->name->toLowerString();
        }

        return null;
    }

    /**
     * @return list<Node>
     */
    private static function statements(string $source): array
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];

        return array_values((new NodeTraverser(new NameResolver()))->traverse($statements));
    }
}
