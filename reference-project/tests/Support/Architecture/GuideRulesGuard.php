<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GuideRulesGuard
{
    /**
     * @param list<string> $ruleIds   the ids of the rules the detector class has
     * @param list<string> $guideFiles the guides whose sentences the rules read
     */
    public function __construct(
        private string $rowsFile,
        private array $guideFiles,
        private array $ruleIds,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $markdown = (string) file_get_contents($this->rowsFile);
        $rows = self::rowsIn($markdown);
        $guides = self::squash(implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $this->guideFiles)));
        $violations = [];

        foreach (self::duplicateIds($markdown) as $id) {
            $violations[] = 'row ' . $id . ' appears twice: keep one row per rule, a second one would silently replace the first';
        }

        foreach (array_diff($this->ruleIds, array_keys($rows)) as $id) {
            $violations[] = 'rule ' . $id . ' has no row: add id, the words the guide must hold and what to do instead';
        }

        foreach (array_diff(array_keys($rows), $this->ruleIds) as $id) {
            $violations[] = 'row ' . $id . ' has no rule in the detector: remove the row, or add the rule';
        }

        foreach ($rows as $id => $row) {
            if ($row['words'] === []) {
                $violations[] = 'row ' . $id . ' names no words: a row without words pins nothing';
            }

            foreach ($row['words'] as $word) {
                if (! str_contains($guides, self::squash($word))) {
                    $violations[] = 'rule ' . $id . ': the guides no longer say "' . $word . '": restore the sentence, or ' . $row['instead'];
                }
            }
        }

        return $violations;
    }

    /**
     * The rows of the table outside a fenced block and an indented block: a row that stands in an example of the
     * table is not a rule.
     *
     * @return array<string, array{words: list<string>, instead: string}>
     */
    public static function rowsIn(string $markdown): array
    {
        $rows = [];

        foreach (self::tableLines($markdown) as $cells) {
            $words = array_values(array_filter(array_map(static fn (string $word): string => trim($word, ' `'), explode(';', $cells[2])), static fn (string $word): bool => $word !== ''));
            $rows[$cells[1]] = ['words' => $words, 'instead' => $cells[3]];
        }

        return $rows;
    }

    /**
     * @return list<string> the ids that two rows carry
     */
    public static function duplicateIds(string $markdown): array
    {
        $ids = array_map(static fn (array $cells): string => $cells[1], self::tableLines($markdown));

        return array_values(array_unique(array_diff_key($ids, array_unique($ids))));
    }

    /**
     * @return list<list<string>> the cells of every table row of five cells with an id in the second one
     */
    private static function tableLines(string $markdown): array
    {
        $found = [];
        $fenced = false;

        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $fenced = ! $fenced;

                continue;
            }

            if ($fenced || preg_match('/^(    |\t)/', $line) === 1) {
                continue;
            }

            $cells = array_map(static fn (string $cell): string => trim($cell), explode('|', trim($line)));

            if (count($cells) === 5 && $cells[0] === '' && $cells[4] === '' && preg_match('/^[a-z][a-z0-9-]*$/', $cells[1]) === 1) {
                $found[] = $cells;
            }
        }

        return $found;
    }

    private static function squash(string $text): string
    {
        return (string) preg_replace('/\s+/', ' ', $text);
    }
}
