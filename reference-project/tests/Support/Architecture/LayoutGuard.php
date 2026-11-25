<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\RecursiveFiles;

final readonly class LayoutGuard
{
    private const array LAYER_FOLDERS = [
        'app' => ['Casts', 'Http', 'Models', 'Modules', 'Policies', 'Providers', 'Rules', 'Support', 'View'],
        'app/Http' => ['Controllers', 'Middleware', 'Requests'],
        'app/Models' => ['Landlord', 'Pivots', 'Tenant'],
    ];

    private const array MODULE_FOLDERS = ['Actions', 'Commands', 'Contracts', 'Data', 'Enums', 'Events', 'Exceptions', 'Facades', 'Jobs', 'Listeners', 'Mail', 'Notifications', 'Services', 'Support', 'ValueObjects'];

    private const array MODULE_SUFFIXES = [
        'Commands' => ['Command'],
        'Data' => ['Data'],
        'Events' => ['Event'],
        'Exceptions' => ['Exception'],
        'Jobs' => ['Job'],
        'Mail' => ['Mail'],
        'Notifications' => ['Notification'],
        'Services' => ['Client', 'Service'],
    ];

    private const array FLAT_SUFFIXES = [
        'app/Http/Controllers' => ['Controller'],
        'app/Http/Requests' => ['Request'],
        'app/Policies' => ['Policy'],
        'app/Providers' => ['ServiceProvider', 'Provider'],
    ];

    public function __construct(private string $root)
    {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (self::LAYER_FOLDERS as $layer => $folders) {
            if (! is_dir($this->path($layer))) {
                continue;
            }

            foreach ($this->entries($layer) as $entry) {
                if (is_dir($this->path($entry))) {
                    if (! in_array(basename($entry), $folders, true)) {
                        array_push($violations, ...$this->misplaced($entry));
                    }
                } elseif (str_ends_with($entry, '.php')) {
                    $violations[] = 'place ' . $entry;
                }
            }
        }

        foreach ($this->entries('app/Modules') as $module) {
            if (! is_dir($this->path($module))) {
                if (str_ends_with($module, '.php')) {
                    $violations[] = 'place ' . $module;
                }

                continue;
            }

            foreach ($this->entries($module) as $entry) {
                if (! is_dir($this->path($entry))) {
                    if (str_ends_with($entry, '.php')) {
                        $violations[] = 'place ' . $entry;
                    }

                    continue;
                }

                $folder = basename($entry);

                if (! in_array($folder, self::MODULE_FOLDERS, true)) {
                    array_push($violations, ...$this->misplaced($entry));

                    continue;
                }

                array_push($violations, ...$this->misnamed($entry, self::MODULE_SUFFIXES[$folder] ?? []));
            }
        }

        foreach (self::FLAT_SUFFIXES as $folder => $suffixes) {
            array_push($violations, ...$this->misnamed($folder, $suffixes));
        }

        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * @param list<string> $suffixes
     * @return list<string>
     */
    private function misnamed(string $folder, array $suffixes): array
    {
        if ($suffixes === [] || ! is_dir($this->path($folder))) {
            return [];
        }

        $misnamed = [];

        foreach ($this->phpFiles($folder) as $file) {
            $name = basename($file, '.php');

            if (! array_any($suffixes, static fn (string $suffix): bool => str_ends_with($name, $suffix))) {
                $misnamed[] = 'name ' . $file;
            }
        }

        return $misnamed;
    }

    /**
     * @return list<string>
     */
    private function misplaced(string $folder): array
    {
        return array_map(static fn (string $file): string => 'place ' . $file, $this->phpFiles($folder));
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $folder): array
    {
        $files = [];

        foreach (RecursiveFiles::in($this->path($folder)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($this->root) + 1);
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function entries(string $folder): array
    {
        $entries = scandir($this->path($folder));

        if ($entries === false) {
            return [];
        }

        return array_map(
            static fn (string $entry): string => $folder . '/' . $entry,
            array_values(array_diff($entries, ['.', '..'])),
        );
    }

    private function path(string $relative): string
    {
        return $this->root . '/' . $relative;
    }
}
