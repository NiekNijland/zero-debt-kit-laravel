<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;

/**
 * The one reader of a counted list (`04-guards.md` C5): a committed file with one key per line, blank lines and lines that start with `#` ignored.
 * A guard compares what it found with the list: a key that is found and not listed is new (fails), and a key that is listed and no longer found is stale
 * (fails until it is removed). The count is the number of keys, which is what the ratchet row counts. At zero the list file is deleted.
 */
final readonly class CountedList
{
    /**
     * @param list<string> $keys
     */
    public function __construct(private array $keys)
    {
    }

    /**
     * A missing file is an error, not an empty list: a renamed list must fail the guard, not switch it off.
     */
    public static function fromFile(string $file): self
    {
        if (! is_file($file)) {
            throw new InvalidArgumentException("the counted list {$file} does not exist: a guard that reads a list fails when it is renamed or deleted early");
        }

        $keys = [];

        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#')) {
                $keys[] = $line;
            }
        }

        return new self($keys);
    }

    public function count(): int
    {
        return count(array_unique($this->keys));
    }

    /**
     * @param list<string> $found
     *
     * @return list<string> the found keys that are not listed
     */
    public function added(array $found): array
    {
        return array_values(array_diff(array_unique($found), $this->keys));
    }

    /**
     * @param list<string> $found
     *
     * @return list<string> the listed keys that are no longer found
     */
    public function stale(array $found): array
    {
        return array_values(array_diff(array_unique($this->keys), $found));
    }

    /**
     * @param list<string> $found
     *
     * @return list<string> `new <key>` and `stale <key>`, sorted: empty when the list is exactly what the tree holds
     */
    public function violations(array $found): array
    {
        $violations = [
            ...array_map(static fn (string $key): string => 'new ' . $key, $this->added($found)),
            ...array_map(static fn (string $key): string => 'stale ' . $key, $this->stale($found)),
        ];
        sort($violations);

        return $violations;
    }
}
