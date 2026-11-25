<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Modules\Events;

use App\Modules\Events\Events\Event;
use ReflectionClass;
use Tests\Support\RecursiveFiles;
use Throwable;

final class AuditEventHelpers
{
    /**
     * @return list<class-string<Event>>
     */
    public static function everyConcreteEventClass(): array
    {
        $root = base_path('app');
        $classes = [];

        foreach (RecursiveFiles::in($root) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = 'App\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -4));

            try {
                if (! class_exists($class)) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            if (is_subclass_of($class, Event::class) && ! new ReflectionClass($class)->isAbstract()) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param class-string<Event> $class
     */
    public static function isAuditEvent(string $class): bool
    {
        return $class::audits();
    }
}
