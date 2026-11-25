<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Override;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\FunctionParameterClosureThisExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Tests\FeatureTestCase;
use Tests\PureTestCase;

final class PestClosureThis implements FunctionParameterClosureThisExtension
{
    public static function testCase(Scope $scope): ObjectType
    {
        $unit = str_contains($scope->getFile(), '/tests/Unit/') || str_contains($scope->getFile(), '/tests/Support/Unit/');

        return new ObjectType($unit ? PureTestCase::class : FeatureTestCase::class);
    }

    #[Override]
    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        return in_array($functionReflection->getName(), ['beforeEach', 'afterEach', 'it', 'test'], true) && $parameter->getName() === 'closure';
    }

    #[Override]
    public function getClosureThisTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, ParameterReflection $parameter, Scope $scope): Type
    {
        return self::testCase($scope);
    }
}
