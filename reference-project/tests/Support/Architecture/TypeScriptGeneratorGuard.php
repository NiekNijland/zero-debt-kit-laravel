<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The dev-only TypeScript generator stays out of runtime code: only the files that configure it name its classes (the attribute is the one name
 * that runtime classes may use).
 */
final readonly class TypeScriptGeneratorGuard
{
    /**
     * @param string $folder the application folder, relative to the root
     * @param list<string> $allowed paths relative to the root of the files that configure the generator
     * @param list<string> $allowedPrefixes path prefixes relative to the root of folders that are the generator's own code
     * @param list<string> $packageNamespaces the namespaces of the generator's packages, with a trailing backslash
     * @param string $attributes the namespace of the attribute that runtime classes may use, with a trailing backslash
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private array $allowed,
        private array $allowedPrefixes,
        private array $packageNamespaces,
        private string $attributes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            $path = substr($file, strlen($this->root) + 1);

            if (in_array($path, $this->allowed, true) || array_any($this->allowedPrefixes, static fn (string $prefix): bool => str_starts_with($path, $prefix))) {
                continue;
            }

            foreach ($this->inFile($file) as $message) {
                $violations[] = $path . ': ' . $message;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        return TemporarySource::with($source, fn (string $file): array => $this->inFile($file));
    }

    /**
     * @return list<string>
     */
    private function inFile(string $file): array
    {
        $uses = array_filter(
            $this->inspector->referencedNames($file),
            fn (string $name): bool => array_any($this->packageNamespaces, static fn (string $namespace): bool => str_starts_with($name, $namespace)) && ! str_starts_with($name, $this->attributes),
        );

        return $uses === [] ? [] : ['the dev-only TypeScript generator is used in runtime code: only its provider and its own folder name it, and the attribute is the one name a class may use'];
    }
}
