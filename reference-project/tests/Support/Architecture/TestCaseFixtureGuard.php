<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;

/**
 * The analyser fails `$this->x = ...` in a test only while the test cases declare no fixture property and no test case is listed as a universal
 * object crate. The guard keeps both true: a public property on a base test case, and a test case in the analyser's list of crates.
 */
final readonly class TestCaseFixtureGuard
{
    /**
     * @param list<string> $testCaseFiles base test case files, relative to the root
     * @param string $analyserConfig the analyser config of the tests, relative to the root
     */
    public function __construct(
        private string $root,
        private array $testCaseFiles,
        private string $analyserConfig,
    ) {
    }

    /**
     * @return list<string> a message per property a test case source declares as a fixture
     */
    public function fixtureProperties(string $source): array
    {
        preg_match_all('/^\s+public\s+(?!function|static function|const)[^(=;]*(\$\w+)/m', $source, $found);

        return array_map(static fn (string $name): string => 'a public property ' . $name . ' on a base test case is a fixture shared by every test: build the state in the test, in a local variable', $found[1]);
    }

    /**
     * @return list<string> a message per base test case that the analyser config lists as a crate
     */
    public function cratesIn(string $config): array
    {
        preg_match_all('/^\s*-\s+(Tests\\\\\w*TestCase)\s*$/m', $config, $found);

        return array_map(static fn (string $class): string => $class . ' is listed as a universal object crate: the analyser then stops seeing the properties a test reads, remove the line', $found[1]);
    }

    /**
     * @return list<string> `path: message` per finding
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->testCaseFiles as $file) {
            foreach ($this->fixtureProperties($this->read($file)) as $message) {
                $violations[] = $file . ': ' . $message;
            }
        }

        foreach ($this->cratesIn($this->read($this->analyserConfig)) as $message) {
            $violations[] = $this->analyserConfig . ': ' . $message;
        }

        return $violations;
    }

    private function read(string $file): string
    {
        if (! is_file($this->root . '/' . $file)) {
            throw new InvalidArgumentException('The file ' . $file . ' does not exist under the root: a guard over a missing file would pass without reading it');
        }

        return (string) file_get_contents($this->root . '/' . $file);
    }
}
