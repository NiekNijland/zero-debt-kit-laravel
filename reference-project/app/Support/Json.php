<?php

declare(strict_types=1);

namespace App\Support;

use JsonException;

final class Json
{
    public static function decodeOrNull(string $json): mixed
    {
        try {
            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    public static function encodeOrNull(mixed $value): ?string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
}
