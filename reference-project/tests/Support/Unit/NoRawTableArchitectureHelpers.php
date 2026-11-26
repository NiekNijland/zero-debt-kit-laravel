<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use App\Modules\Stock\Support\PostgresAdvisoryLock;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;
use Tests\Support\Architecture\PhpFileInspector;

final class NoRawTableArchitectureHelpers
{
    private const array RAW_METHODS = ['table', 'select', 'selectone', 'selectresultsets', 'scalar', 'statement', 'unprepared', 'raw', 'insert', 'update', 'delete', 'affectingstatement', 'cursor'];

    private const array FACADES = ['DB', 'Illuminate\Support\Facades\DB'];

    /**
     * @return list<string> `file:line` of every raw table or statement in the application code; PostgreSQL advisory locks have no Eloquent form and live in PostgresAdvisoryLock
     */
    public static function offenders(): array
    {
        $root = dirname(__DIR__, 3);
        $inspector = new PhpFileInspector();
        $advisoryLock = new ReflectionClass(PostgresAdvisoryLock::class)->getFileName();
        $offenders = [];

        foreach (['app', 'routes', 'bootstrap', 'config'] as $directory) {
            foreach ($inspector->phpFiles($root . '/' . $directory, mayBeAbsent: true) as $file) {
                if ($file === $advisoryLock) {
                    continue;
                }

                foreach (self::rawCallLines((string) file_get_contents($file)) as $line) {
                    $offenders[] = substr($file, strlen($root) + 1) . ':' . $line;
                }
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * @return list<int>
     */
    public static function rawCallLines(string $source): array
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
