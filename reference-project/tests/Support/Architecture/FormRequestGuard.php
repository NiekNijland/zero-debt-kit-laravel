<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final readonly class FormRequestGuard
{
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

        foreach ($this->inspector->phpFiles($this->root . '/app/Http/Requests') as $file) {
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
        $statements = (new ParserFactory())->createForHostVersion()->parse($source) ?? [];
        $finder = new NodeFinder();
        $violations = [];

        foreach ($finder->findInstanceOf($statements, Class_::class) as $class) {
            $authorize = $class->getMethod('authorize');

            if ($authorize instanceof ClassMethod && self::onlyReturnsTrue($authorize) && ! self::overrides($authorize)) {
                $violations[] = $authorize->getStartLine() . ': authorize() that only returns true: remove the method, a form request without one lets the request through and the route carries the permission or policy; keep it with #[Override] only to switch off the check of a parent request';
            }

            foreach ($finder->findInstanceOf($class->getMethod('rules')->stmts ?? [], Node\ArrayItem::class) as $item) {
                if ($item->key instanceof String_ && $item->value instanceof String_) {
                    $violations[] = $item->getStartLine() . ': the rules of a field as one string: write them as an array, [' . implode(', ', array_map(static fn (string $rule): string => "'" . $rule . "'", explode('|', $item->value->value))) . ']';
                }
            }
        }

        return $violations;
    }

    private static function onlyReturnsTrue(ClassMethod $method): bool
    {
        $statements = $method->stmts ?? [];
        $only = $statements[0] ?? null;

        return count($statements) === 1 && $only instanceof Return_ && $only->expr instanceof ConstFetch && $only->expr->name->toLowerString() === 'true';
    }

    private static function overrides(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->getLast() === 'Override') {
                    return true;
                }
            }
        }

        return false;
    }
}
