<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Illuminate\Contracts\Queue\Job;
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
}
