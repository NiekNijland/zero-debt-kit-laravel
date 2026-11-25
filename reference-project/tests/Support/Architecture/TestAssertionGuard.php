<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class TestAssertionGuard
{
    private const array TIERS = ['Unit', 'Feature', 'Integration'];

    private const array ASSERTING_FUNCTIONS = ['expect', 'arch'];

    private const array ASSERTING_METHODS = ['tothrow', 'throws', 'throwsif', 'throwsunless', 'throwsnoexceptions'];

    private const array MOCKERY_EXPECTATIONS = ['shouldhavereceived', 'shouldnothavereceived', 'shouldnotreceive', 'expects', 'once', 'twice', 'never', 'times', 'atleast', 'atmost'];

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

        foreach (self::TIERS as $tier) {
            foreach ($this->inspector->phpFiles($this->root . '/tests/' . $tier) as $file) {
                if (str_ends_with($file, 'Test.php') && ! $this->asserts((string) file_get_contents($file))) {
                    $violations[] = substr($file, strlen($this->root) + 1) . ' asserts nothing: add an expect() on the outcome, a Mockery expectation with a count, or ->throwsNoExceptions() when there is truly nothing else to check';
                }
            }
        }

        return $violations;
    }

    public function asserts(string $source): bool
    {
        foreach ($this->inspector->calls($source) as $call) {
            $named = $call['kind'] === 'function'
                ? in_array($call['name'], self::ASSERTING_FUNCTIONS, true)
                : in_array($call['name'], [...self::ASSERTING_METHODS, ...self::MOCKERY_EXPECTATIONS], true);

            if ($named || str_starts_with($call['name'], 'assert')) {
                return true;
            }
        }

        return false;
    }
}
