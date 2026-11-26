<?php

declare(strict_types=1);

use Tests\Support\Architecture\CountedList;
use Tests\Support\Architecture\PhpSource;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('reads one key per line and ignores blank lines and comments', function (): void {
    $root = SourceSandbox::create(['list.txt' => "# why this list exists\napp/A.php:10\n\n  app/B.php:20  \n"]);

    try {
        $list = CountedList::fromFile($root . '/list.txt');

        expect($list->count())->toBe(2)
            ->and($list->violations(['app/A.php:10', 'app/B.php:20']))->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('fails a key that is found and not listed, and a listed key that is no longer found', function (): void {
    $list = new CountedList(['app/A.php:10', 'app/B.php:20']);

    expect($list->violations(['app/A.php:10', 'app/C.php:30']))->toBe(['new app/C.php:30', 'stale app/B.php:20'])
        ->and($list->added(['app/A.php:10', 'app/C.php:30']))->toBe(['app/C.php:30'])
        ->and($list->stale(['app/A.php:10', 'app/C.php:30']))->toBe(['app/B.php:20']);
});

it('counts a swap as a new key and a stale key, so the count alone is not the check', function (): void {
    expect((new CountedList(['app/A.php:10']))->violations(['app/Z.php:1']))->toBe(['new app/Z.php:1', 'stale app/A.php:10']);
});

it('fails on a list file that does not exist, so a renamed list cannot switch a guard off', function (): void {
    $root = SourceSandbox::create(['other.txt' => 'x']);

    try {
        expect(static fn (): CountedList => CountedList::fromFile($root . '/missing.txt'))->toThrow(InvalidArgumentException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('parses a source text once for every guard: names as written, names resolved, and a visitor entry', function (): void {
    $source = "<?php\nnamespace App;\n\nuse Illuminate\\Support\\Str;\n\nfinal class A { public function a(): string { return Str::of('x'); } }\n";

    $resolved = PhpSource::resolved($source);
    $visited = [];
    PhpSource::traverse(PhpSource::parse($source), new class($visited) extends PhpParser\NodeVisitorAbstract
    {
        public function __construct(private array &$seen)
        {
        }

        #[Override]
        public function enterNode(PhpParser\Node $node): null
        {
            $this->seen[] = $node::class;

            return null;
        }
    });

    expect($resolved)->not->toBeEmpty()
        ->and($visited)->toContain(PhpParser\Node\Stmt\Class_::class);
});
