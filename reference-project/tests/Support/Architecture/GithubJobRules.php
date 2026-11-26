<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * What every job of a workflow file the guard reads must satisfy, whatever it runs: the time it may take, the runner label, the
 * image and the service containers, the pins, the checkout, the commands. The workflow-level rules and the rules about the kit's
 * own jobs are in GithubWorkflowRules and GithubChecksRules.
 */
final readonly class GithubJobRules
{
    private const array CONTAINER_OPTIONS = ['--user', '--cpus', '--memory', '--shm-size'];

    /**
     * @param string       $databaseImage     DATABASE_IMAGE of the kit config, compared as written; empty where the project has no database
     * @param list<string> $writingLines      run lines an owner exception allows to write
     * @param int          $maxTimeoutMinutes the longest `timeout-minutes` a job may ask for
     */
    public function __construct(
        private string $databaseImage,
        private array $writingLines,
        private int $maxTimeoutMinutes,
    ) {
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    public function inWorkflow(array $workflow): array
    {
        $violations = [];
        $jobs = GithubWorkflowReader::jobs($workflow);

        foreach ($jobs as $id => $job) {
            $violations = [...$violations, ...$this->inJob((string) $id, $job)];

            $name = $job['name'] ?? null;

            if (is_string($name) && $name !== $id && (isset($jobs[$name]) || count(array_filter($jobs, static fn (array $other): bool => ($other['name'] ?? null) === $name)) > 1)) {
                $violations[] = 'job `' . $id . '` is named `' . $name . '`, which is the name of another job: two jobs reporting one check name make the required check ambiguous';
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function inJob(string $id, array $job): array
    {
        $where = 'job `' . $id . '`';
        $violations = [];
        $timeout = $job['timeout-minutes'] ?? null;

        if (! is_int($timeout)) {
            $violations[] = $where . ' has no integer timeout-minutes (GitHub\'s default is 360 minutes)';
        } elseif ($timeout < 1 || $timeout > $this->maxTimeoutMinutes) {
            $violations[] = $where . ' has timeout-minutes ' . $timeout . ', above the ' . $this->maxTimeoutMinutes . ' the guard allows: a hung job holds a runner for that long';
        }

        if (isset($job['runs-on']) && preg_match('/latest/', (string) json_encode($job['runs-on'])) === 1) {
            $violations[] = $where . ' runs on a moving label (`*-latest`)';
        }

        if (is_string($job['uses'] ?? null) && ! self::pinned($job['uses'])) {
            $violations[] = $where . ' calls the workflow `' . $job['uses'] . '`, which is not pinned to a full commit sha';
        }

        return [...$violations, ...$this->imageRules($where, $job), ...$this->stepRules($where, $job), ...self::downloadRules($where, $job)];
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

        $options = is_array($container) ? trim((string) ($container['options'] ?? '')) : '';

        foreach ($options === '' ? [] : (preg_split('/\s+(?=-)/', $options) ?: []) as $option) {
            if (! array_any(self::CONTAINER_OPTIONS, static fn (string $allowed): bool => str_starts_with($option, $allowed))) {
                $violations[] = $where . ' passes `' . $option . '` to its container: only ' . implode(', ', self::CONTAINER_OPTIONS) . ' are accepted (a privileged or host-networked container weakens the isolation of the checks)';
            }
        }

        foreach (is_array($job['services'] ?? null) ? $job['services'] : [] as $name => $service) {
            $violations = [...$violations, ...$this->serviceRules($where . ', service `' . $name . '`', $job, (string) $name, is_array($service) ? $service : [])];
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @param array<mixed> $service
     * @return list<string>
     */
    private function serviceRules(string $label, array $job, string $name, array $service): array
    {
        $violations = [];
        $engine = DatabaseEngine::of($this->databaseImage);
        $env = is_array($service['env'] ?? null) ? $service['env'] : [];
        $options = (string) ($service['options'] ?? '');
        $container = $job['container'] ?? null;

        if (($service['image'] ?? '') !== $this->databaseImage) {
            $violations[] = $label . ' image is not DATABASE_IMAGE of kit-config.sh (`' . $this->databaseImage . '`): the tests run on the production engine and major version';
        }

        if ($engine === '' ? $env === [] : ! array_any(DatabaseEngine::passwordKeys($engine), static fn (string $key): bool => isset($env[$key]))) {
            $violations[] = $label . ' has no password variable of its engine (' . ($engine === '' ? 'any' : $engine . ': ' . implode(', ', DatabaseEngine::passwordKeys($engine))) . '): the official image exits at start, and the job fails at "Initialize containers"';
        }

        if (! str_contains($options, '--health-cmd') || ($engine !== '' && ! array_any(DatabaseEngine::healthTools($engine), static fn (string $tool): bool => str_contains($options, $tool)))) {
            $violations[] = $label . ' has no health check of its engine (' . ($engine === '' ? '--health-cmd' : implode(' or ', DatabaseEngine::healthTools($engine))) . '): the job must wait for the database, and a check from another engine never passes';
        }

        if (! is_array($container) && ! isset($service['ports'])) {
            $violations[] = $label . ' publishes no port and the job runs on the runner: it cannot reach the service';
        }

        if (($job['env']['DB_HOST'] ?? null) !== $name) {
            $violations[] = $label . ' is not the DB_HOST of its job: a container job reaches a service by its name';
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

        foreach (GithubWorkflowReader::steps($job) as $step) {
            $uses = is_string($step['uses'] ?? null) ? $step['uses'] : '';

            if ($uses !== '' && ! str_starts_with($uses, './') && ! self::pinned($uses)) {
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
     * A tool downloaded in a run line is checked against its checksum, and never piped into a shell.
     *
     * @param array<mixed> $job
     * @return list<string>
     */
    private static function downloadRules(string $where, array $job): array
    {
        $lines = GithubWorkflowReader::lines($job);
        $downloads = array_filter($lines, static fn (string $line): bool => preg_match('/\b(curl|wget)\b/', $line) === 1);
        $violations = [];

        if ($downloads !== [] && ! array_any($lines, static fn (string $line): bool => preg_match('/\bsha256sum\s+(-c|--check)\b|\bshasum\s+(-a\s*256\s+)?-c\b/', $line) === 1)) {
            $violations[] = $where . ' downloads a file with curl or wget and checks no checksum (`sha256sum -c`): a downloaded binary is pinned by its version and its checksum';
        }

        foreach ($downloads as $line) {
            if (preg_match('/\|\s*(sudo\s+)?(ba)?sh\b/', $line) === 1) {
                $violations[] = $where . ' pipes a download into a shell: `' . $line . '`';
            }
        }

        return $violations;
    }

    private static function pinned(string $uses): bool
    {
        return preg_match('/@([0-9a-f]{40}|sha256:[0-9a-f]{64})$/', $uses) === 1;
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
