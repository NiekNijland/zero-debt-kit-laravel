<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Illuminate\Database\Migrations\Migration;
use Override;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\TrinaryLogic;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;

final class MigrationMethods implements MethodsClassReflectionExtension
{
    #[Override]
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $classReflection->getName() === Migration::class;
    }

    #[Override]
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new class($classReflection, $methodName) implements MethodReflection
        {
            public function __construct(private ClassReflection $class, private string $name)
            {
            }

            #[Override]
            public function getDeclaringClass(): ClassReflection
            {
                return $this->class;
            }

            #[Override]
            public function isStatic(): bool
            {
                return false;
            }

            #[Override]
            public function isPrivate(): bool
            {
                return false;
            }

            #[Override]
            public function isPublic(): bool
            {
                return true;
            }

            #[Override]
            public function getDocComment(): ?string
            {
                return null;
            }

            #[Override]
            public function getName(): string
            {
                return $this->name;
            }

            #[Override]
            public function getPrototype(): ClassMemberReflection
            {
                return $this;
            }

            #[Override]
            public function getVariants(): array
            {
                return [new FunctionVariant(\PHPStan\Type\Generic\TemplateTypeMap::createEmpty(), null, [], true, in_array($this->name, ['up', 'down'], true) ? new VoidType() : new MixedType())];
            }

            #[Override]
            public function isDeprecated(): TrinaryLogic
            {
                return TrinaryLogic::createNo();
            }

            #[Override]
            public function getDeprecatedDescription(): ?string
            {
                return null;
            }

            #[Override]
            public function isFinal(): TrinaryLogic
            {
                return TrinaryLogic::createNo();
            }

            #[Override]
            public function isInternal(): TrinaryLogic
            {
                return TrinaryLogic::createNo();
            }

            #[Override]
            public function getThrowType(): ?Type
            {
                return null;
            }

            #[Override]
            public function hasSideEffects(): TrinaryLogic
            {
                return TrinaryLogic::createMaybe();
            }
        };
    }
}
