<?php

declare(strict_types=1);

use Tests\Support\Unit\NoInlineVarCastArchitectureHelpers;

dataset('inline var casts', [
    'assignment in a method' => ["<?php\nfinal class C { public function f(): void { /** @var int \$x */ \$x = g(); } }\n"],
    'assignment in a function' => ["<?php\nfunction f(): void {\n    /** @var list<int> \$ids */\n    \$ids = g();\n}\n"],
    'return in a method' => ["<?php\nfinal class C { public function f(): array { /** @var list<string> */ return g(); } }\n"],
    'foreach in a closure' => ["<?php\n\$f = function (): void { /** @var Foo \$item */ foreach (g() as \$item) {} };\n"],
    'statement in an arrow function body' => ["<?php\nfinal class C { public function f(): void { \$g = fn () => h(/** @var int \$x */ \$x = 1); } }\n"],
    'phpstan-var' => ["<?php\nfinal class C { public function f(): void { /** @phpstan-var int \$x */ \$x = g(); } }\n"],
    'multi-line docblock' => ["<?php\nfinal class C { public function f(): void {\n    /**\n     * @var int \$x\n     */\n    \$x = g();\n} }\n"],
    'top-level script statement' => ["<?php\n/** @var array<string, int> \$map */\n\$map = g();\n"],
]);

it('detects a @var docblock on a statement or an expression inside a body', function (string $source): void {
    expect(NoInlineVarCastArchitectureHelpers::inlineVarLines($source))->toHaveCount(1);
})->with('inline var casts');

it('accepts @var on properties, constants and promoted parameters', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        const TOP = [];
        final class C
        {
            /** @var list<int> */
            private array $ids = [];

            /** @var array<string, int> */
            private const array MAP = [];

            public function __construct(
                /** @var list<string> */
                public array $names = [],
            ) {
            }

            public function f(): object
            {
                return new class() {
                    /** @var list<int> */
                    public array $values = [];
                };
            }
        }
        return [
            /** @var list<string> */
            'config' => [],
        ];
        PHP;

    expect(NoInlineVarCastArchitectureHelpers::inlineVarLines($source))->toBe([]);
});

it('lets no file in app, bootstrap, config, database or routes narrow a type with an inline @var', function (): void {
    expect(NoInlineVarCastArchitectureHelpers::inlineVarCounts())->toBe([]);
});
