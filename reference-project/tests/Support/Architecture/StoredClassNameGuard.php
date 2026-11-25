<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;
use Tests\Support\Narrow;

final readonly class StoredClassNameGuard
{
    private const string SNAPSHOTS = 'tests/Support/Architecture/stored-class-names';

    private const array KINDS = [
        'events' => ['folder' => 'Events', 'duty' => 'the landlord events.name column stores it: add a data migration that renames the rows'],
        'jobs' => ['folder' => 'Jobs', 'duty' => 'queued payloads (jobs, failed_jobs, Horizon) store it: keep the old name in MovedClassServiceProvider::ALIASES for one release'],
        'mail' => ['folder' => 'Mail', 'duty' => 'queued payloads store it: keep the old name in MovedClassServiceProvider::ALIASES for one release'],
        'notifications' => ['folder' => 'Notifications', 'duty' => 'notifications.type and queued payloads store it: add a data migration that renames the rows and keep the old name in MovedClassServiceProvider::ALIASES for one release'],
    ];

    /**
     * @param array<string, string> $aliases
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $aliases,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (self::KINDS as $kind => $rule) {
            $snapshot = self::SNAPSHOTS . '/' . $kind . '.php';
            $stored = $this->stored($kind);
            $declared = $this->declared($kind);

            foreach (array_diff($declared, $stored) as $class) {
                $violations[] = $class . ' is not in ' . $snapshot . ': add it there, because ' . $rule['duty'] . ' when the class is ever moved or renamed';
            }

            foreach (array_diff($stored, $declared) as $class) {
                $target = $this->aliases[$class] ?? null;

                if ($target === null) {
                    $violations[] = $class . ' in ' . $snapshot . ' no longer exists: ' . $rule['duty'] . ', then replace the entry by the new name';
                } elseif (! in_array($target, $declared, true)) {
                    $violations[] = $class . ' in ' . $snapshot . ' is an alias of ' . $target . ', which is not a class in a ' . $rule['folder'] . ' folder: point the alias in MovedClassServiceProvider::ALIASES at the class that replaced it';
                }
            }

            if ($stored !== array_values(array_unique($stored))) {
                $violations[] = $snapshot . ' names a class twice: keep one line per class';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function declared(string $kind): array
    {
        $classes = [];

        foreach (Globs::of($this->root . '/app/Modules/*/' . self::KINDS[$kind]['folder']) as $folder) {
            foreach ($this->inspector->phpFiles($folder) as $file) {
                $classes[] = 'App\\' . str_replace('/', '\\', substr($file, strlen($this->root . '/app/'), -4));
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    public function stored(string $kind): array
    {
        $file = $this->root . '/' . self::SNAPSHOTS . '/' . $kind . '.php';

        return is_file($file) ? Narrow::strings(require $file) : [];
    }
}
