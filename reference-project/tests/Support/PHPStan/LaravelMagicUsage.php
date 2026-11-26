<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Override;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Spatie\LaravelData\Data;

final class LaravelMagicUsage extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        $class = $method->getDeclaringClass();
        $name = $method->getName();

        $application = str_starts_with($class->getName(), 'App\\');

        if ($name === '__construct' && $application) {
            return VirtualUsageData::withNote('The container builds the classes of the application and injects their constructors.');
        }

        if ($application && $this->isCalledByTheFramework($class->getName(), $name)) {
            return VirtualUsageData::withNote('The framework calls this method by name: the router, the container, the validator, the model or the console.');
        }

        if ($class->isSubclassOf(Seeder::class) && $name === 'run') {
            return VirtualUsageData::withNote('The seed command calls run().');
        }

        if (str_starts_with($class->getName(), 'Database\\Factories\\') && $name === 'definition') {
            return VirtualUsageData::withNote('A factory calls definition().');
        }

        if (str_starts_with($class->getName(), 'App\\Policies\\') && $method->isPublic()) {
            return VirtualUsageData::withNote('Policy abilities are checked by name from routes and the gate.');
        }

        if ($class->isSubclassOf(Data::class) && ($name === 'defaultWrap' || $name === 'rules' || str_starts_with($name, 'from'))) {
            return VirtualUsageData::withNote('Spatie Data calls its magic creation methods and reads rules() when it validates.');
        }

        $first = $method->getParameters()[0] ?? null;

        if ($first instanceof ReflectionParameter && $method->isPublic() && $first->getType() instanceof ReflectionNamedType && $first->getType()->getName() === Job::class) {
            return VirtualUsageData::withNote('The queue calls handler strings (Class@method) with the job.');
        }

        if ($method->isStatic() && preg_match('/^(boot|initialize)[A-Z]/', $name) === 1) {
            return VirtualUsageData::withNote('Eloquent boots and initializes its traits by name.');
        }

        if (str_contains($class->getName(), '\\Listeners\\') && in_array($name, ['shouldQueue', 'viaQueue', 'viaConnection'], true)) {
            return VirtualUsageData::withNote('The dispatcher asks queued listeners.');
        }

        if ($class->isSubclassOf(Notification::class) && ($name === 'broadcastAs' || $name === 'broadcastType' || $name === 'via')) {
            return VirtualUsageData::withNote('The notification channels call these.');
        }

        if (str_contains((string) $method->getFileName(), '/tests/Concerns/') && ($name === 'migrateDatabases' || preg_match('/^(setUp|tearDown)[A-Z]/', $name) === 1)) {
            return VirtualUsageData::withNote('Laravel runs trait hooks by name.');
        }

        return null;
    }

    /**
     * The folders of a Laravel application and the method the framework calls on a class in each: nothing in the code calls them, so the dead-code detector
     * would report each one.
     */
    private function isCalledByTheFramework(string $class, string $method): bool
    {
        $byFolder = [
            'App\\Http\\Controllers\\' => ['__invoke'],
            'App\\Http\\Middleware\\' => ['handle'],
            'App\\Http\\Requests\\' => ['rules', 'authorize', 'messages', 'attributes', 'prepareForValidation', 'passedValidation'],
            'App\\Providers\\' => ['boot', 'register'],
            'App\\Console\\Commands\\' => ['handle'],
            'App\\Rules\\' => ['validate'],
            'App\\Models\\' => ['casts', 'booted'],
        ];

        foreach ($byFolder as $folder => $methods) {
            if (str_starts_with($class, $folder) && in_array($method, $methods, true)) {
                return true;
            }
        }

        return str_contains($class, '\\Commands\\') && $method === 'handle'
            || str_contains($class, '\\Listeners\\') && $method === 'handle'
            || str_contains($class, '\\Jobs\\') && $method === 'handle';
    }
}
