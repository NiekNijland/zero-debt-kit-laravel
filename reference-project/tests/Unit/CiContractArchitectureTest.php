<?php

declare(strict_types=1);

use Tests\Support\Architecture\CiContractGuard;
use Tests\Support\Architecture\CiExamples;
use Tests\Support\Architecture\CiSafetyGuard;

function ciPipelineGuard(): CiContractGuard
{
    return new CiContractGuard('', ['main'], 'composer test:unit');
}

dataset('pipelines that narrow a check or skip a run', [
    'a check that succeeds anyway' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "- bash scripts/checks.sh --part static\n", "- bash scripts/checks.sh --part static || true\n"), 'pull-requests has no step on the pattern **'],
    'another flag on the command' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "- bash scripts/checks.sh --part static\n", "- bash scripts/checks.sh --part static --changed\n"), 'pull-requests has no step on the pattern **'],
    'a path condition' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "              name: Checks static\n", "              name: Checks static\n              condition:\n                changesets:\n                  includePaths:\n                    - 'app/**'\n"), 'pull-requests has no step on the pattern **'],
    'the command only in a comment' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "- bash scripts/checks.sh --part static\n", "# - bash scripts/checks.sh --part static\n"), 'pull-requests has no step on the pattern **'],
    'the checks on a pattern that is not **' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "'**':", "'feature/*':"), 'pull-requests has no step on the pattern **'],
    'no run on the deploying branch' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "    main:\n", "    release:\n"), 'the deploying branch main has no step'],
    'the scheduled run without its command' => [fn (): string => CiExamples::change(CiExamples::pipeline(), 'checks.sh --scheduled', 'checks.sh'), 'the custom pipeline `scheduled`'],
    'a shallow clone' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "  depth: full\n", "  depth: 50\n"), 'does not clone with full depth'],
    'a step without a maximum time' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "          name: Scheduled\n          max-time: 30\n", "          name: Scheduled\n"), 'step `Scheduled` has no max-time'],
    'a manual trigger' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "              name: Backend tests\n", "              name: Backend tests\n              trigger: manual\n"), 'has a manual trigger'],
    'a part without a step' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "--part repo\n", "--part gate\n"), '--part repo` without a condition'],
    'a moving image tag' => [fn (): string => CiExamples::change(CiExamples::pipeline(), 'php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000', 'php:latest'), 'image is not pinned by digest'],
    'the tests twice on one push' => [fn (): string => CiExamples::change(CiExamples::pipeline(), "    main:\n", "    '**':\n      - step:\n          name: Backend tests\n          max-time: 20\n          script:\n            - composer test:unit\n    main:\n"), 'runs the backend tests 2 times'],
]);

it('detects a pipeline that narrows a check or skips a run', function (Closure $pipeline, string $reason): void {
    $violations = ciPipelineGuard()->inPipeline($pipeline());

    expect(implode("\n", $violations))->toContain($reason);
})->with('pipelines that narrow a check or skip a run');

it('accepts the pipeline with the five runs, in a brace pattern and with an alias of a step', function (): void {
    $braced = CiExamples::change(CiExamples::pipeline(), "    main:\n", "    '{main,develop}':\n");
    $guard = new CiContractGuard('', ['main', 'develop'], 'composer test:unit');

    expect(ciPipelineGuard()->inPipeline(CiExamples::pipeline()))->toBe([])
        ->and($guard->inPipeline($braced))->toBe([])
        ->and(ciPipelineGuard()->inPipeline(CiExamples::pipeline() . "definitions:\n  steps:\n    - step: &unused\n        name: Checks\n        script:\n          - bash scripts/checks.sh || true\n"))->toBe([]);
});

dataset('scripts that weaken the checks step', [
    'a task that is gone' => [fn (): string => CiExamples::change(CiExamples::checks(), '    "Lint::npx eslint . --max-warnings 0"' . "\n", ''), 'no task named "Lint"'],
    'the ratchets after the dashboard' => [fn (): string => CiExamples::change(CiExamples::checks(), "bash scripts/ci-ratchets.sh\nbash scripts/debt-dashboard.sh \$DASHBOARD_MODE --offline --details\n", "bash scripts/debt-dashboard.sh \$DASHBOARD_MODE --offline --details\nbash scripts/ci-ratchets.sh\n"), 'does not run its four stages in the order'],
    'a dashboard run that is not offline and detailed' => [fn (): string => CiExamples::change(CiExamples::checks(), ' --offline --details', ' --offline'), 'does not run with --offline --details'],
    'a placeholder' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test', '<formatter in check mode>'), 'still holds a <placeholder>'],
    'a commit' => [fn (): string => CiExamples::change(CiExamples::checks(), "bash scripts/ci-parallel.sh", "git commit -am autofix\nbash scripts/ci-parallel.sh"), 'a command writes to the repository'],
    'a check that succeeds anyway' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test"', 'vendor/bin/pint --test || true"'), 'succeed anyway'],
]);

it('detects a checks script that weakens the checks step', function (Closure $script, string $reason): void {
    $violations = new CiSafetyGuard('develop')->inChecks($script(), CiExamples::kitConfig());

    expect(implode("\n", $violations))->toContain($reason);
})->with('scripts that weaken the checks step');

it('detects a default base branch that is not the branch pull requests target', function (): void {
    $violations = new CiSafetyGuard('main')->inChecks(CiExamples::checks(), CiExamples::kitConfig());

    expect(implode("\n", $violations))->toContain('DEFAULT_BASE_BRANCH in kit-config.sh is not `main`');
});

it('accepts the checks script, comments that name a banned command and a writing line the owner kept', function (): void {
    $commented = CiExamples::change(CiExamples::checks(), '# The one command.', "# git push is never run; || true is never written\n# \"Lint::<placeholder in a comment>\"");
    $kept = CiExamples::change(CiExamples::checks(), 'bash scripts/ci-parallel.sh', "git commit -am autofix\nbash scripts/ci-parallel.sh");

    expect(new CiSafetyGuard('develop')->inChecks(CiExamples::checks(), CiExamples::kitConfig()))->toBe([])
        ->and(new CiSafetyGuard('develop')->inChecks($commented, CiExamples::kitConfig()))->toBe([])
        ->and(new CiSafetyGuard('develop', ['git commit -am autofix'])->inChecks($kept, CiExamples::kitConfig()))->toBe([]);
});

it('keeps the pipeline and the checks script as the contract says', function (): void {
    $root = dirname(__DIR__, 2);

    expect(new CiContractGuard($root . '/bitbucket-pipelines.yml', ['develop'], 'composer test:unit')->violations())->toBe([])
        ->and(new CiSafetyGuard('develop')->inChecks((string) file_get_contents($root . '/scripts/checks.sh'), (string) file_get_contents($root . '/scripts/kit-config.sh')))->toBe([]);
});
