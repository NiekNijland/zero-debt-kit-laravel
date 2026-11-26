<?php

declare(strict_types=1);

use Tests\Support\Unit\ReportNotLogArchitectureHelpers;

uses()->group('arch');

dataset('severe logs in a catch', [
    'imported facade' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable \$e) { Log::error('failed', ['exception' => \$e]); }\n"],
    'global alias' => ["<?php\nnamespace App;\ntry { f(); } catch (\\RuntimeException \$e) { \\Log::error('failed'); }\n"],
    'a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::channel('daily')->error('failed'); }\n"],
    'nested in an if' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable \$e) { if (\$e->getCode() > 0) { Log::error('failed'); } }\n"],
    'a warning for an exact exception' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\App\\RefusedException) { Log::warning('refused'); }\n"],
    'a critical on a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::channel('daily')->critical('failed'); }\n"],
    'an alert' => ["<?php\nnamespace App;\ntry { f(); } catch (\\Throwable) { \\Log::alert('failed'); }\n"],
    'an emergency' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::emergency('failed'); }\n"],
]);

dataset('tenant ids in a log context', [
    'info' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::info('done', ['tenant_id' => \$tenant->id]);\n"],
    'warning in a catch' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::warning('skipped', ['order_id' => 1, 'tenant_id' => 2]); }\n"],
    'a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::channel('daily')->notice('done', ['tenant_id' => 2]);\n"],
]);

it('detects a Log::warning or more severe call that handles a caught exception', function (string $source): void {
    expect(ReportNotLogArchitectureHelpers::severeLogInCatchLines($source))->toHaveCount(1);
})->with('severe logs in a catch');

it('detects a tenant id written into a log context by hand', function (string $source): void {
    expect(ReportNotLogArchitectureHelpers::tenantIdLogKeyLines($source))->toHaveCount(1);
})->with('tenant ids in a log context');

it('accepts a report, an info or debug line in a catch, an error outside a catch and other tenant ids', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Facades\Log;
        try { f(); } catch (\Throwable $e) { report(DomainException::wrap($e)); }
        try { f(); } catch (\App\RefusedException) { Log::info('refused', ['user_id' => 1]); }
        try { f(); } catch (\App\RetriedException) { Log::channel('daily')->debug('retried'); }
        try { f(); } catch (\Throwable $e) { $later = static function (): void { Log::error('not here'); }; throw $e; }
        Log::error('a state that is not an exception', ['order_id' => 1]);
        Order::query()->where('tenant_id', 1)->update(['tenant_id' => 2]);
        Log::info('moved', ['supplier_id' => 2]);
        PHP;

    expect(ReportNotLogArchitectureHelpers::severeLogInCatchLines($source))->toBe([])
        ->and(ReportNotLogArchitectureHelpers::tenantIdLogKeyLines($source))->toBe([]);
});

it('reports caught exceptions instead of logging them, and leaves the tenant id to the log context', function (): void {
    expect(ReportNotLogArchitectureHelpers::offendersInApp())->toBe([]);
});
