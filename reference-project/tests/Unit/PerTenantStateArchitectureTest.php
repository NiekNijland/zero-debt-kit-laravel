<?php

declare(strict_types=1);

use Tests\Support\Architecture\PerTenantStateGuard;
use Tests\Support\Architecture\PhpFileInspector;

dataset('config writes in the wrong place', [
    'a provider that reads the current tenant' => ["<?php\nnamespace App\\Providers;\nuse App\\Models\\Landlord\\Tenant;\nuse Illuminate\\Support\\Facades\\Config;\nuse Illuminate\\Support\\ServiceProvider;\nfinal class NameServiceProvider extends ServiceProvider { public function boot(): void { Config::set('app.name', Tenant::current()?->name); } }\n", 'a provider reads the current tenant to set config'],
    'a provider that checks for a tenant in a closure' => ["<?php\nnamespace App\\Providers;\nuse App\\Models\\Landlord\\Tenant;\nuse Illuminate\\Support\\Facades\\Config;\nuse Illuminate\\Support\\ServiceProvider;\nfinal class LocaleServiceProvider extends ServiceProvider { public function boot(): void { \$this->app->booted(function (): void { if (Tenant::checkCurrent()) { Config::set('app.locale', 'fr'); } }); } }\n", 'a provider reads the current tenant to set config'],
    'a provider that reads the tenant from the container' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Support\\ServiceProvider;\nfinal class MailServiceProvider extends ServiceProvider { public function boot(): void { config(['mail.from.name' => \$this->app->make('currentTenant')->name]); } }\n", 'a provider reads the current tenant to set config'],
    'a service' => ["<?php\nnamespace App\\Modules\\Orders\\Services;\nuse Illuminate\\Support\\Facades\\Config;\nfinal readonly class TimeoutService { public function widen(): void { Config::set('carrier.timeout', 60); } }\n", 'outside a switch task and outside a service provider'],
    'the helper with set()' => ["<?php\nnamespace App\\Modules\\Orders\\Actions;\nfinal readonly class Rename { public function handle(): void { config()->set('app.name', 'x'); } }\n", 'outside a switch task and outside a service provider'],
    'the global facade alias in a job' => ["<?php\nnamespace App\\Modules\\Orders\\Jobs;\nfinal class RenameJob { public function handle(): void { \\Config::push('app.names', 'x'); } }\n", 'outside a switch task and outside a service provider'],
]);

it('detects config that is written for the current tenant outside a switch task', function (string $source, string $reason): void {
    $violations = new PerTenantStateGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('SwitchTenantTask');
})->with('config writes in the wrong place');

it('accepts a switch task, a provider that sets config without the tenant and every config read', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Providers;
        use App\Models\Landlord\Tenant;
        use Illuminate\Support\Facades\Config;
        use Illuminate\Support\ServiceProvider;
        use Spatie\Multitenancy\Contracts\IsTenant;
        use Spatie\Multitenancy\Tasks\SwitchTenantTask;
        final class SwitchAppName implements SwitchTenantTask
        {
            public function makeCurrent(IsTenant $tenant): void { Config::set('app.name', $tenant->name); }
            public function forgetCurrent(): void { Config::set('app.name', 'x'); }
        }
        final class AppServiceProvider extends ServiceProvider
        {
            public function register(): void { Config::set('mail.from.name', Config::string('app.name')); }
            public function boot(): void { $key = (string) Tenant::current()?->id; $name = Config::string('app.name'); $this->headers->set('X', $key . $name); }
        }
        PHP;

    expect(new PerTenantStateGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source))->toBe([]);
});

it('writes config at runtime only in a switch task, or in a provider that does not read the current tenant', function (): void {
    expect(new PerTenantStateGuard(new PhpFileInspector(), dirname(__DIR__, 2))->violations())->toBe([]);
});

it('lists every switch task of app in config/multitenancy.php', function (): void {
    expect(new PerTenantStateGuard(new PhpFileInspector(), dirname(__DIR__, 2))->unlistedTasks())->toBe([]);
});
