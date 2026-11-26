<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class EventDispatchGuard
{
    private const array EVENT_FACADES = ['Event', Event::class];

    private const array DISPATCHERS = [DispatcherContract::class, Dispatcher::class];

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = substr($file, strlen($this->root) + 1) . ':' . $violation;
            }
        }

        return $violations;
    }

    /**
     * The facade, a dispatcher the class receives (a parameter or a property typed with the dispatcher contract) and the
     * container string `app('events')`. The global helper `event()` is the analyser's rule (`phpstan.neon`, disallowed
     * calls), in every form: this guard does not read it a second time.
     *
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $dispatchers = self::dispatcherNames($statements);
        $violations = [];

        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall) as $call) {
            $written = match (true) {
                $call instanceof StaticCall => self::facadeDispatch($call),
                $call instanceof MethodCall => self::dispatcherDispatch($call, $dispatchers),
                default => null,
            };

            if ($written === null) {
                continue;
            }

            $event = ($call->getArgs()[0] ?? null)?->value;

            if (! $event instanceof New_ || ! $event->class instanceof Name || ! str_starts_with(ltrim($event->class->toString(), '\\'), 'App\\')) {
                continue;
            }

            $name = $event->class->getLast();
            $violations[] = $call->getStartLine() . ': ' . $written . '(new ' . $name . '(...)) builds an event of the application to hand it to the dispatcher: write '
                . $name . '::dispatch(...) with the same arguments (the event uses Illuminate\Foundation\Events\Dispatchable)';
        }

        return $violations;
    }

    /**
     * @param array<array-key, Node> $statements
     *
     * @return array{variables: list<string>, properties: list<string>} the names that hold a dispatcher
     */
    private static function dispatcherNames(array $statements): array
    {
        $variables = [];
        $properties = [];
        $holds = static fn (?Node $type): bool => $type instanceof Name && in_array(ltrim($type->toString(), '\\'), self::DISPATCHERS, true);

        foreach ((new NodeFinder())->findInstanceOf($statements, Param::class) as $parameter) {
            if ($holds($parameter->type) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $variables[] = $parameter->var->name;

                if ($parameter->flags !== 0) {
                    $properties[] = $parameter->var->name;
                }
            }
        }

        foreach ((new NodeFinder())->findInstanceOf($statements, Property::class) as $property) {
            if ($holds($property->type)) {
                foreach ($property->props as $item) {
                    $properties[] = $item->name->toString();
                }
            }
        }

        return ['variables' => $variables, 'properties' => $properties];
    }

    private static function facadeDispatch(StaticCall $call): ?string
    {
        $onFacade = $call->class instanceof Name && in_array(ltrim($call->class->toString(), '\\'), self::EVENT_FACADES, true);

        return $onFacade && $call->name instanceof Identifier && $call->name->toLowerString() === 'dispatch' ? 'Event::dispatch' : null;
    }

    /**
     * @param array{variables: list<string>, properties: list<string>} $dispatchers
     */
    private static function dispatcherDispatch(MethodCall $call, array $dispatchers): ?string
    {
        if (! $call->name instanceof Identifier || $call->name->toLowerString() !== 'dispatch') {
            return null;
        }

        $receiver = $call->var;

        $isDispatcher = match (true) {
            $receiver instanceof Variable => is_string($receiver->name) && in_array($receiver->name, $dispatchers['variables'], true),
            $receiver instanceof PropertyFetch => $receiver->var instanceof Variable && $receiver->var->name === 'this'
                && $receiver->name instanceof Identifier && in_array($receiver->name->toString(), $dispatchers['properties'], true),
            $receiver instanceof FuncCall => $receiver->name instanceof Name && $receiver->name->toLowerString() === 'app'
                && ($receiver->getArgs()[0] ?? null)?->value instanceof String_ && $receiver->getArgs()[0]->value->value === 'events',
            default => false,
        };

        return $isDispatcher ? 'the dispatcher' : null;
    }
}
