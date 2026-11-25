<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use ReflectionClass;
use stdClass;
use Stringable;

final class Narrow
{
    public static function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (int) $value;
        }

        throw new LogicException('The value is not an integer: ' . get_debug_type($value));
    }

    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new LogicException('The value is not a string: ' . get_debug_type($value));
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        throw new LogicException('The value is not an array: ' . get_debug_type($value));
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return array_values(self::array($value));
    }

    public static function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : self::int($value);
    }

    public static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new LogicException('The value is not a boolean: ' . get_debug_type($value));
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function stringLists(mixed $value): array
    {
        return array_map(self::strings(...), self::map($value));
    }

    /**
     * @template TObject of object
     *
     * @param class-string<TObject> $class
     * @param array<string, mixed> $arguments
     * @return TObject
     */
    public static function build(string $class, array $arguments): object
    {
        return new ReflectionClass($class)->newInstanceArgs($arguments);
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_map(self::string(...), self::list($value));
    }

    /**
     * @return array<string, string>
     */
    public static function stringMap(mixed $value): array
    {
        return array_map(self::string(...), self::map($value));
    }

    /**
     * @template TObject of object
     *
     * @param class-string<TObject> $class
     * @return TObject|null
     */
    public static function instanceOrNull(mixed $value, string $class): ?object
    {
        return $value === null ? null : self::instance($value, $class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        $result = [];

        foreach (self::array($value) as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    public static function time(mixed $value): DateTimeInterface|string|null
    {
        if ($value === null || is_string($value) || $value instanceof DateTimeInterface) {
            return $value;
        }

        throw new LogicException('The value is not a point in time: ' . get_debug_type($value));
    }

    public static function object(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        throw new LogicException('The value is not an object: ' . get_debug_type($value));
    }

    public static function row(mixed $value): stdClass
    {
        if ($value instanceof stdClass) {
            return $value;
        }

        throw new LogicException('The value is not a database row: ' . get_debug_type($value));
    }

    /**
     * @template TObject of object
     *
     * @param class-string<TObject> $class
     * @return TObject
     */
    public static function instance(mixed $value, string $class): object
    {
        if ($value instanceof $class) {
            return $value;
        }

        throw new LogicException('The value is not a ' . $class . ': ' . get_debug_type($value));
    }

    /**
     * @return callable(): mixed
     */
    public static function callable(mixed $value): callable
    {
        if (is_callable($value)) {
            return $value;
        }

        throw new LogicException('The value is not callable: ' . get_debug_type($value));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function call(object $callable, array $arguments): mixed
    {
        if (! is_callable($callable)) {
            throw new LogicException('The value is not callable: ' . get_debug_type($callable));
        }

        return $callable(...$arguments);
    }

    public static function at(mixed $value, string $path): mixed
    {
        return data_get($value, $path);
    }

    /**
     * @template TModel of Model
     *
     * @param TModel $model
     * @return TModel
     */
    public static function fresh(Model $model): Model
    {
        return $model->fresh() ?? throw new LogicException('The model no longer exists: ' . $model::class);
    }
}
