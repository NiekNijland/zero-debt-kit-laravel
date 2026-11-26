<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

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
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
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
        $statements = PhpSource::resolved($source);
        $dispatchers = TypedReceivers::in($statements, self::DISPATCHERS, ['events']);
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

    private static function facadeDispatch(StaticCall $call): ?string
    {
        $onFacade = $call->class instanceof Name && in_array(ltrim($call->class->toString(), '\\'), self::EVENT_FACADES, true);

        return $onFacade && $call->name instanceof Identifier && $call->name->toLowerString() === 'dispatch' ? 'Event::dispatch' : null;
    }

    private static function dispatcherDispatch(MethodCall $call, TypedReceivers $dispatchers): ?string
    {
        $dispatches = $call->name instanceof Identifier && $call->name->toLowerString() === 'dispatch';

        return $dispatches && $dispatchers->holds($call->var) ? 'the dispatcher' : null;
    }
}
