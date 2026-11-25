<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class TestHelperMirrorGuard
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

        foreach (self::paths($this->root . '/tests/Support/*', GLOB_ONLYDIR) as $tree) {
            if (! is_dir($this->root . '/tests/' . basename($tree))) {
                continue;
            }

            foreach ($this->inspector->phpFiles($tree) as $file) {
                $violation = $this->violationOf(substr($file, strlen($this->root . '/tests/Support/')));

                if ($violation !== null) {
                    $violations[] = 'tests/Support/' . substr($file, strlen($this->root . '/tests/Support/')) . ': ' . $violation;
                }
            }
        }

        return $violations;
    }

    private function violationOf(string $path): ?string
    {
        $class = 'Tests\\Support\\' . str_replace('/', '\\', substr($path, 0, -4));

        if (preg_match('/^(.+)(Helpers|Scenario)\.php$/', $path, $parts) !== 1) {
            return null;
        }

        if (! array_key_exists($class, $this->inspector->declaredTypes($this->root . '/tests/Support/' . $path))) {
            return 'the file does not declare ' . $class . ': the class and its namespace follow the path';
        }

        if (is_file($this->root . '/tests/' . $parts[1] . 'Test.php')) {
            return null;
        }

        if ($parts[2] === 'Scenario' && $this->returnedByNeighbor($path, $class)) {
            return null;
        }

        return 'there is no tests/' . $parts[1] . 'Test.php: the helpers of one test mirror its path and carry its name ('
            . basename($parts[1]) . 'Test.php with ' . basename($parts[1]) . 'Helpers.php), a scenario sits next to the helpers that build it, and a class that several tests share is a themed class directly in tests/Support';
    }

    private function returnedByNeighbor(string $path, string $class): bool
    {
        foreach (self::paths($this->root . '/tests/Support/' . dirname($path) . '/*Helpers.php') as $neighbor) {
            $mirrored = $this->root . '/tests/' . dirname($path) . '/' . basename($neighbor, 'Helpers.php') . 'Test.php';

            if (is_file($mirrored) && in_array($class, $this->inspector->referencedNames($neighbor), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function paths(string $pattern, int $flags = 0): array
    {
        $paths = glob($pattern, $flags);

        return $paths === false ? [] : $paths;
    }
}
