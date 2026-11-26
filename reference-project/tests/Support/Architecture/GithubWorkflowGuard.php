<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Symfony\Component\Yaml\Yaml;

/**
 * The GitHub Actions form of the CI contract (04-guards.md C4): the same points as CiContractGuard, read from `on:`, the jobs, the
 * matrix and the steps of every file under `.github/workflows`. The pipeline is a directory here, not a file: the kit's jobs sit in
 * a file of their own (ci/github-actions.yml), and the other workflows are read for what they must not do.
 */
final readonly class GithubWorkflowGuard
{
    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    public const string PART_LINE = 'bash scripts/checks.sh --part ${{ matrix.part }}';

    public const string MAIN_LINE = 'bash scripts/checks.sh --main --part ${{ matrix.part }}';

    /**
     * @param list<string> $deployBranches   the branches that deploy: `push.branches` of the kit's file covers each of them
     * @param list<string> $parts            the parts of the checks step (`scripts/checks.sh --list-parts`)
     * @param string       $databaseImage    DATABASE_IMAGE of scripts/kit-config.sh, compared as written (exempt from the digest rule)
     * @param list<string> $allowedTriggers  owner exceptions: triggers of a deploy workflow that are no check (`workflow_run`, `workflow_dispatch`)
     * @param list<string> $writingLines     run lines an owner exception allows to write
     */
    public function __construct(
        private string $workflowDirectory,
        private array $deployBranches,
        private string $backendTestCommand,
        private array $parts = ['gate', 'static', 'guards', 'repo'],
        private string $databaseImage = 'postgres:17',
        private array $allowedTriggers = [],
        private array $writingLines = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $files = glob($this->workflowDirectory . '/*.{yml,yaml}', GLOB_BRACE) ?: [];

        if ($files === []) {
            return ['no workflow under ' . $this->workflowDirectory . ': copy ci/github-actions.yml to .github/workflows/kit-checks.yml'];
        }

        return $this->inWorkflows(array_combine(array_map('basename', $files), array_map(static fn (string $file): string => (string) file_get_contents($file), $files)));
    }

    /**
     * @param array<string, string> $yamlByFile
     * @return list<string>
     */
    public function inWorkflows(array $yamlByFile): array
    {
        $violations = [];
        $kitFiles = 0;
        $testsPerEvent = array_fill_keys(self::EVENTS, 0);

        foreach ($yamlByFile as $name => $yaml) {
            $workflow = Yaml::parse($yaml);
            $workflow = is_array($workflow) ? $workflow : [];
            $isKit = GithubWorkflowReader::runsChecks($workflow);
            $kitFiles += $isKit ? 1 : 0;

            $violations = [...$violations, ...array_map(static fn (string $line): string => $name . ': ' . $line, $this->inWorkflow($workflow, $isKit))];

            foreach (GithubWorkflowReader::jobs($workflow) as $job) {
                if (in_array($this->backendTestCommand, GithubWorkflowReader::lines($job), true)) {
                    foreach (GithubWorkflowReader::events($job['if'] ?? null) as $event) {
                        $testsPerEvent[$event]++;
                    }
                }
            }
        }

        if ($kitFiles === 0) {
            $violations[] = 'no workflow runs `bash scripts/checks.sh`: the checks job is missing';
        }

        foreach ($testsPerEvent as $event => $count) {
            if ($count > 1) {
                $violations[] = 'the backend tests run ' . $count . ' times on one ' . $event . ' event (two jobs or two workflows match it): a branch runs each suite once before its deploy';
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function inWorkflow(array $workflow, bool $isKit): array
    {
        $violations = [...$this->jobRules($workflow), ...(array_key_exists('pull_request_target', GithubWorkflowReader::triggers($workflow)) ? ['pull_request_target is used: it runs with secrets, and the pull request\'s own scripts would run in it'] : [])];

        if (! $isKit) {
            return $violations;
        }

        return [...$violations, ...$this->triggerRules($workflow), ...$this->runRules($workflow), ...$this->settingRules($workflow)];
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

        $pullRequest = is_array($on['pull_request'] ?? null) ? $on['pull_request'] : [];

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

        $push = is_array($on['push'] ?? null) ? $on['push'] : [];

        foreach ($this->deployBranches as $branch) {
            if (! in_array($branch, is_array($push['branches'] ?? null) ? $push['branches'] : [], true)) {
                $violations[] = 'push.branches does not list the deploying branch ' . $branch;
            }
        }

        if (isset($push['paths']) || isset($push['paths-ignore'])) {
            $violations[] = 'push has a path filter: the checks of a deploying branch have none';
        }

        $schedule = $on['schedule'] ?? [];

        if (! is_array($schedule) || ! array_any($schedule, static fn (mixed $entry): bool => is_array($entry) && is_string($entry['cron'] ?? null))) {
            $violations[] = 'the schedule trigger has no cron';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function runRules(array $workflow): array
    {
        $jobs = GithubWorkflowReader::jobs($workflow);
        $violations = [];

        foreach ([self::PART_LINE => 'pull_request', self::MAIN_LINE => 'push'] as $line => $event) {
            $carrying = array_filter($jobs, static fn (array $job): bool => in_array($line, GithubWorkflowReader::lines($job), true));

            if ($carrying === []) {
                $violations[] = 'no job runs exactly `' . $line . '`: the ' . ($event === 'push' ? 'checks of a deploying branch' : 'checks of a pull request') . ' are missing';
            }

            foreach ($carrying as $id => $job) {
                $violations = [...$violations, ...$this->checksJobRules((string) $id, $job, $event)];
            }
        }

        $scheduled = array_filter($jobs, static fn (array $job): bool => in_array(CiContractGuard::CHECKS . ' --scheduled', GithubWorkflowReader::lines($job), true));

        if ($scheduled === []) {
            $violations[] = 'no job runs exactly `' . CiContractGuard::CHECKS . ' --scheduled`';
        }

        foreach ($scheduled as $id => $job) {
            if (GithubWorkflowReader::condition($job) !== "github.event_name == 'schedule'") {
                $violations[] = 'job `' . $id . '` runs the scheduled checks on another condition than the schedule event';
            }
        }

        if (! array_any($jobs, static fn (array $job): bool => isset($job['needs']) && str_contains((string) ($job['if'] ?? ''), 'always()') && in_array('test "$RESULT" = success', GithubWorkflowReader::lines($job), true))) {
            $violations[] = 'no aggregate job: a job with `needs`, `if: always()` and the step `test "$RESULT" = success` is the one required status check that follows PARTS';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function checksJobRules(string $id, array $job, string $event): array
    {
        $violations = [];

        if (GithubWorkflowReader::condition($job) !== "github.event_name == '" . $event . "'") {
            $violations[] = 'job `' . $id . '` has another condition than `github.event_name == \'' . $event . '\'`: a condition narrows the checks';
        }

        $matrix = $job['strategy']['matrix']['part'] ?? null;

        if (! is_array($matrix) || array_diff($this->parts, $matrix) !== [] || array_diff($matrix, $this->parts) !== []) {
            $violations[] = 'the matrix of job `' . $id . '` is not exactly the parts of PARTS (' . implode(', ', $this->parts) . ')';
        }

        foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
            if (is_array($step) && in_array(is_string($step['run'] ?? null) ? $step['run'] : '', [self::PART_LINE, self::MAIN_LINE], true) && isset($step['if'])) {
                $violations[] = 'the checks step of job `' . $id . '` has a condition of its own';
            }
        }

        $env = [...(is_array($job['env'] ?? null) ? $job['env'] : [])];

        if ($event === 'pull_request' && ($env['CHECK_BASE'] ?? null) !== '${{ github.base_ref }}') {
            $violations[] = 'job `' . $id . '` does not set CHECK_BASE from `${{ github.base_ref }}`';
        }

        if (isset($env['CHECK_HEAD']) && (GithubWorkflowReader::checkout($job)['with']['ref'] ?? null) !== $env['CHECK_HEAD']) {
            $violations[] = 'job `' . $id . '` sets CHECK_HEAD but does not check out that revision: GitHub merges first, and the checks read HEAD';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function settingRules(array $workflow): array
    {
        $violations = [];
        $concurrency = $workflow['concurrency'] ?? null;

        if (! is_array($concurrency) || ! isset($concurrency['group'])) {
            $violations[] = 'the workflow has no concurrency group: every push to a pull request leaves its older run going';
        } elseif (GithubWorkflowReader::cancelsEveryEvent($concurrency['cancel-in-progress'] ?? false)) {
            $violations[] = 'concurrency cancels runs of every event: only a pull request may cancel its older run, a push to a deploying branch is never cancelled';
        }

        if (! isset($workflow['permissions'])) {
            $violations[] = 'the workflow has no top-level `permissions`: set contents: read';
        }

        if (GithubWorkflowReader::writes($workflow['permissions'] ?? null) || array_any(GithubWorkflowReader::jobs($workflow), static fn (array $job): bool => GithubWorkflowReader::writes($job['permissions'] ?? null))) {
            $violations[] = 'a permission is `write`: a check job reads (contents: read, and actions: read where the dashboard asks the run history)';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function jobRules(array $workflow): array
    {
        $violations = [];

        foreach (GithubWorkflowReader::jobs($workflow) as $id => $job) {
            $where = 'job `' . $id . '`';

            if (! isset($job['timeout-minutes']) || ! is_int($job['timeout-minutes'])) {
                $violations[] = $where . ' has no integer timeout-minutes (GitHub\'s default is 360 minutes)';
            }

            if (isset($job['runs-on']) && preg_match('/latest/', (string) json_encode($job['runs-on'])) === 1) {
                $violations[] = $where . ' runs on a moving label (`*-latest`)';
            }

            $violations = [...$violations, ...$this->imageRules($where, $job), ...$this->stepRules($where, $job)];
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function imageRules(string $where, array $job): array
    {
        $violations = [];
        $container = $job['container'] ?? null;
        $image = is_array($container) ? $container['image'] ?? null : $container;

        if (is_string($image) && ! str_contains($image, '@sha256:')) {
            $violations[] = $where . ' runs in a container that is not pinned by digest (`@sha256:`)';
        }

        foreach (is_array($job['services'] ?? null) ? $job['services'] : [] as $name => $service) {
            $service = is_array($service) ? $service : [];
            $serviceImage = $service['image'] ?? '';
            $label = $where . ', service `' . $name . '`';

            if ($serviceImage !== $this->databaseImage) {
                $violations[] = $label . ' image is not DATABASE_IMAGE of kit-config.sh (`' . $this->databaseImage . '`): the tests run on the production engine and major version';
            }

            if (! isset($service['env']) || ! str_contains((string) ($service['options'] ?? ''), '--health-cmd')) {
                $violations[] = $label . ' has no env or no health check: the official image exits without a password, and the job must wait for the database';
            }

            if (! is_array($container) && ! isset($service['ports'])) {
                $violations[] = $label . ' publishes no port and the job runs on the runner: it cannot reach the service';
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function stepRules(string $where, array $job): array
    {
        $violations = [];

        if (! empty($job['continue-on-error']) || (isset($job['if']) && trim((string) $job['if']) === 'false')) {
            $violations[] = $where . ' may fail or is switched off (`continue-on-error` or `if: false`): nothing is allowed to fail';
        }

        foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
            $step = is_array($step) ? $step : [];
            $uses = is_string($step['uses'] ?? null) ? $step['uses'] : '';

            if ($uses !== '' && ! str_starts_with($uses, './') && preg_match('/@([0-9a-f]{40}|sha256:[0-9a-f]{64})$/', $uses) !== 1) {
                $violations[] = $where . ' uses `' . $uses . '`, which is not pinned to a full commit sha';
            }

            if (str_starts_with($uses, 'actions/checkout@')) {
                $violations = [...$violations, ...self::checkoutRules($where, $step)];
            }

            if (! empty($step['continue-on-error']) || (isset($step['if']) && in_array(trim((string) $step['if']), ['false', 'always()'], true))) {
                $violations[] = $where . ', a step may fail or is forced on (`continue-on-error`, `if: false`, `if: always()`)';
            }
        }

        return [...$violations, ...(new CiSafetyGuard('', $this->writingLines))->inCommands(GithubWorkflowReader::lines($job))];
    }

    /**
     * @param array<mixed> $step
     * @return list<string>
     */
    private static function checkoutRules(string $where, array $step): array
    {
        $violations = [];

        if (($step['with']['fetch-depth'] ?? null) !== 0) {
            $violations[] = $where . ' checks out with a shallow clone: set `fetch-depth: 0` (a history-reading task stops working after a few dozen commits)';
        }

        if (($step['with']['persist-credentials'] ?? true) === false) {
            $violations[] = $where . ' checks out with `persist-credentials: false`: scripts/checks.sh fetches the base with those credentials';
        }

        return $violations;
    }
}
