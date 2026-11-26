<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestAssertionGuard;

uses()->group('arch');

dataset('test files that assert nothing', [
    'a call without a check' => ["<?php\nit('runs', function (): void {\n    \$this->artisan('products:import');\n});\n"],
    'a request without a check' => ["<?php\nit('loads', function (): void {\n    \$this->get(route('dashboard'));\n});\n"],
    'the word in a string' => ["<?php\nit('runs', function (): void {\n    \$text = 'expect(1) and assertTrue()';\n    f(\$text);\n});\n"],
    'a double that only stubs' => ["<?php\nit('runs', function (): void {\n    \$this->mock(Service::class)->shouldReceive('find')->andReturn(null);\n    app(Action::class)->handle();\n});\n"],
    'a file without an opening tag, which PHP prints instead of running' => ["\nit('adds', function (): void {\n    expect(1 + 1)->toBe(2);\n});\n"],
    'an expectation without a matcher' => ["<?php\nit('runs', function (): void {\n    f();\n    expect(true);\n});\n"],
    'a count on something that is not a double' => ["<?php\nit('runs', function (): void {\n    \$schedule->command('a')->daily()->once();\n    f();\n});\n"],
    'an empty file' => ["<?php\n"],
]);

it('detects a test file without an assertion', function (string $source): void {
    expect(new TestAssertionGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['tests/Unit', 'tests/Feature', 'tests/Integration'])->asserts($source))->toBeFalse();
})->with('test files that assert nothing');

it('accepts every way a test asserts', function (string $source): void {
    expect(new TestAssertionGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['tests/Unit', 'tests/Feature', 'tests/Integration'])->asserts($source))->toBeTrue();
})->with([
    'expect' => ["<?php\nit('adds', function (): void {\n    expect(1 + 1)->toBe(2);\n});\n"],
    'a response assertion' => ["<?php\nit('loads', function (): void {\n    \$this->get('/')->assertOk();\n});\n"],
    'a facade assertion' => ["<?php\nit('queues', function (): void {\n    Queue::assertPushed(Job::class);\n});\n"],
    'throws' => ["<?php\nit('refuses', function (): void {\n    f();\n})->throws(RefusedException::class);\n"],
    'throwsNoExceptions' => ["<?php\nit('runs', function (): void {\n    f();\n})->throwsNoExceptions();\n"],
    'a PHPUnit function' => ["<?php\nuse function PHPUnit\\Framework\\assertInstanceOf;\nit('binds', function (): void {\n    assertInstanceOf(Clock::class, app('clock'));\n});\n"],
    'a Mockery expectation with a count' => ["<?php\nit('saves once', function (): void {\n    \$this->mock(Service::class)->shouldReceive('save')->once();\n    app(Action::class)->handle();\n});\n"],
    'a Mockery spy' => ["<?php\nit('never saves', function (): void {\n    \$spy = \$this->spy(Service::class);\n    app(Action::class)->handle();\n    \$spy->shouldNotHaveReceived('save');\n});\n"],
    'an arch expectation' => ["<?php\narch('models')->expect('App\\Models')->toBeClasses();\n"],
]);

it('lets no test file under tests/Unit, tests/Feature or tests/Integration assert nothing', function (): void {
    expect(new TestAssertionGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['tests/Unit', 'tests/Feature', 'tests/Integration'])->violations())->toBe([]);
});
