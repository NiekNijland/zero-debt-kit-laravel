<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\RecursiveFiles;

final class TestClassGuard
{
    private const string DECLARATION = '/^\h*(#\[[^\n]*\]\h*)*(\w+\h+)*(class|interface|trait|enum)\h+\w/mi';

    /**
     * @param list<string> $suites the suite folders, relative to the root; a suite that does not exist is skipped
     */
    public function __construct(
        private readonly PhpFileInspector $inspector,
        private readonly string $root,
        private readonly array $suites,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->suites as $suite) {
            if (! is_dir($this->root . '/' . $suite)) {
                continue;
            }

            foreach (RecursiveFiles::in($this->root . '/' . $suite) as $file) {
                if (! str_ends_with($file->getFilename(), 'Test.php') || preg_match(self::DECLARATION, (string) file_get_contents($file->getPathname())) !== 1) {
                    continue;
                }

                $foreign = array_values(array_filter(
                    array_keys($this->inspector->declaredTypes($file->getPathname())),
                    static fn (string $type): bool => class_basename($type) !== $file->getBasename('.php'),
                ));

                if ($foreign !== []) {
                    $violations[substr($file->getPathname(), strlen($this->root) + 1)] = $foreign;
                }
            }
        }

        ksort($violations);

        return $violations;
    }
}
