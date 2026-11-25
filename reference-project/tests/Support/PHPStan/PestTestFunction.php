<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Override;
use Pest\PendingCalls\TestCall;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class PestTestFunction implements DynamicFunctionReturnTypeExtension
{
    #[Override]
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return in_array($functionReflection->getName(), ['test', 'arch'], true);
    }

    #[Override]
    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        if ($functionReflection->getName() === 'arch') {
            return new ObjectType(PestArchCall::class);
        }

        if ($functionCall->getArgs() === []) {
            return PestClosureThis::testCase($scope);
        }

        return new ObjectType(TestCall::class);
    }
}
