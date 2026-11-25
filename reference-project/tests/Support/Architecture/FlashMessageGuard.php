<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Modules\Copy\Facades\Copy;
use Inertia\Inertia;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;

final readonly class FlashMessageGuard
{
    private const array TYPES = ['success', 'error', 'info'];

    private const array TRANSLATING_FUNCTIONS = ['__', 'trans', 'trans_choice'];

    private const int HELPER_DEPTH = 3;

    private const string ONE_WAY = "Inertia::flash() takes 'success', 'error' or 'info' and one translated line: Inertia::flash('success', Copy::translate('area.toasts.saved'))";

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/app') as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = substr($file, strlen($this->root) + 1) . ':' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = self::parse($source);
        $violations = [];

        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            if (! ($call instanceof StaticCall || $call instanceof MethodCall || $call instanceof NullsafeMethodCall) || ! $call->name instanceof Identifier || $call->name->toLowerString() !== 'flash') {
                continue;
            }

            if (! $call instanceof StaticCall || ! $call->class instanceof Name || ltrim($call->class->toString(), '\\') !== Inertia::class) {
                $violations[] = $call->getStartLine() . ': a flash that does not go through Inertia never reaches the flash toast: ' . self::ONE_WAY;

                continue;
            }

            $arguments = array_values(array_filter($call->args, static fn (Node $argument): bool => $argument instanceof Arg));
            $type = $arguments[0]->value ?? null;
            $message = $arguments[1]->value ?? null;

            if (count($call->args) !== 2 || ! $type instanceof String_ || ! in_array($type->value, self::TYPES, true)) {
                $violations[] = $call->getStartLine() . ': ' . self::ONE_WAY;

                continue;
            }

            if (! $message instanceof Expr || ! self::isTranslated($message, $statements, self::HELPER_DEPTH)) {
                $violations[] = $call->getStartLine() . ': the message is not a translate call, so the user reads a key, raw text or a structure: ' . self::ONE_WAY;
            }
        }

        return $violations;
    }

    /**
     * @param array<Node> $statements
     */
    private static function isTranslated(Expr $expression, array $statements, int $depth): bool
    {
        return match (true) {
            $expression instanceof FuncCall => $expression->name instanceof Name && in_array($expression->name->toLowerString(), self::TRANSLATING_FUNCTIONS, true),
            $expression instanceof MethodCall, $expression instanceof NullsafeMethodCall => $expression->name instanceof Identifier && $expression->name->toLowerString() === 'translate',
            $expression instanceof StaticCall => self::isTranslatingStaticCall($expression, $statements, $depth),
            $expression instanceof Ternary => self::isTranslated($expression->if ?? $expression->cond, $statements, $depth) && self::isTranslated($expression->else, $statements, $depth),
            $expression instanceof Coalesce => self::isTranslated($expression->left, $statements, $depth) && self::isTranslated($expression->right, $statements, $depth),
            $expression instanceof Match_ => array_all($expression->arms, static fn (Node\MatchArm $arm): bool => self::isTranslated($arm->body, $statements, $depth)),
            $expression instanceof Concat => self::isTranslatedConcat($expression, $statements, $depth),
            default => false,
        };
    }

    /**
     * @param array<Node> $statements
     */
    private static function isTranslatedConcat(Concat $concat, array $statements, int $depth): bool
    {
        $translated = 0;

        foreach ([$concat->left, $concat->right] as $part) {
            if ($part instanceof String_ && preg_match('/\pL/u', $part->value) !== 1) {
                continue;
            }

            if (! self::isTranslated($part, $statements, $depth)) {
                return false;
            }

            $translated++;
        }

        return $translated > 0;
    }

    /**
     * @param array<Node> $statements
     */
    private static function isTranslatingStaticCall(StaticCall $call, array $statements, int $depth): bool
    {
        if (! $call->class instanceof Name || ! $call->name instanceof Identifier) {
            return false;
        }

        $class = ltrim($call->class->toString(), '\\');

        if ($class === Copy::class) {
            return $call->name->toLowerString() === 'translate';
        }

        if ($depth === 0) {
            return false;
        }

        $declaration = self::classNamed($class, $statements);

        if (! $declaration instanceof Class_ && ! in_array($call->class->toLowerString(), ['self', 'static'], true) && class_exists($class) && str_starts_with($class, 'App\\')) {
            $statements = self::parse((string) file_get_contents((string) new ReflectionClass($class)->getFileName()));
            $declaration = self::classNamed($class, $statements);
        }

        $returns = (new NodeFinder())->findInstanceOf($declaration?->getMethod($call->name->toString())->stmts ?? [], Return_::class);

        return $returns !== [] && array_all($returns, static fn (Return_ $return): bool => $return->expr instanceof Expr
            && self::isTranslated(self::withinClass($return->expr, $class), $statements, $depth - 1));
    }

    private static function withinClass(Expr $expression, string $class): Expr
    {
        foreach ((new NodeFinder())->findInstanceOf([$expression], StaticCall::class) as $call) {
            if ($call->class instanceof Name && in_array($call->class->toLowerString(), ['self', 'static'], true)) {
                $call->class = new Name\FullyQualified($class);
            }
        }

        return $expression;
    }

    /**
     * @param array<Node> $statements
     */
    private static function classNamed(string $class, array $statements): ?Class_
    {
        $found = (new NodeFinder())->findFirst($statements, static fn (Node $node): bool => $node instanceof Class_ && $node->namespacedName?->toString() === $class);

        return $found instanceof Class_ ? $found : null;
    }

    /**
     * @return array<Node>
     */
    private static function parse(string $source): array
    {
        return (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
    }
}
