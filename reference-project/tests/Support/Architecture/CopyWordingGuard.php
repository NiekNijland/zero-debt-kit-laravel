<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Modules\Copy\Support\CopyFiles;
use Illuminate\Support\Arr;
use Tests\Support\RecursiveFiles;

final class CopyWordingGuard
{
    private const array REPLACEMENTS = [
        'cart' => '"basket"',
        'carts' => '"baskets"',
    ];

    /**
     * @return list<string>
     */
    public static function violations(): array
    {
        $violations = [];

        foreach ([CopyFiles::system(), CopyFiles::framework()] as $translations) {
            foreach ($translations as $locale => $texts) {
                foreach ($texts as $key => $text) {
                    foreach (self::bannedWords($text) as $word) {
                        $violations[] = $locale . ' ' . $key . ': ' . $word;
                    }
                }
            }
        }

        foreach (RecursiveFiles::in(dirname(__DIR__, 3) . '/lang') as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (Arr::dot(Arr::wrap(require $file->getPathname())) as $line => $copy) {
                foreach (is_string($copy) ? self::bannedWords($copy) : [] as $banned) {
                    $violations[] = 'lang/' . basename($file->getPath()) . '/' . $file->getBasename('.php') . ' ' . $line . ': ' . $banned;
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
