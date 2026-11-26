<?php

declare(strict_types=1);

use Tests\Support\Architecture\DocumentedPathGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('guides that name a path that is gone', [
    'a file from the repository root' => [
        ['AGENTS.md' => "# Guide\n\nCopy `app/Support/Like.php`, not `app/Support/Search.php`.\n", 'app/Support/Like.php' => "<?php\n"],
        'AGENTS.md:3: `app/Support/Search.php` does not exist',
    ],
    'a folder in a nested guide' => [
        ['tests/AGENTS.md' => "| Helpers | `tests/Support/Feature/` | `tests/Support/Browser/` |\n", 'tests/Support/Feature/Helpers.php' => "<?php\n"],
        'tests/AGENTS.md:1: `tests/Support/Browser/` does not exist',
    ],
    'a relative file in the design guide' => [
        ['DESIGN.md' => "Use `admin/row-action.tsx` or\n`admin/row-menu.tsx`.\n", 'resources/js/Components/admin/row-action.tsx' => ''],
        'DESIGN.md:2: no file ends in `admin/row-menu.tsx`',
    ],
    'the review rubric' => [
        ['docs/review-rubric.md' => "The list is `scripts/ci-ratchets.sh` (see `tests/Unit/GoneTest.php`).\n", 'scripts/ci-ratchets.sh' => ''],
        'docs/review-rubric.md:1: `tests/Unit/GoneTest.php` does not exist',
    ],
]);

it('detects a guide that names a path that does not exist', function (array $files, string $reason): void {
    $root = SourceSandbox::create(Narrow::stringMap($files));

    try {
        $violations = new DocumentedPathGuard($root)->violations();

        expect($violations)->toHaveCount(1)
            ->and($violations[0])->toContain($reason)
            ->and($violations[0])->toContain('placeholder such as <Name>');
    } finally {
        SourceSandbox::remove($root);
    }
})->with('guides that name a path that is gone');

it('accepts paths that exist, placeholders, wildcards and text that is not a path', function (): void {
    $root = SourceSandbox::create([
        'AGENTS.md' => implode("\n", [
            'Copy `app/Support/Like.php` in `app/Support` or `app/Support/`; the page is `Pages/Order/Show.tsx`, the hook `hooks/use-debounce.ts`.',
            'A module is `app/Modules/<Domain>/Actions`, a plan `docs/plans/example-*.md`, areas `app/Http/Controllers/{Admin,Landlord}`.',
            'Run `composer pint`, read `vendor/package/README.md`, write `storage/app/x.json`, call `Inertia::render()` and `Route::get(\'/a/b\')`.',
            'Values read `and/or`, `text/plain`, `resources/js/...` and `phpstan.neon`.',
        ]),
        'app/Support/Like.php' => "<?php\n",
        'resources/js/Pages/Order/Show.tsx' => '',
        'resources/js/hooks/use-debounce.ts' => '',
        'vendor/package/composer.json' => '{}',
        'storage/app/.gitignore' => '',
        'README.md' => 'Not a guide: `app/Gone.php`.',
        'docs/notes.md' => 'Not a guide: `app/Gone.php`.',
    ]);

    try {
        expect(new DocumentedPathGuard($root)->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('names only paths that exist in AGENTS.md, DESIGN.md and the review rubric', function (): void {
    $root = dirname(__DIR__, 2);

    expect(DocumentedPathGuard::pathsIn((string) file_get_contents($root . '/AGENTS.md')))->not->toBe([])
        ->and(new DocumentedPathGuard($root)->violations())->toBe([]);
});
