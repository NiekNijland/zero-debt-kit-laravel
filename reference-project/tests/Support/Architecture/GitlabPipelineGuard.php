<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Symfony\Component\Yaml\Yaml;

/**
 * The GitLab CI form of the CI contract (04-guards.md C4): the same points as CiContractGuard, read from `workflow:rules`, the
 * jobs, `parallel:matrix` and the `rules` of each job of `.gitlab-ci.yml` (ci/gitlab-ci.yml is the skeleton).
 */
final readonly class GitlabPipelineGuard
{
    private const array SOURCES = ['merge_request_event', 'push', 'schedule'];

    private const string EQUALS = '=' . '=';

    /**
     * @param list<string> $deployBranches the branches that deploy; the first one may be written as `$CI_DEFAULT_BRANCH`
     * @param string       $checksScript   the project's checks script, whose PARTS table names the parts
     * @param list<string> $writingLines   run lines an owner exception allows to write
     */
    public function __construct(
        private string $pipelineFile,
        private string $checksScript,
        private array $deployBranches,
        private string $backendTestCommand,
        private string $databaseImage,
        private array $writingLines = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        if (! is_file($this->pipelineFile)) {
            return ['the pipeline file does not exist: copy ci/gitlab-ci.yml to .gitlab-ci.yml'];
        }

        if (! is_file($this->checksScript)) {
            return ['the checks script ' . $this->checksScript . ' does not exist: the parts of the checks step are read from it'];
        }

        return $this->inPipeline((string) file_get_contents($this->pipelineFile), (string) file_get_contents($this->checksScript));
    }

    /**
     * @return list<string>
     */
    public function inPipeline(string $yaml, string $checksText): array
    {
        $pipeline = Yaml::parse($yaml);
        $pipeline = is_array($pipeline) ? $pipeline : [];
        $jobs = self::jobs($pipeline);
        $parts = ChecksParts::of($checksText);

        return array_values(array_unique([
            ...$this->workflowRules($pipeline),
            ...$this->checksRules($jobs, $parts),
            ...$this->mainRules($jobs, $parts),
            ...$this->scheduledRules($jobs),
            ...$this->testRules($jobs),
            ...$this->jobRules($pipeline, $jobs),
        ]));
    }

    /**
     * @param array<mixed> $pipeline
     * @return list<string>
     */
    private function workflowRules(array $pipeline): array
    {
        $rules = is_array($pipeline['workflow']['rules'] ?? null) ? $pipeline['workflow']['rules'] : [];
        $violations = [];

        if ($rules === []) {
            $violations[] = 'the pipeline has no workflow:rules: say which sources start a pipeline';
        }

        foreach ($rules as $rule) {
            $condition = is_array($rule) ? (string) ($rule['if'] ?? '') : '';

            if (str_contains($condition, '$CI_COMMIT_BRANCH ' . self::EQUALS) && ! str_contains($condition, self::source('push'))) {
                $violations[] = 'a workflow rule on a branch lets every pipeline source through (a manual run, the API, a trigger): `' . $condition . '` needs `' . self::source('push') . '`';
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @param list<string> $parts
     * @return list<string>
     */
    private function checksRules(array $jobs, array $parts): array
    {
        $carrying = array_filter($jobs, static fn (array $job): bool => in_array(CiContractGuard::CHECKS . ' --part $PART', self::lines($job), true));

        if ($carrying === []) {
            return ['no job runs exactly `' . CiContractGuard::CHECKS . ' --part $PART`: a merge request pipeline without it is accepted by "Pipelines must succeed"'];
        }

        $violations = [];

        foreach ($carrying as $id => $job) {
            if (self::rules($job) !== [self::source('merge_request_event')]) {
                $violations[] = 'job `' . $id . '` has another rule than `' . self::source('merge_request_event') . '`: a changes filter or a condition narrows the checks';
            }

            if (! self::matrixIs($job, $parts)) {
                $violations[] = 'the matrix of job `' . $id . '` is not exactly the parts of PARTS (' . implode(', ', $parts) . ')';
            }

            if (($job['variables']['CHECK_BASE'] ?? null) !== '$CI_MERGE_REQUEST_TARGET_BRANCH_NAME') {
                $violations[] = 'job `' . $id . '` does not set CHECK_BASE from `$CI_MERGE_REQUEST_TARGET_BRANCH_NAME`';
            }

            if (isset($job['variables']['CHECK_HEAD']) && $job['variables']['CHECK_HEAD'] !== '$CI_COMMIT_SHA') {
                $violations[] = 'job `' . $id . '` sets CHECK_HEAD to something else than the commit it checks out (`$CI_COMMIT_SHA`)';
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @param list<string> $parts
     * @return list<string>
     */
    private function mainRules(array $jobs, array $parts): array
    {
        $carrying = array_filter($jobs, static fn (array $job): bool => in_array(CiContractGuard::CHECKS . ' --main --part $PART', self::lines($job), true));

        if ($carrying === []) {
            return ['no job runs exactly `' . CiContractGuard::CHECKS . ' --main --part $PART`: the checks of a deploying branch are missing'];
        }

        $violations = [];

        foreach ($carrying as $id => $job) {
            $rule = implode(' ', self::rules($job));

            if (! str_contains($rule, self::source('push'))) {
                $violations[] = 'job `' . $id . '` is not limited to the push source: a manual run on the main branch would start a partial pipeline';
            }

            foreach ($this->deployBranches as $index => $branch) {
                if (! str_contains($rule, '"' . $branch . '"') && ! ($index === 0 && str_contains($rule, '$CI_DEFAULT_BRANCH'))) {
                    $violations[] = 'job `' . $id . '` does not run on the deploying branch ' . $branch;
                }
            }

            if (! self::matrixIs($job, $parts)) {
                $violations[] = 'the matrix of job `' . $id . '` is not exactly the parts of PARTS (' . implode(', ', $parts) . ')';
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @return list<string>
     */
    private function scheduledRules(array $jobs): array
    {
        $carrying = array_filter($jobs, static fn (array $job): bool => in_array(CiContractGuard::CHECKS . ' --scheduled', self::lines($job), true));

        if ($carrying === []) {
            return ['no job runs exactly `' . CiContractGuard::CHECKS . ' --scheduled`'];
        }

        return array_values(array_map(
            static fn (string $id): string => 'job `' . $id . '` runs the scheduled checks on another rule than `' . self::source('schedule') . '`',
            array_keys(array_filter($carrying, static fn (array $job): bool => self::rules($job) !== [self::source('schedule')])),
        ));
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @return list<string>
     */
    private function testRules(array $jobs): array
    {
        $count = array_fill_keys(self::SOURCES, 0);

        foreach ($jobs as $job) {
            if (in_array($this->backendTestCommand, self::lines($job), true)) {
                foreach (self::sources(self::rules($job)) as $source) {
                    $count[$source]++;
                }
            }
        }

        return array_values(array_map(
            static fn (string $source): string => 'the backend tests run ' . $count[$source] . ' times on one `' . $source . '` pipeline: a branch runs each suite once before its deploy',
            array_keys(array_filter($count, static fn (int $times): bool => $times > 1)),
        ));
    }

    /**
     * @param array<mixed> $pipeline
     * @param array<string, array<mixed>> $jobs
     * @return list<string>
     */
    private function jobRules(array $pipeline, array $jobs): array
    {
        $variables = is_array($pipeline['variables'] ?? null) ? $pipeline['variables'] : [];
        $violations = self::pinnedImage($pipeline['image'] ?? null) ? [] : ['the pipeline image is not pinned by digest (`@sha256:`)'];

        if ((string) ($variables['GIT_DEPTH'] ?? '') !== '0') {
            $violations[] = 'GIT_DEPTH is not 0: a shallow clone stops working after a few dozen commits';
        }

        foreach ($jobs as $id => $job) {
            $where = 'job `' . $id . '`';
            $own = is_array($job['variables'] ?? null) ? $job['variables'] : [];

            if (! isset($job['timeout'])) {
                $violations[] = $where . ' has no timeout (the default is the project setting)';
            }

            if (isset($job['image']) && ! self::pinnedImage($job['image'])) {
                $violations[] = $where . ' uses an image that is not pinned by digest';
            }

            if ((string) ($own['GIT_DEPTH'] ?? '0') !== '0' || ($own['GIT_STRATEGY'] ?? '') === 'none') {
                $violations[] = $where . ' does not clone with full depth';
            }

            if (! empty($job['allow_failure']) || ($job['when'] ?? '') === 'manual' || str_contains((string) json_encode($job['rules'] ?? []), '"when":"manual"')) {
                $violations[] = $where . ' may fail or waits for a click (`allow_failure`, `when: manual`): a check nobody has to run is a report';
            }

            $violations = [...$violations, ...$this->serviceRules($where, $job, [...$variables, ...$own]), ...(new CiSafetyGuard('', $this->writingLines))->inCommands(self::lines($job))];
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @param array<mixed> $variables
     * @return list<string>
     */
    private function serviceRules(string $where, array $job, array $variables): array
    {
        $violations = [];
        $engine = DatabaseEngine::of($this->databaseImage);
        $keys = $engine === '' ? array_filter(array_keys($variables), static fn (int|string $key): bool => str_contains((string) $key, 'PASSWORD')) : array_filter(DatabaseEngine::passwordKeys($engine), static fn (string $key): bool => isset($variables[$key]));

        foreach (is_array($job['services'] ?? null) ? $job['services'] : [] as $service) {
            $image = is_array($service) ? $service['name'] ?? '' : $service;

            if ($image !== $this->databaseImage) {
                $violations[] = $where . ' has a service image that is not DATABASE_IMAGE of kit-config.sh (`' . $this->databaseImage . '`)';
            }

            if ($keys === []) {
                $violations[] = $where . ' has a database service and no password variable of its engine (' . ($engine === '' ? 'any' : $engine . ': ' . implode(', ', DatabaseEngine::passwordKeys($engine))) . '): the official image exits without one';
            }
        }

        return $violations;
    }

    private static function pinnedImage(mixed $image): bool
    {
        $name = is_array($image) ? $image['name'] ?? '' : $image;

        return is_string($name) && str_contains($name, '@sha256:');
    }

    /**
     * @param array<mixed> $job
     * @param list<string> $parts
     */
    private static function matrixIs(array $job, array $parts): bool
    {
        $matrix = $job['parallel']['matrix'][0]['PART'] ?? null;

        return is_array($matrix) && array_diff($parts, $matrix) === [] && array_diff($matrix, $parts) === [];
    }

    /**
     * @param array<mixed> $pipeline
     * @return array<string, array<mixed>>
     */
    private static function jobs(array $pipeline): array
    {
        return array_filter($pipeline, static fn (mixed $job, int|string $key): bool => is_array($job) && isset($job['script']) && is_string($key), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param array<mixed> $job
     * @return list<string> the `if` of each rule
     */
    private static function rules(array $job): array
    {
        return array_values(array_map(static fn (mixed $rule): string => is_array($rule) ? (string) ($rule['if'] ?? '') . (isset($rule['changes']) || isset($rule['exists']) ? ' [path filter]' : '') : '', is_array($job['rules'] ?? null) ? $job['rules'] : []));
    }

    /**
     * @param list<string> $rules
     * @return list<string>
     */
    private static function sources(array $rules): array
    {
        $text = implode(' ', $rules);

        if ($rules === [] || preg_match_all('/\$CI_PIPELINE_SOURCE \={2} "(\w+)"/', $text, $match) < 1) {
            return str_contains($text, '$CI_PIPELINE_SOURCE ' . '!' . '= "schedule"') ? ['merge_request_event', 'push'] : self::SOURCES;
        }

        return $match[1];
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private static function lines(array $job): array
    {
        $lines = [];

        foreach (['before_script', 'script', 'after_script'] as $key) {
            foreach (is_array($job[$key] ?? null) ? $job[$key] : [] as $line) {
                if (is_string($line)) {
                    array_push($lines, ...array_values(array_filter(array_map('trim', explode("\n", $line)), static fn (string $part): bool => $part !== '')));
                }
            }
        }

        return $lines;
    }

    private static function source(string $name): string
    {
        return '$CI_PIPELINE_SOURCE ' . self::EQUALS . ' "' . $name . '"';
    }
}
