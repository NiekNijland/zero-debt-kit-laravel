<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Override;
use Pest\Expectation;
use Pest\Expectations\EachExpectation;
use Pest\Expectations\HigherOrderExpectation;
use Pest\Expectations\OppositeExpectation;
use Pest\Mixins\Expectation as ExpectationMethods;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

final class PestHigherOrderProperty implements ExpressionTypeResolverExtension
{
    private const array ARCH = ['classes', 'traits', 'interfaces', 'enums'];

    #[Override]
    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if (! $expr instanceof PropertyFetch || ! $expr->name instanceof Identifier) {
            return null;
        }

        $name = $expr->name->name;
        $subject = $scope->getType($expr->var);
        $classes = array_values(array_unique($subject->getObjectClassNames()));

        if ($classes === [HigherOrderExpectation::class]) {
            return $this->afterHigherOrder($subject, $name);
        }

        if (count($classes) !== 1 || ! in_array($classes[0], [Expectation::class, ExpectationMethods::class], true)) {
            return null;
        }

        $value = $subject->getTemplateType($classes[0], 'TValue');

        return match (true) {
            $name === 'value' => $value,
            $name === 'not' => new GenericObjectType(OppositeExpectation::class, [$value]),
            $name === 'each' => new GenericObjectType(EachExpectation::class, [$value]),
            in_array($name, self::ARCH, true) => null,
            $this->isAssertion($name) => new GenericObjectType(Expectation::class, [$value]),
            default => new GenericObjectType(HigherOrderExpectation::class, [$value, new MixedType()]),
        };
    }

    private function afterHigherOrder(Type $subject, string $name): Type
    {
        $original = $subject->getTemplateType(HigherOrderExpectation::class, 'TOriginalValue');

        if ($name === 'not') {
            return $subject;
        }

        return $this->isAssertion($name)
            ? new GenericObjectType(Expectation::class, [$original])
            : new GenericObjectType(HigherOrderExpectation::class, [$original, new MixedType()]);
    }

    private function isAssertion(string $name): bool
    {
        return method_exists(Expectation::class, $name) || method_exists(ExpectationMethods::class, $name);
    }
}
