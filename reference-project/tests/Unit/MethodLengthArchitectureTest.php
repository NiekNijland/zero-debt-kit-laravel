<?php

declare(strict_types=1);

use Tests\Support\Unit\MethodLengthArchitectureHelpers;

it('measures a method from its signature to its closing brace', function (): void {
    $body = str_repeat("        \$x = 1;\n", 77);
    $source = "<?php\nnamespace App;\nfinal class A\n{\n    public function fits(): void\n    {\n{$body}    }\n\n    #[\\Override]\n    public function tooLong(): void\n    {\n{$body}\$y = 2;\n    }\n}\n";

    expect(MethodLengthArchitectureHelpers::longMethods($source))->toBe(['App\\A::tooLong' => 82]);
});

it('measures methods of traits, enums and anonymous classes', function (): void {
    $method = "    public function m(): void\n    {\n" . str_repeat("        \$x = 1;\n", 3) . "    }\n";
    $source = "<?php\nnamespace App;\ntrait T\n{\n{$method}}\nenum E\n{\n{$method}}\n\$a = new class()\n{\n{$method}};\n";

    expect(MethodLengthArchitectureHelpers::longMethods($source, 5))->toBe([
        'App\\T::m' => 6,
        'App\\E::m' => 6,
        'class@21::m' => 6,
    ]);
});

it('lets no method in app span more than 80 lines', function (): void {
    expect(MethodLengthArchitectureHelpers::longMethodsInApp())->toBe([]);
});
