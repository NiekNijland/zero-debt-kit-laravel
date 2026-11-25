<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Migrations\Migration;
use LogicException;

final class Migrations
{
    public static function load(string $file): Migration
    {
        $migration = require database_path('migrations/' . $file);

        if ($migration instanceof Migration) {
            return $migration;
        }

        throw new LogicException('The file does not return a migration: ' . $file);
    }
}
