<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Symfony\Component\Yaml\Yaml;

final readonly class CiContractGuard
{
    public const string CHECKS = 'bash scripts/checks.sh';

    /**
     * @param list<string> $deployBranches the branches that deploy: each one runs the checks, the tests and the smoke run
     * @param string       $checksScript   the project's checks script, whose PARTS table names the parts: each has its own step
     */
    public function __construct(
        private string $pipelineFile,
        private string $checksScript,
        private array $deployBranches,
        private string $backendTestCommand,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        if (! is_file($this->pipelineFile)) {
            return ['the pipeline file does not exist: copy the skeleton of the kit that fits the host'];
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
        $runs = is_array($pipeline) && is_array($pipeline['pipelines'] ?? null) ? $pipeline['pipelines'] : [];
        $clone = is_array($pipeline) && is_array($pipeline['clone'] ?? null) ? $pipeline['clone'] : [];
        $image = is_array($pipeline) ? $pipeline['image'] ?? null : null;

        return [...$this->runViolations($runs, $clone, ChecksParts::of($checksText)), ...$this->stepViolations($runs, $clone, $image)];
    }

    /**
     * @param array<mixed> $runs
     * @param array<mixed> $clone
     * @param list<string> $parts
     * @return list<string>
     */
    private function runViolations(array $runs, array $clone, array $parts): array
    {
        $violations = [];
        $pullRequests = self::section($runs, 'pull-requests');
        $checked = [];

        foreach ($parts as $part) {
            $steps = array_filter($pullRequests['**'] ?? [], static fn (array $step): bool => in_array(self::CHECKS . ' --part ' . $part, self::lines($step), true) && ! isset($step['condition']));

            if ($steps === []) {
                $violations[] = 'pull-requests has no step on the pattern ** that runs exactly `' . self::CHECKS . ' --part ' . $part . '` without a condition: a part without a step, a path condition or a changed command narrows the checks of a pull request';
            }

            $checked = [...$checked, ...$steps];
        }

        $branches = self::section($runs, 'branches');

        foreach ($this->deployBranches as $branch) {
            $covering = array_filter($branches, static fn (array $steps, string $key): bool => self::covers($key, $branch), ARRAY_FILTER_USE_BOTH);
            $steps = array_merge(...array_values($covering) ?: [[]]);
            $tests = array_filter($steps, fn (array $step): bool => in_array($this->backendTestCommand, self::lines($step), true));

            foreach ($parts as $part) {
                if (! array_any($steps, static fn (array $step): bool => in_array(self::CHECKS . ' --main --part ' . $part, self::lines($step), true))) {
                    $violations[] = 'the deploying branch ' . $branch . ' has no step that runs exactly `' . self::CHECKS . ' --main --part ' . $part . '`';
                }
            }

            if (count($tests) > 1) {
                $violations[] = 'the deploying branch ' . $branch . ' runs the backend tests ' . count($tests) . ' times on one push: two branch patterns cover it';
            }
        }

        $scheduled = self::section($runs, 'custom')['scheduled'] ?? [];

        if (! array_any($scheduled, static fn (array $step): bool => in_array(self::CHECKS . ' --scheduled', self::lines($step), true))) {
            $violations[] = 'the custom pipeline `scheduled` does not run exactly `' . self::CHECKS . ' --scheduled`';
        }

        foreach ([...$checked, ...$scheduled] as $step) {
            if (! self::fullClone($step, $clone)) {
                $violations[] = 'the step `' . self::name($step) . '` reads history and does not clone with full depth: a shallow clone stops working after a few dozen commits';
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $runs
     * @param array<mixed> $clone
     * @return list<string>
     */
    private function stepViolations(array $runs, array $clone, mixed $image): array
    {
        $violations = [];

        if (! self::pinned($image)) {
            $violations[] = 'the pipeline image is not pinned by digest (`@sha256:`)';
        }

        foreach (['pull-requests', 'branches', 'custom'] as $kind) {
            foreach (self::section($runs, $kind) as $key => $steps) {
                foreach ($steps as $step) {
                    $where = $kind . ' ' . $key . ', step `' . self::name($step) . '`';
                    $violations = [...$violations, ...self::stepRules($step, $where, $image)];
                }
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $step
     * @return list<string>
     */
    private static function stepRules(array $step, string $where, mixed $image): array
    {
        $violations = [];

        if (! isset($step['max-time'])) {
            $violations[] = $where . ' has no max-time';
        }

        if (($step['trigger'] ?? 'automatic') === 'manual') {
            $violations[] = $where . ' has a manual trigger: a check nobody has to run is a report';
        }

        if (isset($step['image']) && ! self::pinned($step['image'])) {
            $violations[] = $where . ' uses an image that is not pinned by digest';
        }

        return $violations;
    }

    private static function pinned(mixed $image): bool
    {
        $name = is_array($image) ? $image['name'] ?? '' : $image;

        return is_string($name) && str_contains($name, '@sha256:');
    }

    /**
     * @param array<mixed> $step
     * @param array<mixed> $clone
     */
    private static function fullClone(array $step, array $clone): bool
    {
        $own = is_array($step['clone'] ?? null) ? $step['clone'] : $clone;

        return ($own['depth'] ?? null) === 'full';
    }

    private static function covers(string $key, string $branch): bool
    {
        if ($key === '**' || $key === $branch) {
            return true;
        }

        return preg_match('/^\{(.+)\}$/', $key, $match) === 1 && in_array($branch, explode(',', $match[1]), true);
    }

    /**
     * @param array<mixed> $runs
     * @return array<string, list<array<mixed>>>
     */
    private static function section(array $runs, string $kind): array
    {
        $section = [];

        foreach (is_array($runs[$kind] ?? null) ? $runs[$kind] : [] as $key => $items) {
            $section[(string) $key] = self::stepsIn($items);
        }

        return $section;
    }

    /**
     * @return list<array<mixed>>
     */
    private static function stepsIn(mixed $items): array
    {
        $steps = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (is_array($item['step'] ?? null)) {
                $steps[] = $item['step'];
            }

            if (isset($item['parallel'])) {
                $inner = is_array($item['parallel']) && isset($item['parallel']['steps']) ? $item['parallel']['steps'] : $item['parallel'];
                array_push($steps, ...self::stepsIn($inner));
            }
        }

        return $steps;
    }

    /**
     * @param array<mixed> $step
     * @return list<string>
     */
    private static function lines(array $step): array
    {
        return array_values(array_filter(is_array($step['script'] ?? null) ? $step['script'] : [], 'is_string'));
    }

    /**
     * @param array<mixed> $step
     */
    private static function name(array $step): string
    {
        return is_string($step['name'] ?? null) ? $step['name'] : 'unnamed';
    }
}
