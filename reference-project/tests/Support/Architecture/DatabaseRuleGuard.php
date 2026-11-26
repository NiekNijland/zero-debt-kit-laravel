<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use ReflectionClass;

final class DatabaseRuleGuard
{
    private const string FACADE = 'Illuminate\Validation\Rule';

    private const array RULES = ['exists', 'unique'];

    private const array RULE_CLASSES = ['Illuminate\Validation\Rules\Exists' => 'exists', 'Illuminate\Validation\Rules\Unique' => 'unique'];

    private const array CONSTRAINTS = ['where', 'wherein', 'using'];

    private const string STRING_RULE = '/(?:^|\|)(exists|unique):/';

    /**
     * @param string $source the folder the rules are read in, relative to the root
     * @param array{scope: class-string, nullableScope: class-string, column: string}|null $tenant the facts of a multi-tenant project: the global scope that binds a
     *     model to a tenant, the scope that also lets rows of no tenant through, and the column both filter on. Without it (a single-tenant project) only the
     *     model-class half of the rule runs.
     */
    public function __construct(
        private readonly PhpFileInspector $inspector,
        private readonly string $root,
        private readonly string $source,
        private readonly ?array $tenant = null,
    ) {
    }

    /**
     * @return list<string> `file:line: the rule: what to write instead` for every existence or uniqueness rule in app that skips its model or its tenant
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
     * @return list<string> `line: the rule: what to write instead`
     */
    public function inSource(string $source): array
    {
        $statements = PhpSource::resolved($source);
        $finder = new NodeFinder();
        $found = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedStringPart) as $string) {
            if (($string instanceof String_ || $string instanceof InterpolatedStringPart) && preg_match(self::STRING_RULE, $string->value, $match) === 1) {
                $found[] = [$string->getStartLine(), "the string rule '{$match[1]}:': " . $this->replacement($match[1])];
            }
        }

        foreach ($finder->findInstanceOf($statements, New_::class) as $new) {
            $rule = $new->class instanceof Name ? (self::RULE_CLASSES[ltrim($new->class->toString(), '\\')] ?? null) : null;

            if ($rule !== null) {
                $found[] = [$new->getStartLine(), 'new ' . ucfirst($rule) . '(): ' . $this->replacement($rule)];
            }
        }

        $chains = $this->chains($statements);

        foreach ($finder->findInstanceOf($statements, StaticCall::class) as $call) {
            $reason = $this->reasonToFail($call, $chains[spl_object_id($call)] ?? []);

            if ($reason !== null) {
                $found[] = [$call->getStartLine(), $reason];
            }
        }

        sort($found);

        return array_map(static fn (array $violation): string => $violation[0] . ': ' . $violation[1], $found);
    }

    /**
     * @param list<MethodCall|NullsafeMethodCall> $chain
     */
    private function reasonToFail(StaticCall $call, array $chain): ?string
    {
        $rule = $call->name instanceof Identifier ? $call->name->toLowerString() : '';

        if (! $call->class instanceof Name || ltrim($call->class->toString(), '\\') !== self::FACADE || ! in_array($rule, self::RULES, true)) {
            return null;
        }

        $model = $this->model($call);

        if ($model === null) {
            return "Rule::{$rule}() without a model class as its first argument: " . $this->replacement($rule);
        }

        if ($this->tenant === null) {
            return null;
        }

        $scope = $this->tenantScope($model);
        $nullable = $scope === $this->tenant['nullableScope'];

        if ($scope === null || $this->constrainsTenant($chain, $nullable)) {
            return null;
        }

        $short = new ReflectionClass($model)->getShortName();
        $column = $this->tenant['column'];
        $global = $nullable ? ", or ->whereNull('{$column}') for the rows of no tenant" : '';

        return "Rule::{$rule}({$short}::class) without {$column} in its chain: {$short} belongs to a tenant and the rule skips the scope of the model, so add ->where('{$column}', <the id of the current tenant>){$global}";
    }

    private function replacement(string $rule): string
    {
        $tenant = $this->tenant === null ? '' : ", with ->where('{$this->tenant['column']}', <the id of the current tenant>) when the model belongs to a tenant";

        return "write Rule::{$rule}(Model::class, 'column'){$tenant}";
    }

    /**
     * @return class-string<Model>|null
     */
    private function model(StaticCall $call): ?string
    {
        $first = $call->args[0] ?? null;
        $value = $first instanceof Node\Arg && ! $first->unpack ? $first->value : null;

        if (! $value instanceof ClassConstFetch || ! $value->class instanceof Name || ! $value->name instanceof Identifier || $value->name->toLowerString() !== 'class') {
            return null;
        }

        $class = ltrim($value->class->toString(), '\\');

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * @param class-string<Model> $model
     * @return class-string|null the scope that binds the model to a tenant, read from its ScopedBy attributes the way Eloquent reads them
     */
    private function tenantScope(string $model): ?string
    {
        $scopes = [];

        $parents = class_parents($model);

        foreach ([$model, ...array_values($parents === false ? [] : $parents), ...array_values(class_uses_recursive($model))] as $class) {
            if (! class_exists($class) && ! trait_exists($class)) {
                continue;
            }

            foreach (new ReflectionClass($class)->getAttributes(ScopedBy::class) as $attribute) {
                foreach ($attribute->getArguments() as $argument) {
                    $scopes = [...$scopes, ...(is_array($argument) ? array_values($argument) : [$argument])];
                }
            }
        }

        foreach ([$this->tenant['scope'] ?? null, $this->tenant['nullableScope'] ?? null] as $tenantScope) {
            if ($tenantScope !== null && in_array($tenantScope, $scopes, true)) {
                return $tenantScope;
            }
        }

        return null;
    }

    /**
     * @param list<MethodCall|NullsafeMethodCall> $chain
     */
    private function constrainsTenant(array $chain, bool $nullable): bool
    {
        $constraints = $nullable ? [...self::CONSTRAINTS, 'wherenull'] : self::CONSTRAINTS;
        $tenantColumn = $this->tenant['column'] ?? '';

        foreach ($chain as $call) {
            $first = $call->args[0] ?? null;

            if (! $call->name instanceof Identifier || ! in_array($call->name->toLowerString(), $constraints, true) || ! $first instanceof Node\Arg) {
                continue;
            }

            $value = $first->value;
            $columns = $value instanceof Closure || $value instanceof ArrowFunction ? (new NodeFinder())->findInstanceOf([$value], String_::class) : [$value];

            foreach ($columns as $candidate) {
                if ($candidate instanceof String_ && ($candidate->value === $tenantColumn || str_ends_with($candidate->value, '.' . $tenantColumn))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<Node> $statements
     * @return array<int, list<MethodCall|NullsafeMethodCall>> the method calls chained onto each static call, by the object id of the static call
     */
    private function chains(array $statements): array
    {
        $chains = [];

        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            $root = $call;

            while ($root instanceof MethodCall || $root instanceof NullsafeMethodCall) {
                $root = $root->var;
            }

            if ($root instanceof StaticCall && ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)) {
                $chains[spl_object_id($root)][] = $call;
            }
        }

        return $chains;
    }
}
