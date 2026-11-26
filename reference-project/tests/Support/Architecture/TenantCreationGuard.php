<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use ReflectionClass;
use Tests\Support\ClassNames;

/**
 * A tenant model creates with its tenant, unless its tenant is optional. The traits are read from the loaded class (a trait that uses the trait
 * counts), so the real-tree case needs the application; the rule itself is `isMissingTenant()`, which the examples run on lists.
 */
final readonly class TenantCreationGuard
{
    /**
     * @param string $folder the tenant model folder, relative to the root
     * @param string $namespace the namespace of that folder, with a trailing backslash
     * @param class-string $creates the trait that sets the tenant on create
     * @param class-string $scope the attribute that scopes a model to its tenant
     * @param class-string $nullableScope the scope of a model whose tenant is optional
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private string $namespace,
        private string $creates,
        private string $scope,
        private string $nullableScope,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            $class = new ReflectionClass(ClassNames::of($this->namespace . str_replace('/', '\\', substr($file, strlen($this->root . '/' . $this->folder . '/'), -4))));
            $arguments = ($class->getAttributes($this->scope)[0] ?? null)?->getArguments();

            if ($this->isMissingTenant(array_values(class_uses_recursive($class->getName())), $arguments)) {
                $violations[] = $class->getName() . ' is a tenant model that creates without its tenant: use ' . basename(str_replace('\\', '/', $this->creates)) . ', or scope it with ' . basename(str_replace('\\', '/', $this->nullableScope)) . ' when the tenant is optional';
            }
        }

        return $violations;
    }

    /**
     * @param list<string> $traits every trait of the class, the traits of its traits included
     * @param array<int|string, mixed>|null $scopeArguments the arguments of the scope attribute, null when the class has none
     */
    public function isMissingTenant(array $traits, ?array $scopeArguments): bool
    {
        return ! in_array($this->creates, $traits, true) && $scopeArguments !== [$this->nullableScope];
    }
}
