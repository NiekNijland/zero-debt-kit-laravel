<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Override;
use ReflectionClass;
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

        $note = $this->entryPointNote($class, $name, $method)
            ?? $this->libraryNote($class, $name, $method)
            ?? $this->hookNote($class, $name, $method);

        return $note === null ? null : VirtualUsageData::withNote($note);
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function entryPointNote(ReflectionClass $class, string $name, ReflectionMethod $method): ?string
    {
        $in = static fn (string $namespace): bool => str_starts_with($class->getName(), $namespace);

        return match (true) {
            $name === '__construct' && $in('App\\') => 'The container builds the classes of the application and injects their constructors.',
            $in('App\\Policies\\') && $method->isPublic() => 'Policy abilities are checked by name from routes and the gate.',
            $in('App\\Http\\Controllers\\') && $name === '__invoke' => 'The router calls the invokable controller.',
            $in('App\\Http\\Middleware\\') && $name === 'handle' => 'The router calls handle() of a middleware.',
            $in('App\\Http\\Requests\\') && $name === 'rules' => 'A form request is validated through rules().',
            $in('App\\Providers\\') && ($name === 'boot' || $name === 'register') => 'The container registers and boots providers.',
            $in('Database\\Factories\\') && $name === 'definition' => 'A factory calls definition().',
            $class->isSubclassOf(Seeder::class) && $name === 'run' => 'The seed command calls run().',
            $in('App\\Models\\') && $name === 'casts' => 'A model calls casts() when it builds its attributes.',
            default => null,
        };
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function libraryNote(ReflectionClass $class, string $name, ReflectionMethod $method): ?string
    {
        $first = $method->getParameters()[0] ?? null;
        $takesJob = $first instanceof ReflectionParameter && $first->getType() instanceof ReflectionNamedType && $first->getType()->getName() === Job::class;

        return match (true) {
            $class->isSubclassOf(Data::class) && ($name === 'defaultWrap' || $name === 'rules' || str_starts_with($name, 'from')) => 'Spatie Data calls its magic creation methods and reads rules() when it validates.',
            $method->isPublic() && $takesJob => 'The queue calls handler strings (Class@method) with the job.',
            $class->isSubclassOf(Notification::class) && in_array($name, ['broadcastAs', 'broadcastType', 'via'], true) => 'The notification channels call these.',
            default => null,
        };
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function hookNote(ReflectionClass $class, string $name, ReflectionMethod $method): ?string
    {
        return match (true) {
            $method->isStatic() && preg_match('/^(boot|initialize)[A-Z]/', $name) === 1 => 'Eloquent boots and initializes its traits by name.',
            str_contains($class->getName(), '\\Listeners\\') && in_array($name, ['shouldQueue', 'viaQueue', 'viaConnection'], true) => 'The dispatcher asks queued listeners.',
            str_contains((string) $method->getFileName(), '/tests/Concerns/') && ($name === 'migrateDatabases' || preg_match('/^(setUp|tearDown)[A-Z]/', $name) === 1) => 'Laravel runs trait hooks by name.',
            default => null,
        };
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
