<?php

declare(strict_types=1);

use Tests\Support\Unit\NoReflectionInTestsArchitectureHelpers;

dataset('reflection that reaches into a class', [
    'reflection method' => ["<?php\nnew ReflectionMethod(\$job, 'handle')->invoke(\$job);\n"],
    'imported reflection property' => ["<?php\nuse ReflectionProperty;\n\$p = new ReflectionProperty(Foo::class, 'state');\n"],
    'fully qualified reflection object' => ["<?php\n\$r = new \\ReflectionObject(\$job);\n"],
    'private constant' => ["<?php\nnew ReflectionClassConstant(Foo::class, 'LIMIT');\n"],
    'reflection class kept in a variable' => ["<?php\nit('x', function (): void {\n    \$r = new ReflectionClass(\$job);\n    \$r->getShortName();\n    \$r->getProperty('state');\n});\n"],
    'a method of a reflection class' => ["<?php\nnew ReflectionClass(\$job)->getMethod('run')->invoke(\$job);\n"],
    'default properties' => ["<?php\nnew ReflectionClass(Foo::class)->getDefaultProperties();\n"],
    'every method' => ["<?php\nnew ReflectionClass(Foo::class)->getMethods();\n"],
    'every property' => ["<?php\nnew ReflectionClass(Foo::class)->getProperties();\n"],
    'set accessible' => ["<?php\n\$method->setAccessible(true);\n"],
    'closure bind' => ["<?php\n\\Closure::bind(fn () => \$this->secret, \$job, Job::class)();\n"],
    'bind to' => ["<?php\n(fn () => \$this->secret)->bindTo(\$job, \$job)();\n"],
]);

it('detects reflection that reaches a private member', function (string $source): void {
    expect(NoReflectionInTestsArchitectureHelpers::violations($source))->toHaveCount(1);
})->with('reflection that reaches into a class');

it('accepts reading the public structure of a class', function (): void {
    $source = <<<'PHP'
        <?php
        new ReflectionClass(Job::class)->getAttributes(Middleware::class);
        new ReflectionClass(Job::class)->implementsInterface(ShouldQueue::class);
        new \ReflectionClass(Job::class)->getMethods(\ReflectionMethod::IS_PUBLIC);
        new ReflectionClass(Job::class)->getProperties(ReflectionProperty::IS_PUBLIC);
        new ReflectionFunction($closure)->getParameters();
        $request->getMethod();
        it('reads a class', function (): void {
            $class = new ReflectionClass(Job::class);
            expect($class->getShortName())->toBe('Job')->and($class->isFinal())->toBeTrue();
        });
        PHP;

    expect(NoReflectionInTestsArchitectureHelpers::violations($source))->toBe([]);
});

it('lets no test reach a private member through reflection', function (): void {
    expect(NoReflectionInTestsArchitectureHelpers::violationsInTests())->toBe([]);
});
