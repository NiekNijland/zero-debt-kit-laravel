<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use DateTimeInterface;
use Stringable;

final class Scalar
{
    public static function int(mixed $value, int $default = 0): int
    {
        return self::intOrNull($value) ?? $default;
    }

    public static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return self::floatOrNull($value) ?? $default;
    }

    public static function floatOrNull(mixed $value): ?float
    {
        if (is_float($value)) {
            return $value;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return self::stringOrNull($value) ?? $default;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof Stringable => (string) $value,
            default => null,
        };
    }

    /**
     * @param iterable<mixed> $values
     * @return list<int>
     */
    public static function ints(iterable $values): array
    {
        $ints = [];

        foreach ($values as $value) {
            $int = self::intOrNull($value);

            if ($int !== null) {
                $ints[] = $int;
            }
        }

        return $ints;
    }

    /**
     * @param iterable<mixed> $values
     * @return list<string>
     */
    public static function strings(iterable $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            $string = self::stringOrNull($value);

            if ($string !== null) {
                $strings[] = $string;
            }
        }

        return $strings;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param iterable<array-key, mixed> $values
     * @return array<string, string>
     */
    public static function stringMap(iterable $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            $string = self::stringOrNull($value);

            if ($string !== null) {
                $map[(string) $key] = $string;
            }
        }

        return $map;
    }

    /**
     * @param iterable<array-key, mixed> $values
     * @return array<string, int>
     */
    public static function intMap(iterable $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            $int = self::intOrNull($value);

            if ($int !== null) {
                $map[(string) $key] = $int;
            }
        }

        return $map;
    }

    /**
     * @param iterable<array-key, mixed> $values
     * @return array<string, bool>
     */
    public static function boolMap(iterable $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($bool !== null) {
                $map[(string) $key] = $bool;
            }
        }

        return $map;
    }

    /**
     * @param iterable<mixed> $values
     * @return array<int, int>
     */
    public static function intsById(iterable $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            $int = self::intOrNull($value);

            if (is_int($key) && $int !== null) {
                $map[$key] = $int;
            }
        }

        return $map;
    }

    /**
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    public static function stringsById(iterable $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            $string = self::stringOrNull($value);

            if (is_int($key) && $string !== null) {
                $map[$key] = $string;
            }
        }

        return $map;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    public static function map(array $values): array
    {
        $map = [];

        foreach ($values as $key => $value) {
            $map[(string) $key] = $value;
        }

        return $map;
    }
}
