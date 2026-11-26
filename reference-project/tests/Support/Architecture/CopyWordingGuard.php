<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Arr;
use Tests\Support\RecursiveFiles;

final readonly class CopyWordingGuard
{
    private const array REPLACEMENTS = [
        'cart' => '"basket"',
        'carts' => '"baskets"',
    ];

    /**
     * @param list<array<string, array<string, string>>> $translations the copy the application holds in code, by locale and key
     * @param string $langFolder the folder of the language files, relative to the root
     */
    public function __construct(
        private string $root,
        private array $translations,
        private string $langFolder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->translations as $translations) {
            foreach ($translations as $locale => $texts) {
                foreach ($texts as $key => $text) {
                    foreach (self::bannedWords($text) as $word) {
                        $violations[] = $locale . ' ' . $key . ': ' . $word;
                    }
                }
            }
        }

        foreach (RecursiveFiles::in($this->root . '/' . $this->langFolder) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (Arr::dot(Arr::wrap(require $file->getPathname())) as $line => $copy) {
                foreach (is_string($copy) ? self::bannedWords($copy) : [] as $banned) {
                    $violations[] = $this->langFolder . '/' . basename($file->getPath()) . '/' . $file->getBasename('.php') . ' ' . $line . ': ' . $banned;
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function bannedWords(string $text): array
    {
        $copy = (string) preg_replace(['/\{\{.*?\}\}/s', '/(?<![\w:]):[a-z_]+/i'], ' ', $text);
        $found = [];

        preg_match_all('/\b(' . implode('|', array_keys(self::REPLACEMENTS)) . ')\b/iu', $copy, $matches);

        foreach (array_unique(array_map(mb_strtolower(...), $matches[1])) as $word) {
            $found[] = '"' . $word . '": write ' . self::REPLACEMENTS[$word];
        }

        return $found;
    }
}
