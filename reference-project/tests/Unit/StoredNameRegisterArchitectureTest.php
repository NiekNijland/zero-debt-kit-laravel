<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\StoredNameRegisterGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

$guideText = static fn (): string => "# Guide\n\n" . implode("\n", array_map(static fn (string $words): string => "- {$words}", StoredNameRegisterGuard::GUIDE)) . "\n";

$run = static function (array $files, array $registers = [], ?string $guide = null, ?string $aliases = null) use ($guideText): array {
    $files = Narrow::stringMap($files);
    $registers = Narrow::stringMap($registers);
    $tree = ['app/AGENTS.md' => $guide ?? $guideText(), ...$files];

    foreach (['queued-classes', 'closure-classes'] as $kind) {
        $tree["database/stored-names/{$kind}.txt"] = $registers[$kind] ?? '';
    }

    if ($aliases !== null) {
        $tree['database/stored-names/aliases.tsv'] = $aliases;
    }

    $root = SourceSandbox::create($tree);

    try {
        return new StoredNameRegisterGuard(new PhpFileInspector(), $root, 'app', 'app/AGENTS.md', 'database/stored-names', $aliases === null ? '' : 'database/stored-names/aliases.tsv', 'App', 'App\\Modules\\')->findings();
    } finally {
        SourceSandbox::remove($root);
    }
};

$job = static fn (string $declaration, string $members = '', string $path = 'app/Modules/Orders/Jobs/SendReceiptJob.php', string $namespace = 'App\\Modules\\Orders\\Jobs'): array => [$path => "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue as Queued;\n\n{$declaration}\n{\n{$members}\n}\n"];

$starter = static fn (string $body): array => ['app/Modules/Orders/Services/RefundStarter.php' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Modules\\Orders\\Services;\n\nuse App\\Modules\\Orders\\Services\\RefundCleaner;\nuse Illuminate\\Support\\Facades\\Bus;\nuse Illuminate\\Support\\Facades\\Redis;\n\nfinal class RefundStarter\n{\n    public function start(): void\n    {\n{$body}\n    }\n}\n"];

$chain = "        Bus::chain([])->catch(function (\\Throwable \$e): void {\n            resolve(RefundCleaner::class)->clean(\$e);\n        })->dispatch();";

dataset('stored names out of line', [
    'a queued job that is on no register' => [$job('final class SendReceiptJob implements ShouldQueue'), [], 'database/stored-names/queued-classes.txt: App\Modules\Orders\Jobs\SendReceiptJob is missing: add the line, sorted'],
    'a queued job reached through an aliased contract' => [$job('final class SendReceiptJob implements Queued'), [], 'queued-classes.txt: App\Modules\Orders\Jobs\SendReceiptJob is missing'],
    'a queued job that inherits the contract from a parent of the application' => [[...$job('abstract class BaseJob implements ShouldQueue', '', 'app/Modules/Orders/Jobs/BaseJob.php'), 'app/Modules/Orders/Jobs/ChildJob.php' => "<?php\n\nnamespace App\\Modules\\Orders\\Jobs;\n\nfinal class ChildJob extends BaseJob\n{\n}\n"], [], 'queued-classes.txt: App\Modules\Orders\Jobs\ChildJob is missing'],
    'the class a chain callback names' => [$starter($chain), [], 'closure-classes.txt: App\Modules\Orders\Services\RefundCleaner is missing: add the line, sorted'],
    'the defining class of a chain callback, which is its scope' => [$starter($chain), ['closure-classes' => "App\\Modules\\Orders\\Services\\RefundCleaner\n"], 'closure-classes.txt: App\Modules\Orders\Services\RefundStarter is missing'],
    'a dispatched closure' => [$starter("        dispatch(function (): void {\n            RefundCleaner::run();\n        });"), [], 'closure-classes.txt: App\Modules\Orders\Services\RefundCleaner is missing'],
    'a closure queued through the facade' => [$starter("        \\Illuminate\\Support\\Facades\\Queue::push(function (): void {\n            RefundCleaner::run();\n        });"), [], 'closure-classes.txt: App\Modules\Orders\Services\RefundStarter is missing'],
    'a register line for a job that is gone' => [[], ['queued-classes' => "App\\Modules\\Orders\\Jobs\\GoneJob\n"], 'queued-classes.txt: App\Modules\Orders\Jobs\GoneJob names no such class: delete the line'],
    'an unsorted register' => [[...$job('final class SendReceiptJob implements ShouldQueue'), ...$job('final class AuditJob implements ShouldQueue', '', 'app/Modules/Orders/Jobs/AuditJob.php')], ['queued-classes' => "App\\Modules\\Orders\\Jobs\\SendReceiptJob\nApp\\Modules\\Orders\\Jobs\\AuditJob\n"], 'the register is not sorted or holds a line twice'],
    'a register that holds a line twice' => [$job('final class SendReceiptJob implements ShouldQueue'), ['queued-classes' => "App\\Modules\\Orders\\Jobs\\SendReceiptJob\nApp\\Modules\\Orders\\Jobs\\SendReceiptJob\n"], 'the register is not sorted or holds a line twice'],
    'a file the parser cannot read' => [['app/Broken.php' => "<?php\nfinal class Broken {\n"], [], 'unreadable app/Broken.php'],
]);

dataset('alias lists out of step', [
    'a stored class of the old layout without a line' => [$job('final class SendReceiptJob implements ShouldQueue', '', 'app/Orders/Jobs/SendReceiptJob.php', 'App\\Orders\\Jobs'), ['queued-classes' => "App\\Orders\\Jobs\\SendReceiptJob\n"], "App\\Orders\\Jobs\\AuditJob\tApp\\Modules\\Orders\\Jobs\\AuditJob\n", 'queued-classes.txt: App\Orders\Jobs\SendReceiptJob is stored under the old layout and has no line in database/stored-names/aliases.tsv'],
    'the new name of a line that is not on the register' => [$job('final class SendReceiptJob implements ShouldQueue', '', 'app/Orders/Jobs/SendReceiptJob.php', 'App\\Orders\\Jobs'), ['queued-classes' => "App\\Orders\\Jobs\\SendReceiptJob\n"], "App\\Orders\\Jobs\\SendReceiptJob\tApp\\Modules\\Orders\\Jobs\\SendReceiptJob\n", 'queued-classes.txt: App\Modules\Orders\Jobs\SendReceiptJob is missing: add the line, sorted'],
    'a line for a class stored under neither name' => [[], [], "App\\Orders\\Jobs\\GoneJob\tApp\\Modules\\Orders\\Jobs\\GoneJob\n", 'aliases.tsv: App\Orders\Jobs\GoneJob and App\Modules\Orders\Jobs\GoneJob name no stored class: delete the line'],
    'a line separated by a space' => [[], [], "App\\Orders\\Jobs\\GoneJob App\\Modules\\Orders\\Jobs\\GoneJob\n", 'is not an old name and a new name separated by one tab'],
    'a list not sorted by old name' => [[], [], "App\\Orders\\Jobs\\ZetaJob\tApp\\Modules\\Orders\\Jobs\\ZetaJob\nApp\\Orders\\Jobs\\AlphaJob\tApp\\Modules\\Orders\\Jobs\\AlphaJob\n", 'the list is not sorted by old name, or holds an old name or a new name twice'],
    'a new name used twice' => [[], [], "App\\Orders\\Jobs\\AlphaJob\tApp\\Modules\\Orders\\Jobs\\SendReceiptJob\nApp\\Orders\\Jobs\\ZetaJob\tApp\\Modules\\Orders\\Jobs\\SendReceiptJob\n", 'holds an old name or a new name twice'],
]);

it('fails a stored name that is on no register, a register line that names nothing and an unsorted register', function (array $files, array $registers, string $words) use ($run): void {
    expect(implode("\n", $run($files, $registers)))->toContain($words);
})->with('stored names out of line');

it('fails an alias list that is not in step with the stored classes', function (array $files, array $registers, string $aliases, string $words) use ($run): void {
    expect(implode("\n", $run($files, $registers, null, $aliases)))->toContain($words);
})->with('alias lists out of step');

it('fails a guide that lost a sentence the guard reads', function () use ($run): void {
    expect(implode("\n", $run([], [], "# Guide\n")))->toContain('app/AGENTS.md no longer holds the sentence the guard reads (every queued job carries its class name in the queue payload)');
});

dataset('registers in step with the code', [
    'a queued job on the register' => [$job('final class SendReceiptJob implements ShouldQueue'), ['queued-classes' => "App\\Modules\\Orders\\Jobs\\SendReceiptJob\n"]],
    'an abstract queued class' => [$job('abstract class SendReceiptJob implements ShouldQueue'), []],
    'a job that is not queued' => [$job('final class SendReceiptJob'), []],
    'a class that only looks like the contract' => [$job('final class SendReceiptJob implements \App\Contracts\ShouldQueue'), []],
    'a callback of a lock, which is no queue serializer' => [$starter("        Redis::throttle('key')->then(function (): void {\n            RefundCleaner::run();\n        });"), []],
    'a chain without a closure' => [$starter("        Bus::chain([])->onQueue('refunds')->dispatch();"), []],
    'a closure that never reaches a queue' => [$starter("        array_map(function (): void {\n            RefundCleaner::run();\n        }, []);"), []],
    'a chain callback with both names on the register' => [$starter($chain), ['closure-classes' => "App\\Modules\\Orders\\Services\\RefundCleaner\nApp\\Modules\\Orders\\Services\\RefundStarter\n"]],
    'no code and empty registers' => [[], []],
]);

it('passes registers in step with the code and classes that are not stored', function (array $files, array $registers) use ($run): void {
    expect($run($files, $registers))->toBe([]);
})->with('registers in step with the code');

it('passes an alias list in step with the stored classes before and after the move', function (array $files, array $registers, string $aliases) use ($run, $job): void {
    expect($run($files, $registers, null, $aliases))->toBe([]);
})->with([
    'before the move: the class under its old name, both names registered' => [$job('final class SendReceiptJob implements ShouldQueue', '', 'app/Orders/Jobs/SendReceiptJob.php', 'App\\Orders\\Jobs'), ['queued-classes' => "App\\Modules\\Orders\\Jobs\\SendReceiptJob\nApp\\Orders\\Jobs\\SendReceiptJob\n"], "App\\Orders\\Jobs\\SendReceiptJob\tApp\\Modules\\Orders\\Jobs\\SendReceiptJob\n"],
    'after the move: the class under its new name, both names registered' => [$job('final class SendReceiptJob implements ShouldQueue'), ['queued-classes' => "App\\Modules\\Orders\\Jobs\\SendReceiptJob\nApp\\Orders\\Jobs\\SendReceiptJob\n"], "App\\Orders\\Jobs\\SendReceiptJob\tApp\\Modules\\Orders\\Jobs\\SendReceiptJob\n"],
    'a new class already in the new layout needs no line' => [[...$job('final class SendReceiptJob implements ShouldQueue'), ...$job('final class NewJob implements ShouldQueue', '', 'app/Modules/Orders/Jobs/NewJob.php')], ['queued-classes' => "App\\Modules\\Orders\\Jobs\\NewJob\nApp\\Modules\\Orders\\Jobs\\SendReceiptJob\nApp\\Orders\\Jobs\\SendReceiptJob\n"], "App\\Orders\\Jobs\\SendReceiptJob\tApp\\Modules\\Orders\\Jobs\\SendReceiptJob\n"],
    'no alias list lines: the register holds the names of the code only' => [$job('final class SendReceiptJob implements ShouldQueue'), ['queued-classes' => "App\\Modules\\Orders\\Jobs\\SendReceiptJob\n"], ''],
]);

it('keeps the registers of stored names in step with the application code', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $guard = new StoredNameRegisterGuard($inspector, $root, 'app', 'app/AGENTS.md', 'database/stored-names', 'database/stored-names/aliases.tsv', 'App', 'App\\Modules\\');

    expect($inspector->phpFiles($root . '/app'))->not->toBe([])
        ->and(file_get_contents($root . '/database/stored-names/queued-classes.txt'))->toContain('App\Modules\Orders\Jobs\SendReceiptJob')
        ->and($guard->findings())->toBe([]);
});
