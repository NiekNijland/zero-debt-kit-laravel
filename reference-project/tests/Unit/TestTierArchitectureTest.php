<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestClassGuard;
use Tests\Support\Architecture\TestHelperFunctionGuard;
use Tests\Support\Unit\TestTierArchitectureHelpers;

uses()->group('arch');

dataset('booting unit test sources', [
    'plain import' => ["<?php\nuse Tests\\FeatureTestCase;\nuses(FeatureTestCase::class);\n"],
    'aliased import' => ["<?php\nuse Tests\\FeatureTestCase as Base;\nuses(Base::class);\n"],
    'group import' => ["<?php\nuse Illuminate\\Foundation\\Testing\\{RefreshDatabase, WithFaker};\nuses(RefreshDatabase::class);\n"],
    'inline fully qualified name' => ["<?php\nuses(\\Tests\\TestCase::class);\n"],
    'class extending a booting base' => ["<?php\nnamespace Tests\\Unit;\nfinal class FooTest extends \\Tests\\TestCase {}\n"],
    'pest extend call' => ["<?php\npest()->extend(\\Tests\\FeatureTestCase::class);\n"],
]);

it('detects a booting base in every way a unit test can name it', function (string $source): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['tests/Unit/FooTest.php' => $source, 'tests/Pest.php' => "<?php\n"]);

    try {
        expect(array_keys(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations()))->toBe(['tests/Unit/FooTest.php']);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('booting unit test sources');

it('detects a booting base reached through a support trait', function (): void {
    $root = TestTierArchitectureHelpers::tierSandbox([
        'tests/Concerns/Indirect.php' => "<?php\nnamespace Tests\\Concerns;\nuse Illuminate\\Foundation\\Testing\\LazilyRefreshDatabase;\ntrait Indirect { use LazilyRefreshDatabase; }\n",
        'tests/Support/Deeper.php' => "<?php\nnamespace Tests\\Support;\nuse Tests\\Concerns\\Indirect;\ntrait Deeper { use Indirect; }\n",
        'tests/Unit/FooTest.php' => "<?php\nuses(Tests\\Support\\Deeper::class);\n",
        'tests/Pest.php' => "<?php\n",
    ]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations())->toHaveKey('tests/Unit/FooTest.php');
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
});

it('detects a booting base bound to the unit directory in Pest.php', function (): void {
    $root = TestTierArchitectureHelpers::tierSandbox([
        'tests/Pest.php' => "<?php\npest()->extend(Tests\\FeatureTestCase::class)->in('Feature', 'Unit');\n",
        'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(true)->toBeTrue());\n",
    ]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations())->toHaveKey('tests/Pest.php');
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
});

dataset('booting Pest bindings', [
    'function call root' => ["<?php\nuses(Tests\\FeatureTestCase::class)->in('Unit');\n", 'tests/Pest.php'],
    'no directory in the global file' => ["<?php\npest()->extend(Tests\\FeatureTestCase::class);\n", 'tests/Pest.php'],
    'directory constant' => ["<?php\npest()->extend(Tests\\FeatureTestCase::class)->in(__DIR__ . '/Unit');\n", 'tests/Pest.php'],
    'relative directory' => ["<?php\npest()->extend(Tests\\FeatureTestCase::class)->in('./Unit');\n", 'tests/Pest.php'],
    'directory list' => ["<?php\npest()->extend(Tests\\FeatureTestCase::class)->in(['Feature', 'Unit']);\n", 'tests/Pest.php'],
    'class name as string' => ["<?php\npest()->extend('Tests\\\\FeatureTestCase')->in('Unit');\n", 'tests/Pest.php'],
    'binding in another file' => ["<?php\npest()->extend(Tests\\FeatureTestCase::class)->in('../Unit');\n", 'tests/Feature/BindingTest.php'],
]);

it('detects a booting base bound to the unit directory in every spelling', function (string $source, string $path): void {
    $root = TestTierArchitectureHelpers::tierSandbox([$path => $source, 'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(true)->toBeTrue());\n"]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations())->toHaveKey($path);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('booting Pest bindings');

it('does not flag a booting base bound to another directory', function (): void {
    $root = TestTierArchitectureHelpers::tierSandbox([
        'tests/Pest.php' => "<?php\npest()->extend(Tests\\FeatureTestCase::class)->in('Feature', 'Integration');\n",
        'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(true)->toBeTrue());\n",
    ]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations())->toBe([]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
});

it('detects a booting trait that lives outside the support directories', function (): void {
    $root = TestTierArchitectureHelpers::tierSandbox([
        'tests/Feature/Helpers/Booted.php' => "<?php\nnamespace Tests\\Feature\\Helpers;\nuse Illuminate\\Foundation\\Testing\\RefreshDatabase;\ntrait Booted { use RefreshDatabase; }\n",
        'tests/Unit/FooTest.php' => "<?php\nuses(Tests\\Feature\\Helpers\\Booted::class);\n",
        'tests/Pest.php' => "<?php\n",
    ]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations())->toHaveKey('tests/Unit/FooTest.php');
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
});

it('accepts the pure and bare container tiers in unit tests', function (): void {
    $root = TestTierArchitectureHelpers::tierSandbox([
        'tests/Concerns/BootsBareContainer.php' => "<?php\nnamespace Tests\\Concerns;\nuse Illuminate\\Foundation\\Application;\ntrait BootsBareContainer {}\n",
        'tests/Pest.php' => "<?php\npest()->extend(Tests\\PureTestCase::class)->in('Unit');\n",
        'tests/Unit/FooTest.php' => "<?php\nuses(Tests\\Concerns\\BootsBareContainer::class);\n",
    ]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->unitTestViolations())->toBe([]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
});

it('keeps every unit test off a booting base class', function (): void {
    expect(TestTierArchitectureHelpers::tierGuardFor(dirname(__DIR__, 2))->unitTestViolations())->toBe([]);
});

dataset('database layer sources', [
    'model import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Models\\Tenant\\User;\nfinal class A { public User \$user; }\n"],
    'module model import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Modules\\Orders\\Models\\Thing;\nfinal class A { public ?Thing \$thing = null; }\n"],
    'aliased import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Models\\Tenant\\User as Account;\nfinal class A { public function f(Account \$a): void {} }\n"],
    'group import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Models\\Tenant\\{Order, User};\nfinal class A {}\n"],
    'inline fully qualified name' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nfinal class A { public function f(): mixed { return \\App\\Models\\Tenant\\User::query(); } }\n"],
    'database facade' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class A { public function f(): void { DB::select('select 1'); } }\n"],
    'eloquent builder' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse Illuminate\\Database\\Eloquent\\Builder;\nfinal class A { public function f(Builder \$q): void {} }\n"],
]);

it('detects database layer references in every way a pure layer can name them', function (string $source): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['app/Modules/Orders/Exceptions/A.php' => $source]);

    try {
        expect(array_keys(TestTierArchitectureHelpers::tierGuardFor($root)->pureLayerViolations()))->toBe(['app/Modules/Orders/Exceptions/A.php']);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('database layer sources');

dataset('Data sources that may name a model', [
    'model in a static from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function __construct(public string \$id) {} public static function fromModel(User \$user): self { return new self(\$user->name); } }\n"],
    'inline name in a static from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nfinal class A { public static function fromId(int \$id): self { return \\App\\Models\\Tenant\\User::query()->findOrFail(\$id); } }\n"],
    'a private static from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { private static function fromTarget(User \$user): self { return new self(); } }\n"],
]);

it('lets a Data class name a model inside its static from factories', function (string $source): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['app/Modules/Orders/Data/A.php' => $source]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->pureLayerViolations())->toBe([]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('Data sources that may name a model');

dataset('Data sources that may not name a model', [
    'a property type' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function __construct(public User \$user) {} public static function fromModel(User \$user): self { return new self(\$user); } }\n"],
    'a constructor default' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function __construct(public string \$id = User::class) {} }\n"],
    'a static method that is not a from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public static function forUser(User \$user): self { return new self(); } }\n"],
    'an instance method named from' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function fromModel(User \$user): self { return \$this; } }\n"],
    'a private helper that is not a from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public static function fromModel(User \$user): self { return new self(self::label(\$user)); } private static function label(User \$user): string { return \$user->name; } }\n"],
    'the database facade in a from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class A { public static function fromId(int \$id): self { DB::table('users')->find(\$id); return new self(); } }\n"],
]);

it('keeps every other part of a Data class off the models', function (string $source): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['app/Modules/Orders/Data/A.php' => $source]);

    try {
        expect(array_keys(TestTierArchitectureHelpers::tierGuardFor($root)->pureLayerViolations()))->toBe(['app/Modules/Orders/Data/A.php']);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('Data sources that may not name a model');

it('ignores pure layer files that never reach the database layer', function (): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['app/Modules/Orders/Enums/A.php' => "<?php\nnamespace App\\Modules\\Orders\\Enums;\nuse Illuminate\\Support\\Collection;\nenum A: string { case X = 'x'; }\n"]);

    try {
        expect(TestTierArchitectureHelpers::tierGuardFor($root)->pureLayerViolations())->toBe([]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
});

it('keeps every pure layer off the database layer', function (): void {
    $root = dirname(__DIR__, 2);

    expect(TestTierArchitectureHelpers::tierGuardFor($root)->pureLayerViolations())->toBe([])
        ->and(file_exists($root . '/tests/Support/Architecture/pure-layer-allowlist.php'))->toBeFalse();
});

dataset('classes declared in a test file', [
    'helper class' => ["<?php\nfinal class Clock {}\nit('works', fn () => expect(true)->toBeTrue());\n", ['Clock']],
    'readonly helper class' => ["<?php\nfinal readonly class Handler {}\n", ['Handler']],
    'class behind an attribute' => ["<?php\n#[Attribute] final class Marker {}\n", ['Marker']],
    'upper case keyword' => ["<?php\nFINAL CLASS Shouty {}\n", ['Shouty']],
    'interface' => ["<?php\ninterface Probe {}\n", ['Probe']],
    'trait' => ["<?php\ntrait Helps {}\n", ['Helps']],
    'enum' => ["<?php\nenum Status: string { case A = 'a'; }\n", ['Status']],
    'second class next to the test case' => ["<?php\nnamespace Tests\\Unit;\nfinal class FooTest extends \\PHPUnit\\Framework\\TestCase {}\nfinal class Extra {}\n", ['Tests\\Unit\\Extra']],
]);

it('detects every class a test file declares besides its own test case', function (string $source, array $types): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['tests/Feature/FooTest.php' => $source]);

    try {
        expect((new TestClassGuard(new PhpFileInspector(), $root))->violations())->toBe(['tests/Feature/FooTest.php' => $types]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('classes declared in a test file');

dataset('files the test class guard allows', [
    'anonymous class' => ['tests/Unit/FooTest.php', "<?php\n\$clock = new class() { public int \$now = 0; };\n"],
    'anonymous class after new' => ['tests/Unit/FooTest.php', "<?php\nfunction clock(): object\n{\n    return new class extends ArrayObject {};\n}\n"],
    'test case named after the file' => ['tests/Integration/FooTest.php', "<?php\nnamespace Tests\\Integration;\nfinal class FooTest extends \\PHPUnit\\Framework\\TestCase {}\n"],
    'class in a source string' => ['tests/Unit/FooTest.php', "<?php\n\$source = \"<?php\\nfinal class Inline {}\\n\";\n"],
    'support class' => ['tests/Support/Clock.php', "<?php\nnamespace Tests\\Support;\nfinal class Clock {}\n"],
    'concern' => ['tests/Concerns/Helps.php', "<?php\nnamespace Tests\\Concerns;\ntrait Helps {}\n"],
    'fixture' => ['tests/Fixtures/Events/TestEvent.php', "<?php\nnamespace Tests\\Fixtures\\Events;\nclass TestEvent {}\n"],
    'fixture next to a test' => ['tests/Integration/Checkout/fixtures/FakeJob.php', "<?php\nnamespace Tests\\Integration\\Checkout\\fixtures;\nclass FakeJob {}\n"],
]);

it('allows anonymous classes, test cases named after their file and classes outside test files', function (string $path, string $source): void {
    $root = TestTierArchitectureHelpers::tierSandbox([$path => $source]);

    try {
        expect((new TestClassGuard(new PhpFileInspector(), $root))->violations())->toBe([]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('files the test class guard allows');

it('keeps every class out of the test files', function (): void {
    expect((new TestClassGuard(new PhpFileInspector(), dirname(__DIR__, 2)))->violations())->toBe([]);
});

dataset('functions declared in a test file', [
    'top level function' => ["<?php\nfunction helper(): void {}\n", ['helper']],
    'function returning by reference' => ["<?php\nfunction &helper(): array { static \$rows = []; return \$rows; }\n", ['helper']],
    'function behind function_exists' => ["<?php\nif (! function_exists('helper')) {\n    function helper(): void {}\n}\n", ['helper']],
    'function inside a describe closure' => ["<?php\ndescribe('group', function (): void {\n    function helper(): void {}\n});\n", ['helper']],
    'function inside a method body' => ["<?php\nfinal class Foo\n{\n    public function run(): void\n    {\n        function nested(): void {}\n    }\n}\n", ['nested']],
    'function in a namespace' => ["<?php\nnamespace Tests\\Feature;\nfunction helper(): void {}\n", ['helper']],
    'function after a class' => ["<?php\nfinal class Foo { public function run(): void {} }\nfunction helper(): void {}\n", ['helper']],
    'function after an anonymous class with a closure argument' => ["<?php\n\$probe = new class(fn () => 1) { public function run(): void {} };\nfunction helper(): void {}\n", ['helper']],
    'function after a class constant fetch' => ["<?php\n\$name = Foo::class;\nfunction helper(): void {}\n", ['helper']],
    'two functions' => ["<?php\nfunction first(): void {}\nfunction second(): void {}\n", ['first', 'second']],
]);

it('detects every named function a test file declares', function (string $source, array $functions): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['Feature/FooTest.php' => $source]);

    try {
        expect((new TestHelperFunctionGuard($root))->violations())->toBe(['Feature/FooTest.php' => $functions]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('functions declared in a test file');

dataset('files the test helper function guard allows', [
    'method in a class' => ["<?php\nfinal class Foo { public static function helper(): void {} }\n"],
    'method in a trait' => ["<?php\ntrait Helps { public function helper(): void {} }\n"],
    'method in an interface' => ["<?php\ninterface Probe { public function helper(): void; }\n"],
    'method in an enum' => ["<?php\nenum Status: string { case A = 'a'; public function label(): string { return 'a'; } }\n"],
    'method of an anonymous class' => ["<?php\n\$probe = new class(fn () => 1) { public function helper(): void {} };\n"],
    'closure' => ["<?php\n\$helper = function (): void {};\n\$other = static function (): void {};\n"],
    'arrow function' => ["<?php\n\$helper = fn (): int => 1;\n"],
    'closure after a class constant fetch' => ["<?php\n\$name = Foo::class;\n\$helper = function (): void {};\n"],
    'imported function' => ["<?php\nuse function Pest\\Laravel\\actingAs;\n"],
    'function in a string' => ["<?php\n\$source = 'function helper() {}';\n"],
    'function in a heredoc' => ["<?php\n\$source = <<<'PHP'\nfunction helper() {}\nPHP;\n"],
    'function in a comment' => ["<?php\n// function helper() {}\n"],
]);

it('allows methods, closures and function-shaped text in test files', function (string $source): void {
    $root = TestTierArchitectureHelpers::tierSandbox(['Feature/FooTest.php' => $source]);

    try {
        expect((new TestHelperFunctionGuard($root))->violations())->toBe([]);
    } finally {
        TestTierArchitectureHelpers::tierRemove($root);
    }
})->with('files the test helper function guard allows');

it('keeps every function out of the test files', function (): void {
    expect((new TestHelperFunctionGuard(dirname(__DIR__)))->violations())->toBe([]);
});
