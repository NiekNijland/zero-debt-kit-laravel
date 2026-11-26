<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Copy\Support\CopyFiles;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The translation keys the frontend and the backend read, against the seeded texts. The repository root, the frontend folder and
 * the folders that count as production code (folder to file extensions) are arguments: the thin test holds the literals.
 */
final class FrontendTranslationKeys
{
    public const string PLACEHOLDER = '*';

    private const string TEST_ONLY = '~(\.(test|spec|stories|mock)\.|fixtures|/(__mocks__|mocks|storybook|stories)/|\.d\.ts$|/types/generated\.ts$)~';

    private const string TEST_ONLY_AND_FIXTURES = '~(\.(test|spec|stories|mock)\.|fixtures|/(__mocks__|mocks|storybook|stories)/|\.d\.ts$|/types/generated\.ts$|story-translations)~';

    private const string ROUTE_OR_PERMISSION_NAME = '/\b(?:route|to_route|routeIs|name|can|hasPermissionTo|permission|isRoute|current)\(\s*[\'"`][^\'"`]*[\'"`]/';

    private const string KEY_PREFIX = '/[\'"`]([A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z0-9_-]+)*\.)(?:\$\{|\{\$|%s|%d|[\'"`]\s*(?:\.|\+))/';

    private const string KEY_SUFFIX = '/(?:\}|\$[A-Za-z_]+(?:->\w+)*[\}\s]*[\'"`]?\s*\.\s*)([_.][A-Za-z0-9_.-]{2,})[\'"`]/';

    private const string DOTTED_LITERAL = '/[\'"`]([A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z0-9_-]+)+)(?:[\'"`]|\$\{|\{\$|%s|%d)/';

    /** @var array<string, array<string, string>>|null */
    private static ?array $seeded = null;

    /** @var array{keys: array<string, list<string>>, templates: array<string, list<string>>}|null */
    private ?array $found = null;

    /**
     * @param string $root the repository root
     * @param string $frontend the folder of the TypeScript sources, relative to the root
     * @param array<string, list<string>> $productionFolders folder relative to the root => the file extensions that are production code
     */
    public function __construct(
        private readonly string $root,
        private readonly string $frontend,
        private readonly array $productionFolders,
    ) {
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function seeded(): array
    {
        return self::$seeded ??= CopyFiles::system();
    }

    /**
     * @return array<string, list<string>>
     */
    public function keys(): array
    {
        return $this->found()['keys'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function templates(): array
    {
        return $this->found()['templates'];
    }

    public static function pattern(string $template): string
    {
        return '/^' . implode('.+', array_map(
            static fn (string $part): string => preg_quote($part, '/'),
            explode(self::PLACEHOLDER, $template),
        )) . '$/';
    }

    /**
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $text, $matches);

        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    public function unused(): array
    {
        $code = $this->productionCode();
        preg_match_all('/[A-Za-z][\w-]*(?:\.[\w-]+)+/', $code, $words);
        $words = array_flip($words[0]);
        $withoutNames = (string) preg_replace(self::ROUTE_OR_PERMISSION_NAME, '', $code);
        preg_match_all(self::KEY_PREFIX, $withoutNames, $prefixes);
        preg_match_all(self::KEY_SUFFIX, $withoutNames, $suffixes);
        preg_match_all(self::DOTTED_LITERAL, $withoutNames, $literals);

        $prefixes = array_flip($prefixes[1]);
        $suffixes = array_keys(array_flip($suffixes[1]));
        $literals = array_flip($literals[1]);
        $templates = array_values(array_filter(
            array_keys($this->templates()),
            static fn (string $template): bool => ! str_starts_with($template, self::PLACEHOLDER),
        ));
        $unused = [];

        foreach (array_keys(self::seeded()['en']) as $key) {
            if (isset($words[$key]) || isset($this->keys()[$key]) || self::isCovered($key, $prefixes, $suffixes, $literals, $templates)) {
                continue;
            }

            $unused[] = $key;
        }

        sort($unused);

        return $unused;
    }

    /**
     * @param array<string, int> $prefixes
     * @param list<string> $suffixes
     * @param array<string, int> $literals
     * @param list<string> $templates
     */
    private static function isCovered(string $key, array $prefixes, array $suffixes, array $literals, array $templates): bool
    {
        $segments = explode('.', $key);

        for ($length = count($segments) - 1; $length >= 1; $length--) {
            $parent = implode('.', array_slice($segments, 0, $length));

            if (isset($prefixes[$parent . '.']) || ($length >= 2 && isset($literals[$parent]))) {
                return true;
            }
        }

        return array_any($suffixes, static fn (string $suffix): bool => str_ends_with($key, $suffix))
            || array_any($templates, static fn (string $template): bool => preg_match(self::pattern($template), $key) === 1);
    }

    private function productionCode(): string
    {
        $code = '';

        foreach ($this->productionFolders as $directory => $extensions) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (in_array($file->getExtension(), $extensions, true) && preg_match(self::TEST_ONLY_AND_FIXTURES, $file->getPathname()) !== 1) {
                    $code .= file_get_contents($file->getPathname()) . "\n";
                }
            }
        }

        return $code;
    }

    /**
     * @return array{keys: array<string, list<string>>, templates: array<string, list<string>>}
     */
    private function found(): array
    {
        if ($this->found !== null) {
            return $this->found;
        }

        $root = $this->root;
        $found = ['keys' => [], 'templates' => []];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $this->frontend)) as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);

            if (! in_array($file->getExtension(), ['ts', 'tsx'], true) || preg_match(self::TEST_ONLY, $path) === 1) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach (FrontendTranslationLiterals::literalsIn($source) as [$type, $value, $offset]) {
                $found[$type][$value][] = $path . ':' . (substr_count($source, "\n", 0, $offset) + 1);
            }
        }

        foreach ($found as $type => $literals) {
            $found[$type] = array_map(static fn (array $locations): array => array_values(array_unique($locations)), $literals);
            ksort($found[$type]);
        }

        return $this->found = $found;
    }
}
