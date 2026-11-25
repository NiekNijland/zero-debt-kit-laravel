<?php

declare(strict_types=1);

// PHPStan fails on `$this->x = ...` in a test only while the test cases declare no fixture property
// and are no universal object crate; this keeps both true.
it('keeps fixture properties off the test cases', function (string $file): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);

    expect(preg_match_all('/^\s+public\s+(?!function|static function|const)[^(=;]*\$\w+/m', $source))->toBe(0);
})->with([
    'tests/TestCase.php',
    'tests/FeatureTestCase.php',
    'tests/PureTestCase.php',
]);

it('lets PHPStan see every property a test reads', function (): void {
    $config = (string) file_get_contents(dirname(__DIR__, 2) . '/phpstan-tests.neon');

    expect($config)
        ->not->toContain('- Tests\FeatureTestCase')
        ->not->toContain('- Tests\PureTestCase')
        ->not->toContain('- Tests\TestCase');
});
