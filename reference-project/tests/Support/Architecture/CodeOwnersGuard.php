<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * Point 22 of the CI contract (04-guards.md C4): the files that carry the guards are covered by a code-owners rule that names an
 * owner. The `[ratchet: ...]` override tag sits in a commit message that the author of the pull request writes, so only a review
 * by a person who owns those files stops an author from writing it. The host's setting that makes the owner's review required
 * (GitHub: branch protection "Require review from Code Owners"; GitLab: Code Owners approval in a protected branch rule) is not
 * in the repository and stays the owner's (07-process.md section 12); this guard holds the half that is a file. It also fails a rule
 * whose only owners look like a bot. Whether the owner can approve at all (the author of a pull request cannot) is a question of
 * the identities the agents work under, which no file shows: ci/CODEOWNERS.example names the three states.
 *
 * Bitbucket Cloud has no code-owners file: default reviewers and a merge check are settings the test cannot read. There the
 * profile names them and the test is created with `hostEnforced: true`, which accepts a missing file and still reads one.
 */
final readonly class CodeOwnersGuard
{
    /**
     * @param list<string> $paths the files that carry the guards, relative to the root: `scripts/`, the caps file, kit-config.sh, the pipeline files,
     *                            the guard folder and the code-owners file itself
     */
    public function __construct(
        private string $codeOwnersFile,
        private array $paths,
        private bool $hostEnforced = false,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        if (! is_file($this->codeOwnersFile)) {
            return $this->hostEnforced ? [] : ['no code-owners file at ' . $this->codeOwnersFile . ': copy ci/CODEOWNERS.example (GitHub: .github/CODEOWNERS, GitLab: CODEOWNERS or .gitlab/CODEOWNERS); on a host without the file, name the default reviewers and the merge check in the profile and pass hostEnforced'];
        }

        return $this->inCodeOwners((string) file_get_contents($this->codeOwnersFile));
    }

    /**
     * @return list<string>
     */
    public function inCodeOwners(string $text): array
    {
        $rules = self::rules($text);
        $violations = [];

        foreach ($rules as [$pattern]) {
            if (str_starts_with($pattern, '!')) {
                $violations[] = 'the pattern `' . $pattern . '` negates a rule: the code-owners file has no negation, and the host reads such a line as an invalid rule';
            }
        }

        foreach ($this->paths as $path) {
            $owners = null;

            foreach ($rules as [$pattern, $ruleOwners]) {
                if (preg_match(self::regex($pattern), $path) === 1) {
                    $owners = $ruleOwners;
                }
            }

            if ($owners === null) {
                $violations[] = $path . ' is covered by no code-owners rule: an author could edit it, or write the override tag, without the owner\'s review';
            } elseif ($owners === []) {
                $violations[] = $path . ' is matched last by a rule with no owner: that rule takes the file out of the review';
            } elseif (array_filter($owners, static fn (string $owner): bool => preg_match('/(\[bot\]|[-_]bot|^@?bot|^@?github-actions)$/i', $owner) === 1) === $owners) {
                $violations[] = $path . ' is owned by a bot only (' . implode(', ', $owners) . '): an automation account is not the person who reads the reason of an override';
            }
        }

        return $violations;
    }

    /**
     * @return list<array{string, list<string>}>
     */
    private static function rules(string $text): array
    {
        $rules = [];

        foreach (explode("\n", $text) as $line) {
            $line = trim((string) preg_replace('/(^|\s)#.*$/', '', $line));

            if ($line === '' || str_starts_with($line, '[') || str_starts_with($line, '^[')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $rules[] = [(string) array_shift($parts), array_values(array_filter($parts, static fn (string $owner): bool => str_starts_with($owner, '@') || str_contains($owner, '@')))];
        }

        return $rules;
    }

    private static function regex(string $pattern): string
    {
        $directory = str_ends_with($pattern, '/');
        $trimmed = trim($pattern, '/');
        $anchored = str_starts_with($pattern, '/') || str_contains($trimmed, '/');
        $body = '';

        foreach (preg_split('#(\*\*/|/\*\*|\*\*|\*|\?)#', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $body .= match ($token) {
                '**/' => '(?:.*/)?',
                '/**' => '/.*',
                '**' => '.*',
                '*' => '[^/]*',
                '?' => '[^/]',
                default => preg_quote($token, '#'),
            };
        }

        return '#^' . ($anchored ? '' : '(?:.*/)?') . $body . ($directory ? '/.*' : '(?:/.*)?') . '$#';
    }
}
