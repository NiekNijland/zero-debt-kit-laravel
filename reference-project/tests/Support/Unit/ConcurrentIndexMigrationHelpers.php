<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class ConcurrentIndexMigrationHelpers
{
    /**
     * @return list<string>
     */
    public static function blockingIndexTables(string $source): array
    {
        $finder = new NodeFinder();
        $statements = self::upStatements($finder, $source);

        $named = static fn (Node $node, array $names): bool => ($node instanceof StaticCall || $node instanceof MethodCall)
            && $node->name instanceof Identifier
            && in_array(mb_strtolower($node->name->toString()), $names, true);
        $blueprintCalls = static fn (string $method): array => array_values(array_filter(
            $finder->find($statements, static fn (Node $node): bool => ($node instanceof StaticCall || $node instanceof MethodCall)
                && $named($node, [$method])
                && array_filter($node->getArgs(), static fn (Arg $argument): bool => $argument->value instanceof Closure || $argument->value instanceof ArrowFunction) !== []),
            static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall,
        ));
        $tableName = static function (StaticCall|MethodCall $call): ?string {
            $first = $call->getArgs()[0]->value ?? null;

            return $first instanceof String_ ? $first->value : null;
        };
        $sqlTable = static fn (string $pattern, string $sql): ?string => preg_match($pattern, $sql, $match) === 1
            ? trim((string) strrchr('.' . $match[1], '.'), '."')
            : null;

        $sql = self::sqlStrings($finder, $statements);

        $created = array_filter([
            ...array_map($tableName, $blueprintCalls('create')),
            ...array_map(static fn (string $query): ?string => $sqlTable('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([\w."]+)/i', $query), $sql),
        ], static fn (?string $table): bool => $table !== null && $table !== '');
        $blocking = [];

        foreach ($blueprintCalls('table') as $call) {
            $indexCall = $finder->findFirst($call->getArgs(), static fn (Node $node): bool => $named($node, [
                'index', 'unique', 'primary', 'foreign', 'constrained', 'rawindex', 'fulltext', 'spatialindex',
                'morphs', 'nullablemorphs', 'numericmorphs', 'nullablenumericmorphs',
                'uuidmorphs', 'nullableuuidmorphs', 'ulidmorphs', 'nullableulidmorphs',
            ]));

            if ($indexCall !== null) {
                $blocking[] = $tableName($call) ?? '(dynamic table name)';
            }
        }

        foreach ($sql as $query) {
            if (preg_match('/CREATE\s+(UNIQUE\s+)?INDEX\s+(?!CONCURRENTLY)/i', $query) === 1) {
                $blocking[] = $sqlTable('/\bON\s+(?:ONLY\s+)?([\w."]+)/i', $query) ?? '(raw SQL)';
            }

            if (preg_match('/FOREIGN\s+KEY/i', $query) === 1 && preg_match('/NOT\s+VALID/i', $query) !== 1) {
                $blocking[] = $sqlTable('/ALTER\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:ONLY\s+)?([\w."]+)/i', $query)
                    ?? $sqlTable('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([\w."]+)/i', $query)
                    ?? '(raw SQL)';
            }
        }

        return array_values(array_unique(array_diff($blocking, $created)));
    }

    /**
     * @return list<Node\Stmt>
     */
    private static function upStatements(NodeFinder $finder, string $source): array
    {
        $methods = [];

        foreach ($finder->findInstanceOf((new ParserFactory())->createForHostVersion()->parse($source) ?? [], ClassMethod::class) as $method) {
            $methods[mb_strtolower($method->name->toString())] = $method->stmts ?? [];
        }

        $statements = [];
        $pending = ['up'];
        $followed = [];

        while ($pending !== []) {
            $name = array_pop($pending);

            if (isset($followed[$name]) || ! isset($methods[$name])) {
                continue;
            }

            $followed[$name] = true;
            array_push($statements, ...$methods[$name]);

            foreach ($finder->find($methods[$name], static fn (Node $node): bool => (
                ($node instanceof MethodCall && $node->var instanceof Variable && $node->var->name === 'this')
                || ($node instanceof StaticCall && $node->class instanceof Name && in_array(mb_strtolower($node->class->toString()), ['self', 'static'], true))
            ) && $node->name instanceof Identifier) as $call) {
                if (($call instanceof MethodCall || $call instanceof StaticCall) && $call->name instanceof Identifier) {
                    $pending[] = mb_strtolower($call->name->toString());
                }
            }
        }

        return $statements;
    }

    /**
     * @param list<Node\Stmt> $statements
     * @return list<string>
     */
    private static function sqlStrings(NodeFinder $finder, array $statements): array
    {
        $sql = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedString) as $string) {
            if ($string instanceof String_) {
                $sql[] = $string->value;
            } elseif ($string instanceof InterpolatedString) {
                $sql[] = implode('?', array_map(static fn (Node $part): string => $part instanceof InterpolatedStringPart ? $part->value : '', $string->parts));
            }
        }

        return $sql;
    }

    public static function indexMigrationSource(string $up, string $members = ''): string
    {
        return "<?php\nreturn new class() extends Migration {\n{$members}\n    public function up(): void\n    {\n{$up}\n    }\n};\n";
    }
}
