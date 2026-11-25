<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\Schedule;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Tests\Support\Globs;

final readonly class ScheduledTaskGuard
{
    private const array SCHEDULE_FACADES = ['Schedule', Schedule::class];

    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (Globs::of($this->root . '/routes/*.php') as $file) {
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
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $violations = [];

        foreach ((new NodeFinder())->findInstanceOf($statements, Expression::class) as $statement) {
            $minutes = self::overlapMinutes($statement->expr);

            if ($minutes === false) {
                continue;
            }

            if ($minutes === null) {
                $violations[] = $statement->getStartLine() . ': a scheduled task without ->withoutOverlapping(<minutes>): add it with the minutes after which a run that never finished frees the next one, so a slow run is never joined by a second';
            }
        }

        return $violations;
    }

    private static function overlapMinutes(Expr $chain): int|false|null
    {
        $minutes = null;

        while ($chain instanceof MethodCall) {
            if ($chain->name instanceof Identifier && $chain->name->toLowerString() === 'withoutoverlapping') {
                $argument = ($chain->getArgs()[0] ?? null)?->value;
                $minutes = $argument instanceof Int_ ? $argument->value : $minutes;
            }

            $chain = $chain->var;
        }

        $scheduled = $chain instanceof StaticCall && $chain->class instanceof Name && in_array(ltrim($chain->class->toString(), '\\'), self::SCHEDULE_FACADES, true);

        return $scheduled ? $minutes : false;
    }
}
