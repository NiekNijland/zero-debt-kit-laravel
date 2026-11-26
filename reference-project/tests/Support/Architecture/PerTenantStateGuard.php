<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

final readonly class PerTenantStateGuard
{
    private const array CONFIG_FACADES = ['Config', 'Illuminate\Support\Facades\Config'];

    private const array CONFIG_WRITES = ['set', 'prepend', 'push'];

    private const array CURRENT_TENANT_READS = ['current', 'checkcurrent', 'getcurrentid'];

    private const string TASK = 'a SwitchTenantTask in app/Modules/Tenants/Support, listed in switch_tenant_tasks of config/multitenancy.php';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
        /** @var string the config file that lists the tenant switch tasks, relative to the root */
        private string $registry,
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
     * @return list<string>
     */
    public function unlistedTasks(): array
    {
        $listed = $this->inspector->referencedNames($this->root . '/' . $this->registry);
        $unlisted = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inspector->declaredTypes($file) as $class => $related) {
                if (in_array(SwitchTenantTask::class, $related, true) && is_subclass_of($class, SwitchTenantTask::class) && ! in_array($class, $listed, true)) {
                    $unlisted[] = $class . ' is not in switch_tenant_tasks of ' . $this->registry . ', so it never runs: add it there';
                }
            }
        }

        return $unlisted;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($source) ?? []);
        $finder = new NodeFinder();
        $owners = [];

        foreach ($finder->findInstanceOf($statements, ClassLike::class) as $class) {
            foreach ($class->getMethods() as $method) {
                foreach ($this->configWrites([$method]) as $write) {
                    $owners[spl_object_id($write)] = [$class, $method];
                }
            }
        }

        $violations = [];

        foreach ($this->configWrites($statements) as $write) {
            [$class, $method] = $owners[spl_object_id($write)] ?? [null, null];
            $reason = $this->reason($class, $method);

            if ($reason !== null) {
                $violations[] = $write->getStartLine() . ': ' . $reason;
            }
        }

        return $violations;
    }

    private function reason(?ClassLike $class, ?ClassMethod $method): ?string
    {
        if ($class instanceof Class_ && in_array(SwitchTenantTask::class, array_map(static fn (Name $name): string => ltrim($name->toString(), '\\'), $class->implements), true)) {
            return null;
        }

        $provider = $class instanceof Class_ && $class->extends instanceof Name && str_ends_with($class->extends->toString(), 'ServiceProvider');

        if (! $provider || ! $method instanceof ClassMethod) {
            return 'config is written at runtime outside a switch task and outside a service provider: state per tenant is ' . self::TASK . ', state per process is a key in config/*.php';
        }

        if ($this->readsCurrentTenant($method)) {
            return 'a provider reads the current tenant to set config: make it ' . self::TASK;
        }

        return null;
    }

    /**
     * @param array<Node> $nodes
     * @return list<Node>
     */
    private function configWrites(array $nodes): array
    {
        return array_values((new NodeFinder())->find($nodes, static function (Node $node): bool {
            if ($node instanceof StaticCall) {
                return $node->class instanceof Name
                    && in_array(ltrim($node->class->toString(), '\\'), self::CONFIG_FACADES, true)
                    && $node->name instanceof Identifier
                    && in_array($node->name->toLowerString(), self::CONFIG_WRITES, true);
            }

            if ($node instanceof FuncCall) {
                return self::isConfigHelper($node) && ($node->getArgs()[0]->value ?? null) instanceof Array_;
            }

            return $node instanceof MethodCall
                && $node->name instanceof Identifier
                && in_array($node->name->toLowerString(), self::CONFIG_WRITES, true)
                && $node->var instanceof FuncCall
                && self::isConfigHelper($node->var);
        }));
    }

    private static function isConfigHelper(FuncCall $call): bool
    {
        return $call->name instanceof Name && mb_strtolower($call->name->getLast()) === 'config';
    }

    private function readsCurrentTenant(ClassMethod $method): bool
    {
        return (new NodeFinder())->findFirst([$method], static function (Node $node): bool {
            if ($node instanceof String_) {
                return $node->value === 'currentTenant';
            }

            return $node instanceof StaticCall
                && $node->class instanceof Name
                && $node->class->getLast() === 'Tenant'
                && $node->name instanceof Identifier
                && in_array($node->name->toLowerString(), self::CURRENT_TENANT_READS, true);
        }) instanceof Node;
    }
}
