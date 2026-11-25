<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

final class ClassNames
{
    /**
     * @return class-string
     */
    public static function of(string $name): string
    {
        if (! class_exists($name) && ! interface_exists($name) && ! enum_exists($name) && ! trait_exists($name)) {
            throw new InvalidArgumentException($name . ' is not a known class.');
        }

        return $name;
    }
}
