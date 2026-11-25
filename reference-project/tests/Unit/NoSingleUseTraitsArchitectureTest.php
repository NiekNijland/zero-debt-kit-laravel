<?php

declare(strict_types=1);

use Tests\Support\Unit\NoSingleUseTraitsArchitectureHelpers;

it('counts the distinct classes that use each trait', function (): void {
    $sources = [
        "<?php\nnamespace App\\Support;\ntrait Shared {}\ntrait Lonely {}\ntrait Unused {}\n",
        "<?php\nnamespace App\\One;\nuse App\\Support\\Shared;\nuse App\\Support\\Lonely;\nfinal class A { use Shared; use Lonely; }\n",
        "<?php\nnamespace App\\Two;\nfinal class B { use \\App\\Support\\Shared; }\n",
    ];

    expect(NoSingleUseTraitsArchitectureHelpers::traitUsers($sources))->toBe([
        'App\\Support\\Lonely' => 1,
        'App\\Support\\Shared' => 2,
        'App\\Support\\Unused' => 0,
    ])->and(NoSingleUseTraitsArchitectureHelpers::singleUseTraits($sources))->toBe(['App\\Support\\Lonely']);
});

it('counts a class that uses a trait twice once, and an anonymous class as a user of its own', function (): void {
    $sources = [
        "<?php\nnamespace App;\ntrait T {}\nfinal class A { use T; use T; }\n",
        "<?php\nnamespace App;\n\$x = new class() { use T; };\n",
        "<?php\nnamespace App;\ntrait Inner {}\ntrait Outer { use Inner; }\n",
    ];

    expect(NoSingleUseTraitsArchitectureHelpers::traitUsers($sources))->toBe([
        'App\\Inner' => 1,
        'App\\Outer' => 0,
        'App\\T' => 2,
    ]);
});

it('lets no trait in app be used by exactly one class', function (): void {
    expect(NoSingleUseTraitsArchitectureHelpers::singleUseTraitsInApp())->toBe([]);
});
