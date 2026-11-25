<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\PhpFileInspector;

final class ThinControllersArchitectureHelpers
{
    public const int MAX_INVOKE_LINES = 40;

    private const array WRITES = [
        'save', 'savequietly', 'update', 'updatequietly', 'updateorcreate', 'updateorinsert', 'firstorcreate', 'create', 'createquietly', 'createmany',
        'delete', 'deletequietly', 'forcedelete', 'forcedeletequietly', 'restore', 'restorequietly', 'attach', 'sync', 'syncwithoutdetaching',
        'detach', 'updateexistingpivot', 'insert', 'insertorignore', 'insertgetid', 'upsert', 'increment', 'decrement', 'incrementeach',
        'decrementeach', 'lockforupdate', 'sharedlock', 'transaction', 'begintransaction',
    ];

    private const array DB_FACADES = ['DB', 'Illuminate\Support\Facades\DB'];

    private const array COLLABORATOR_FOLDERS = ['\Actions\\', '\Services\\'];

    /**
     * @return list<string> `line: reason` of every write, lock, transaction or `DB::` call in the source, and of an `__invoke` that is too long
     */
    public static function violationsIn(string $source): array
    {
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $finder = new NodeFinder();
        $collaborators = self::collaborators($finder, $statements);
        $violations = [];

        $calls = $finder->find(
            $statements,
            static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall,
        );

        foreach ($calls as $call) {
            if (! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)) {
                continue;
            }

            $line = $call->getStartLine();

            if ($call instanceof StaticCall && $call->class instanceof Name && in_array($call->class->toString(), self::DB_FACADES, true)) {
                $violations[] = $line . ': DB::';

                continue;
            }

            $name = $call->name instanceof Identifier ? mb_strtolower($call->name->toString()) : '';

            if (! in_array($name, self::WRITES, true)) {
                continue;
            }

            if (! $call instanceof StaticCall && self::callsCollaborator($call->var, $collaborators)) {
                continue;
            }

            $violations[] = $line . ': ' . $name;
        }

        foreach ($finder->findInstanceOf($statements, ClassMethod::class) as $method) {
            $length = $method->getEndLine() - $method->getStartLine() + 1;

            if ($method->name->toLowerString() === '__invoke' && $length > self::MAX_INVOKE_LINES) {
                $violations[] = $method->getStartLine() . ': __invoke has ' . $length . ' lines';
            }
        }

        usort($violations, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

        return $violations;
    }

    /**
     * @return list<string> `file:line: reason` for every controller
     */
    public static function offendersInControllers(): array
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        foreach (new PhpFileInspector()->phpFiles($root . '/app/Http/Controllers') as $file) {
            foreach (self::violationsIn((string) file_get_contents($file)) as $violation) {
                $offenders[] = substr($file, strlen($root) + 1) . ':' . $violation;
            }
        }

        return $offenders;
    }

    /**
     * @param array<Node> $statements
     * @return list<string> the properties whose class is an Action or a Service of a module
     */
    private static function collaborators(NodeFinder $finder, array $statements): array
    {
        $collaborators = [];

        foreach ($finder->findInstanceOf($statements, Class_::class) as $class) {
            foreach ($class->getProperties() as $property) {
                if (self::isCollaborator($property->type)) {
                    foreach ($property->props as $item) {
                        $collaborators[] = $item->name->toString();
                    }
                }
            }

            $constructor = $class->getMethod('__construct');

            foreach ($constructor !== null ? $constructor->params : [] as $param) {
                if ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name) && self::isCollaborator($param->type)) {
                    $collaborators[] = $param->var->name;
                }
            }
        }

        return $collaborators;
    }

    private static function isCollaborator(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Name) {
            return false;
        }

        $class = '\\' . $type->toString();

        foreach (self::COLLABORATOR_FOLDERS as $folder) {
            if (str_contains($class, $folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $collaborators
     */
    private static function callsCollaborator(Node $receiver, array $collaborators): bool
    {
        return $receiver instanceof PropertyFetch
            && $receiver->var instanceof Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Identifier
            && in_array($receiver->name->toString(), $collaborators, true);
    }
}
