<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\Globs;

final class OneModulePerDomainArchitectureHelpers
{
    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return array_map(basename(...), Globs::of(dirname(__DIR__, 3) . '/app/Modules/*', GLOB_ONLYDIR));
    }

    /**
     * @param list<string> $modules
     * @return list<string>
     */
    public static function overlaps(array $modules): array
    {
        $offenders = [];

        foreach ($modules as $module) {
            foreach ($modules as $other) {
                if ($module === $other) {
                    continue;
                }

                $stems = array_unique([$other, str_ends_with($other, 's') ? substr($other, 0, -1) : $other]);
                $plurals = [$other . 's', str_ends_with($other, 'y') ? substr($other, 0, -1) . 'ies' : $other . 's'];
                $prefixed = array_filter($stems, static fn (string $stem): bool => preg_match('/^' . preg_quote($stem, '/') . '[A-Z]/', $module) === 1);

                if ($prefixed !== [] || in_array($module, $plurals, true)) {
                    $offenders[] = $module . ' overlaps ' . $other;
                }
            }
        }

        return $offenders;
    }
}
