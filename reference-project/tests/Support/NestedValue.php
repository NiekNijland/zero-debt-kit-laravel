<?php

declare(strict_types=1);

namespace Tests\Support;

use ArrayAccess;
use Closure;
use OutOfBoundsException;
use Stringable;
use Traversable;
use UnexpectedValueException;

final class NestedValue
{
    public static function get(mixed $data, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (is_array($data)) {
                if (! array_key_exists($key, $data)) {
                    throw new OutOfBoundsException(sprintf('The key "%s" does not exist.', $key));
                }

                $data = $data[$key];

                continue;
            }

            if ($data instanceof ArrayAccess) {
                $data = $data[$key];

                continue;
            }

            if (is_object($data) && array_key_exists($key, get_object_vars($data))) {
                $data = get_object_vars($data)[$key];

                continue;
            }

            throw new UnexpectedValueException(sprintf('Cannot read the key "%s" of a value that is not an array.', $key));
        }

        return $data;
    }

    public static function find(mixed $data, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $data = $data[$key];

                continue;
            }

            if ($data instanceof ArrayAccess && $data->offsetExists($key)) {
                $data = $data[$key];

                continue;
            }

            return null;
        }

        return $data;
    }

    public static function closure(mixed $data, string|int ...$path): Closure
    {
        $value = self::get($data, ...$path);

        if (is_callable($value)) {
            return $value(...);
        }

        throw new UnexpectedValueException('The value is not callable.');
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $data, string|int ...$path): array
    {
        $value = self::get($data, ...$path);

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        throw new UnexpectedValueException('The value is not an array.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $data, string|int ...$path): array
    {
        $value = self::array($data, ...$path);

        $map = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('The array has a key that is not a string.');
            }

            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $data, string|int ...$path): array
    {
        $strings = [];

        foreach (self::array($data, ...$path) as $item) {
            if (! is_string($item)) {
                throw new UnexpectedValueException('The array has an item that is not a string.');
            }

            $strings[] = $item;
        }

        return $strings;
    }

    public static function float(mixed $data, string|int ...$path): float
    {
        $value = self::get($data, ...$path);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        throw new UnexpectedValueException('The value is not a number.');
    }

    public static function bool(mixed $data, string|int ...$path): bool
    {
        $value = self::get($data, ...$path);

        if (is_bool($value)) {
            return $value;
        }

        throw new UnexpectedValueException('The value is not a boolean.');
    }

    public static function string(mixed $data, string|int ...$path): string
    {
        $value = self::get($data, ...$path);

        if (is_string($value)) {
            return $value;
        }

        throw new UnexpectedValueException('The value is not a string.');
    }

    public static function text(mixed $data, string|int ...$path): string
    {
        $value = self::get($data, ...$path);

        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new UnexpectedValueException('The value cannot be read as text.');
    }

    public static function int(mixed $data, string|int ...$path): int
    {
        $value = self::get($data, ...$path);

        if (is_int($value)) {
            return $value;
        }

        throw new UnexpectedValueException('The value is not an integer.');
    }
}
