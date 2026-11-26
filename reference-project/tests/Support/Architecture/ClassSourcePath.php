<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use ReflectionClass;

final class ClassSourcePath
{
    /**
     * @param class-string $class
     * @return string the path of the file that declares the class, relative to the root
     */
    public static function of(string $class, string $root): string
    {
        return mb_substr((string) new ReflectionClass($class)->getFileName(), mb_strlen($root) + 1);
    }
}
