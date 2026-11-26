<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Attribute;
use PhpParser\NodeFinder;

/**
 * A TypeScript type is named after its PHP class: the attribute carries no argument that renames it.
 */
final readonly class TypeScriptAttributeGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private string $attribute,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $attributed = 0;

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            $source = (string) file_get_contents($file);
            $attributed += $this->attributeCount($source);

            foreach ($this->inSource($source) as $message) {
                $violations[] = substr($file, strlen($this->root) + 1) . ': ' . $message;
            }
        }

        if ($attributed === 0) {
            $violations[] = $this->folder . ': no #[' . $this->attribute . '] was read, so the guard has read nothing: is the folder renamed?';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $violations = [];

        foreach ($this->attributes($source) as $attribute) {
            if ($attribute->args !== []) {
                $violations[] = 'line ' . $attribute->getStartLine() . ': #[' . $this->attribute . '] renames the type: drop the argument, the TypeScript type is named after the PHP class';
            }
        }

        return $violations;
    }

    public function attributeCount(string $source): int
    {
        return count($this->attributes($source));
    }

    /**
     * @return list<Attribute>
     */
    private function attributes(string $source): array
    {
        $statements = PhpSource::parse($source);

        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($statements, Attribute::class),
            fn (Attribute $attribute): bool => $attribute->name->getLast() === $this->attribute,
        ));
    }
}
