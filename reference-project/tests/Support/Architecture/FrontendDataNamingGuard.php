<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tests\Support\ClassNames;

final class FrontendDataNamingGuard
{
    /**
     * @return list<string>
     */
    public static function violations(PhpFileInspector $inspector, string $root): array
    {
        $violations = [];

        foreach ($inspector->phpFiles($root . '/app') as $file) {
            if (! str_contains((string) file_get_contents($file), '#[TypeScript')) {
                continue;
            }

            foreach (array_keys($inspector->declaredTypes($file)) as $type) {
                foreach (self::misnamedProperties(ClassNames::of($type)) as $violation) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    public static function misnamedProperties(string $class): array
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->isSubclassOf(Data::class) || $reflection->getAttributes(TypeScript::class) === []) {
            return [];
        }

        $violations = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (! $property->isStatic() && preg_match('/^[a-z][a-zA-Z0-9]*$/', $property->getName()) !== 1) {
                $violations[] = ($reflection->isAnonymous() ? 'a Data class' : $class) . '::$' . $property->getName()
                    . " is not camelCase: the frontend reads Data under camelCase keys, so rename the property; a key that another system needs in snake_case keeps it with #[MapOutputName('snake_case')] on the camelCase property";
            }
        }

        return $violations;
    }
}
