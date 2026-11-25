<?php

declare(strict_types=1);

use Tests\Support\Unit\EmptyCatchArchitectureHelpers;

dataset('swallowed broad catches', [
    'imported throwable' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable) {}\n"],
    'fully qualified exception' => ["<?php\nnamespace App;\ntry { f(); } catch (\\Exception \$e) {}\n"],
    'comment only' => ["<?php\nnamespace App;\nuse RuntimeException;\ntry { f(); } catch (RuntimeException) {\n    // ignored\n}\n"],
    'union with a broad type' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (\\App\\Foo | Throwable) {}\n"],
    'inside a closure' => ["<?php\nnamespace App;\n\$f = function (): void { try { f(); } catch (\\Error) {} };\n"],
    'aliased throwable' => ["<?php\nnamespace App;\nuse Throwable as Failure;\ntry { f(); } catch (Failure) {}\n"],
    'return null' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): ?int { try { return f(); } catch (Throwable) { return null; } }\n"],
    'return false' => ["<?php\nnamespace App;\nuse Exception;\nfunction g(): bool { try { return f(); } catch (Exception \$e) {\n    // ignored\n    return false;\n} }\n"],
    'return true' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): bool { try { return f(); } catch (Throwable) { return true; } }\n"],
    'return an empty array' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): array { try { return f(); } catch (Throwable) { return []; } }\n"],
    'bare return' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): void { try { f(); } catch (Throwable) { return; } }\n"],
    'fallback value' => ["<?php\nnamespace App;\nuse Throwable;\nfunction h(): array { try { return f(); } catch (Throwable) { return ['fallback']; } }\n"],
    'log only' => ["<?php\nnamespace App;\nuse Throwable;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (Throwable \$e) { Log::warning(\$e->getMessage()); }\n"],
    'console error only' => ["<?php\nnamespace App;\nuse RuntimeException;\nfinal class C { public function handle(): int { try { f(); } catch (RuntimeException \$e) { \$this->error(\$e->getMessage()); return 1; } return 0; } }\n"],
    'a domain exception factory without throw' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { \$failure = FooExceptions::wrap(\$e); }\n"],
]);

it('detects a broad catch that only swallows the exception', function (string $source): void {
    expect(EmptyCatchArchitectureHelpers::swallowedCatchLines($source))->toHaveCount(1);
})->with('swallowed broad catches');

it('accepts catches that report, rescue, rethrow, fail or name the exact expected exception', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Throwable;
        use League\Flysystem\UnableToReadFile;
        use Illuminate\Support\Facades\Exceptions;
        try { f(); } catch (Throwable $e) { report($e); }
        try { f(); } catch (Throwable $e) { throw $e; }
        try { f(); } catch (Throwable $e) { throw new \DomainException('wrapped', previous: $e); }
        try { f(); } catch (UnableToReadFile) {}
        try { f(); } catch (Throwable $e) { rescue(static fn () => report($e), report: false); }
        try { f(); } catch (Throwable $e) { Exceptions::report($e); }
        try { f(); } catch (Throwable $e) { ReportedFailureException::reportSafely('Label', $e); }
        function g(): ?int { try { return f(); } catch (Throwable $e) { report($e); return null; } }
        final class T { public function test(): void { try { f(); } catch (\Exception $e) { $this->fail($e->getMessage()); } } }
        PHP;

    expect(EmptyCatchArchitectureHelpers::swallowedCatchLines($source))->toBe([]);
});

it('lets no file in app swallow a broad exception', function (): void {
    expect(EmptyCatchArchitectureHelpers::swallowedCatchCounts())->toBe([]);
});
