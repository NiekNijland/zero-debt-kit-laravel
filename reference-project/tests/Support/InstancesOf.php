<?php

declare(strict_types=1);

namespace Tests\Support;

use UnexpectedValueException;

final class InstancesOf
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @return T
     */
    public static function one(mixed $value, string $class): object
    {
        if ($value instanceof $class) {
            return $value;
        }

        throw new UnexpectedValueException(sprintf('The value is not an instance of %s.', $class));
    }

    /**
     * @template T of object
     *
     * @param iterable<mixed> $items
     * @param class-string<T> $class
     * @return list<T>
     */
    public static function list(iterable $items, string $class): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = self::one($item, $class);
        }

        return $list;
    }
}
