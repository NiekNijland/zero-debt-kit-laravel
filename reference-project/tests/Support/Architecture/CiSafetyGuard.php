<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class CiSafetyGuard
{
    private const array REQUIRED_TASKS = ['Format', 'Refactor', 'Analyser', 'Type coverage', 'Lint', 'Guard tests', 'Audit', 'Commit messages', 'Secret scan'];

    /**
     * @param list<string> $writingLines lines an owner exception allows to write (an autofix commit the owner kept in step 1)
     */
    public function __construct(
        private string $baseBranch,
        private array $writingLines = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function inChecks(string $checksScript, string $kitConfig): array
    {
        $lines = self::code($checksScript);
        $text = implode("\n", $lines);
        $violations = [];

        $positions = array_map(static fn (string $part): int|false => strpos($text, $part), ['--guard scripts/ci-ratchets.sh', 'bash scripts/ci-ratchets.sh', 'debt-dashboard.sh $DASHBOARD_MODE --offline', 'bash scripts/ci-parallel.sh']);

        if (in_array(false, $positions, true) || $positions !== array_values(array_unique($positions)) || $positions !== self::sorted($positions)) {
            $violations[] = 'checks.sh does not run its four stages in the order: the base copy of the ratchets with --guard, the ratchets, the dashboard, the parallel checks';
        }

        if (preg_match('/debt-dashboard\.sh[^\n]*--offline --details/', $text) !== 1) {
            $violations[] = 'the dashboard line of checks.sh does not run with --offline --details';
        }

        foreach (self::REQUIRED_TASKS as $task) {
            if (! str_contains($text, '"' . $task . '::')) {
                $violations[] = 'checks.sh has no task named "' . $task . '": a check that is no entry of the parallel call does not run';
            }
        }

        if (preg_match('/<[a-z][a-z0-9 ,.:()\/-]*>/', $text) === 1) {
            $violations[] = 'checks.sh still holds a <placeholder>: replace it with the project\'s command';
        }

        if (preg_match('/^DEFAULT_BASE_BRANCH="([^"]*)"/m', $kitConfig, $match) !== 1 || $match[1] !== $this->baseBranch) {
            $violations[] = 'DEFAULT_BASE_BRANCH in kit-config.sh is not `' . $this->baseBranch . '`, the branch pull requests target';
        }

        return [...$violations, ...$this->inCommands($lines)];
    }

    /**
     * @param list<string> $lines the command lines of a pipeline step or a script
     * @return list<string>
     */
    public function inCommands(array $lines): array
    {
        $violations = [];

        foreach ($lines as $line) {
            if (in_array($line, $this->writingLines, true)) {
                continue;
            }

            if (preg_match('/\bgit\s+(commit|push|add)\b/', $line) === 1) {
                $violations[] = 'a command writes to the repository: `' . $line . '` (CI checks and never commits, unless the owner kept an autofix commit: pass the line as an exception)';
            }

            if (preg_match('/\|\|\s*(true|:|exit\s+0)(?![A-Za-z0-9_])|continue-on-error|allow_failure/', $line) === 1) {
                $violations[] = 'a check is made to succeed anyway: `' . $line . '`';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function code(string $script): array
    {
        return array_values(array_filter(
            array_map(static fn (string $line): string => rtrim((string) preg_replace('/^\s+/', '', $line)), explode("\n", $script)),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }

    /**
     * @param list<int|false> $positions
     * @return list<int|false>
     */
    private static function sorted(array $positions): array
    {
        $copy = $positions;
        sort($copy);

        return $copy;
    }
}
