<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The rules about a workflow file as a whole: its triggers, its concurrency group, its permissions, its default shell, a
 * placeholder or a secret anywhere in it, and (for a file the owner has the guard harden) the hazards of events that carry secrets.
 */
final readonly class GithubWorkflowRules
{
    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    /**
     * @param list<string> $deployBranches  the branches that deploy: `push.branches` covers each of them
     * @param list<string> $allowedTriggers owner exceptions: triggers that are no check (`workflow_run`, `workflow_dispatch`)
     */
    public function __construct(
        private array $deployBranches,
        private array $allowedTriggers,
    ) {
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    public function inKitWorkflow(array $workflow): array
    {
        return [...$this->triggerRules($workflow), ...self::settingRules($workflow), ...self::contentRules($workflow)];
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    public static function hazards(array $workflow): array
    {
        $triggers = GithubWorkflowReader::triggers($workflow);
        $violations = [];

        if (array_key_exists('pull_request_target', $triggers)) {
            $violations[] = 'pull_request_target is used: it runs with secrets, and the pull request\'s own scripts would run in it';
        }

        if (array_key_exists('workflow_run', $triggers) && array_any(GithubWorkflowReader::jobs($workflow), static fn (array $job): bool => preg_match('/head_sha|head_branch|head\.ref|pull_request/', (string) json_encode(GithubWorkflowReader::checkout($job)['with'] ?? [])) === 1)) {
            $violations[] = 'a workflow_run workflow checks out the code of the pull request that triggered it: it runs with secrets';
        }

        if ((array_key_exists('pull_request', $triggers) || array_key_exists('schedule', $triggers)) && self::secretsIn($workflow) !== []) {
            $violations[] = 'a workflow that a pull request or the schedule starts reads a secret (`' . self::secretsIn($workflow)[0] . '`): a same-repository pull request runs its own copy of the file';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function triggerRules(array $workflow): array
    {
        $on = GithubWorkflowReader::triggers($workflow);
        $violations = [];

        foreach (array_keys($on) as $trigger) {
            if (! in_array($trigger, [...self::EVENTS, ...$this->allowedTriggers], true)) {
                $violations[] = 'the trigger `' . $trigger . '` is not one of pull_request, push, schedule: a check nobody has to run is a report (an owner exception is the allowedTriggers argument)';
            }
        }

        foreach (self::EVENTS as $event) {
            if (! array_key_exists($event, $on)) {
                $violations[] = 'the workflow has no `' . $event . '` trigger';
            }
        }

        return [...$violations, ...self::pullRequestRules($on), ...$this->pushRules($on), ...self::scheduleRules($on)];
    }

    /**
     * @param array<mixed> $on
     * @return list<string>
     */
    private static function pullRequestRules(array $on): array
    {
        $pullRequest = is_array($on['pull_request'] ?? null) ? $on['pull_request'] : [];
        $violations = [];

        foreach (['paths', 'paths-ignore', 'branches', 'branches-ignore'] as $filter) {
            if (isset($pullRequest[$filter])) {
                $violations[] = 'pull_request has `' . $filter . '`: the checks of a pull request have no filter (a pull request that targets a parent branch of a stack matches no `branches` list either)';
            }
        }

        $types = $pullRequest['types'] ?? ['opened', 'synchronize', 'reopened'];

        foreach (['opened', 'synchronize', 'reopened', 'edited'] as $type) {
            if (! in_array($type, is_array($types) ? $types : [], true)) {
                $violations[] = 'pull_request.types lacks `' . $type . '`: `edited` re-runs the checks after a retarget, the others are the default';
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $on
     * @return list<string>
     */
    private function pushRules(array $on): array
    {
        $push = is_array($on['push'] ?? null) ? $on['push'] : [];
        $violations = [];

        foreach ($this->deployBranches as $branch) {
            if (! in_array($branch, is_array($push['branches'] ?? null) ? $push['branches'] : [], true)) {
                $violations[] = 'push.branches does not list the deploying branch ' . $branch;
            }
        }

        if (isset($push['paths']) || isset($push['paths-ignore'])) {
            $violations[] = 'push has a path filter: the checks of a deploying branch have none';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $on
     * @return list<string>
     */
    private static function scheduleRules(array $on): array
    {
        $schedule = $on['schedule'] ?? [];

        if (! is_array($schedule) || ! array_any($schedule, static fn (mixed $entry): bool => is_array($entry) && is_string($entry['cron'] ?? null) && trim($entry['cron']) !== '')) {
            return ['the schedule trigger has no cron'];
        }

        return [];
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private static function settingRules(array $workflow): array
    {
        $violations = [];
        $concurrency = $workflow['concurrency'] ?? null;
        $group = is_array($concurrency) ? (string) ($concurrency['group'] ?? '') : '';

        if (! is_array($concurrency) || $group === '') {
            $violations[] = 'the workflow has no concurrency group: every push to a pull request leaves its older run going';
        } elseif (! str_contains($group, 'github.event_name') || ! str_contains($group, 'pull_request.number')) {
            $violations[] = 'the concurrency group `' . $group . '` does not hold `github.event_name` and the pull request number: one constant group cancels every other pull request, and a group on the ref alone makes a push run and a pull request run cancel each other';
        } elseif (GithubWorkflowReader::cancelsEveryEvent($concurrency['cancel-in-progress'] ?? false)) {
            $violations[] = 'concurrency cancels runs of every event: only a pull request may cancel its older run, a push to a deploying branch is never cancelled';
        }

        if (! isset($workflow['permissions'])) {
            $violations[] = 'the workflow has no top-level `permissions`: set contents: read';
        }

        if (GithubWorkflowReader::writes($workflow['permissions'] ?? null) || array_any(GithubWorkflowReader::jobs($workflow), static fn (array $job): bool => GithubWorkflowReader::writes($job['permissions'] ?? null))) {
            $violations[] = 'a permission is `write`: a check job reads (contents: read, packages: read for a private image, and actions: read where the dashboard asks the run history)';
        }

        if (($workflow['defaults']['run']['shell'] ?? null) !== 'bash') {
            $violations[] = 'the workflow does not set `defaults.run.shell: bash`: the default shell has no pipefail, so a failing command in a pipe passes';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private static function contentRules(array $workflow): array
    {
        $violations = [];

        foreach (GithubWorkflowReader::strings($workflow) as $text) {
            if (preg_match(CiSafetyGuard::PLACEHOLDER, $text, $match) === 1) {
                $violations[] = 'the workflow still holds a placeholder (`' . $match[0] . '`): replace it with the project\'s value, in a run line, the cron, an image or a setting';
            }
        }

        if (self::secretsIn($workflow) !== []) {
            $violations[] = 'the workflow reads a secret (`' . self::secretsIn($workflow)[0] . '`): the jobs run the pull request\'s own scripts, so no secret belongs in this file (the token of a job is `github.token`)';
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string> the `secrets.X` references other than the workflow token
     */
    private static function secretsIn(array $workflow): array
    {
        $found = [];

        foreach (GithubWorkflowReader::strings($workflow) as $text) {
            preg_match_all('/secrets\.(?!GITHUB_TOKEN\b)[A-Za-z0-9_]+|secrets\[/', $text, $match);
            array_push($found, ...$match[0]);
        }

        return array_values(array_unique($found));
    }
}
