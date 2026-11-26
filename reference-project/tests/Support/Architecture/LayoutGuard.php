<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\RecursiveFiles;

final readonly class LayoutGuard
{
    private const string PLACE = 'move it to a folder of the layout table, or add the folder to the table first';

    private const string NAME = 'end the class name in %s, as the name column of the layout table says';

    /**
     * The tables are the layout table of the guide, handed in by the thin test (the older, argument form of the
     * module layout guard, which reads the table from the guide itself).
     *
     * @param array<string, list<string>> $layerFolders folders a layer may hold, by layer path relative to the root
     * @param string $moduleRoot the folder whose children are modules
     * @param list<string> $moduleFolders the folders a module may hold
     * @param array<string, list<string>> $moduleSuffixes the suffixes of the names in a module folder
     * @param array<string, list<string>> $flatSuffixes the suffixes of the names in a folder outside the modules, by path
     */
    public function __construct(
        private string $root,
        private array $layerFolders,
        private string $moduleRoot,
        private array $moduleFolders,
        private array $moduleSuffixes,
        private array $flatSuffixes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->layerFolders as $layer => $folders) {
            if (! is_dir($this->path($layer))) {
                continue;
            }

            foreach ($this->entries($layer) as $entry) {
                if (is_dir($this->path($entry))) {
                    if (! in_array(basename($entry), $folders, true)) {
                        array_push($violations, ...$this->misplaced($entry));
                    }
                } elseif (str_ends_with($entry, '.php')) {
                    $violations[] = 'place ' . $entry . ': ' . self::PLACE;
                }
            }
        }

        foreach ($this->entries($this->moduleRoot) as $module) {
            if (! is_dir($this->path($module))) {
                if (str_ends_with($module, '.php')) {
                    $violations[] = 'place ' . $module . ': ' . self::PLACE;
                }

                continue;
            }

            foreach ($this->entries($module) as $entry) {
                if (! is_dir($this->path($entry))) {
                    if (str_ends_with($entry, '.php')) {
                        $violations[] = 'place ' . $entry . ': ' . self::PLACE;
                    }

                    continue;
                }

                $folder = basename($entry);

                if (! in_array($folder, $this->moduleFolders, true)) {
                    array_push($violations, ...$this->misplaced($entry));

                    continue;
                }

                array_push($violations, ...$this->misnamed($entry, $this->moduleSuffixes[$folder] ?? []));
            }
        }

        foreach ($this->flatSuffixes as $folder => $suffixes) {
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
                $misnamed[] = 'name ' . $file . ': ' . sprintf(self::NAME, implode(' or ', $suffixes));
            }
        }

        return $misnamed;
    }

    /**
     * @return list<string>
     */
    private function misplaced(string $folder): array
    {
        return array_map(static fn (string $file): string => 'place ' . $file . ': ' . self::PLACE, $this->phpFiles($folder));
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $folder): array
    {
        $files = [];

        foreach (RecursiveFiles::in($this->path($folder)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = mb_substr($file->getPathname(), mb_strlen($this->root) + 1);
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function entries(string $folder): array
    {
        $entries = is_dir($this->path($folder)) ? scandir($this->path($folder)) : false;

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
