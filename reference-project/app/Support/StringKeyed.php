<?php

declare(strict_types=1);

namespace App\Support;

final class StringKeyed
{
    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    public static function from(array $values): array
    {
        $keyed = [];

        foreach ($values as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fromOrNull(mixed $values): ?array
    {
        return is_array($values) ? self::from($values) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $rows = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $rows[] = self::from($value);
            }
        }

        return $rows;
    }
}
