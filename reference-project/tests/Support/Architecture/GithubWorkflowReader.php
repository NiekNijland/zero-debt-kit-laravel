<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * What the GitHub guard reads out of a parsed workflow: the triggers, the jobs, the lines of the `run:` steps and the events a job
 * runs on. No rule is in here.
 */
final class GithubWorkflowReader
{
    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    public static function cancelsEveryEvent(mixed $cancel): bool
    {
        return $cancel === true || (is_string($cancel) && ! str_contains($cancel, 'pull_request'));
    }

    public static function writes(mixed $permissions): bool
    {
        return is_string($permissions) ? str_starts_with($permissions, 'write') : (is_array($permissions) && in_array('write', $permissions, true));
    }

    /**
     * @param array<mixed> $workflow
     * @return array<mixed>
     */
    public static function triggers(array $workflow): array
    {
        $on = $workflow['on'] ?? $workflow[true] ?? [];

        if (is_string($on)) {
            return [$on => null];
        }

        return is_array($on) && array_is_list($on) ? array_fill_keys($on, null) : (is_array($on) ? $on : []);
    }

    /**
     * @param array<mixed> $workflow
     * @return array<string, array<mixed>>
     */
    public static function jobs(array $workflow): array
    {
        return array_filter(is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [], 'is_array');
    }

    /**
     * @param array<mixed> $workflow
     */
    public static function runsChecks(array $workflow): bool
    {
        return array_any(self::jobs($workflow), static fn (array $job): bool => array_any(self::lines($job), static fn (string $line): bool => str_starts_with($line, CiContractGuard::CHECKS)));
    }

    /**
     * @param array<mixed> $job
     */
    public static function condition(array $job): string
    {
        return trim((string) ($job['if'] ?? ''));
    }

    /**
     * The events a job runs on, read from its `if`: an `if` this reader cannot read counts as every event.
     *
     * @return list<string>
     */
    public static function events(mixed $condition): array
    {
        if (! is_string($condition)) {
            return self::EVENTS;
        }

        if (preg_match('/^github\.event_name == \'(\w+)\'$/', trim($condition), $match) === 1) {
            return [$match[1]];
        }

        if (preg_match('/^github\.event_name != \'(\w+)\'$/', trim($condition), $match) === 1) {
            return array_values(array_diff(self::EVENTS, [$match[1]]));
        }

        return self::EVENTS;
    }

    /**
     * Every line of every `run:` of a job, a block split on its newlines.
     *
     * @param array<mixed> $job
     * @return list<string>
     */
    public static function lines(array $job): array
    {
        $lines = [];

        foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
            if (is_array($step) && is_string($step['run'] ?? null)) {
                array_push($lines, ...array_values(array_filter(array_map('trim', explode("\n", $step['run'])), static fn (string $line): bool => $line !== '')));
            }
        }

        return $lines;
    }

    /**
     * @param array<mixed> $job
     * @return array<mixed>
     */
    public static function checkout(array $job): array
    {
        foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
            if (is_array($step) && str_starts_with((string) ($step['uses'] ?? ''), 'actions/checkout@')) {
                return $step;
            }
        }

        return [];
    }
}
