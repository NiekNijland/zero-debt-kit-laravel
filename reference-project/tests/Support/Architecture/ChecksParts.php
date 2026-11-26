<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use LogicException;

/**
 * The parts of the checks step, read from the config block of the checks script itself (`PARTS=( "<part>::<tasks>" ... )`), so
 * no pipeline guard keeps a copy of the list: a part added to the script and forgotten in a pipeline fails the guard.
 */
final class ChecksParts
{
    /**
     * @return list<string>
     */
    public static function of(string $checksScript): array
    {
        if (preg_match('/^PARTS=\((.*?)^\)/ms', $checksScript, $block) !== 1) {
            throw new LogicException('The checks script holds no PARTS=( ... ) table: the guard cannot read the parts of the checks step');
        }

        preg_match_all('/^\s*"([A-Za-z][A-Za-z0-9-]*)::/m', $block[1], $names);

        if ($names[1] === []) {
            throw new LogicException('The PARTS table of the checks script names no part');
        }

        return $names[1];
    }
}
