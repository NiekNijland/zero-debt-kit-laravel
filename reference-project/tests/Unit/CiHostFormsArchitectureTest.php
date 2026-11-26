<?php

declare(strict_types=1);

use Tests\Support\Architecture\CiExamples;
use Tests\Support\Architecture\CiHostExamples;
use Tests\Support\Architecture\CodeOwnersGuard;
use Tests\Support\Architecture\GithubWorkflowGuard;
use Tests\Support\Architecture\GitlabPipelineGuard;

// The GitHub form (a workflow directory, the kit's jobs in a file of their own), the GitLab form and point 22 (code owners) of the
// contract in 04-guards.md C4. CiContractArchitectureTest holds the Bitbucket form and the checks script.

const CI_SHA = 'actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2';

function githubViolations(string $workflow): string
{
    return implode("\n", new GithubWorkflowGuard('', ['main', 'develop'], 'composer test:unit')->inWorkflows(['kit-checks.yml' => $workflow]));
}

function inGithubJob(string $job, string $from, string $to): string
{
    return CiExamples::changeAfter(CiHostExamples::githubWorkflow(), "\n  " . $job . ":\n", $from, $to);
}

dataset('GitHub workflows that narrow a check or skip a run', [
    'a manual trigger' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "on:\n", "on:\n  workflow_dispatch:\n"), 'the trigger `workflow_dispatch` is not one of'],
    'a trigger that runs with secrets' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "on:\n", "on:\n  pull_request_target:\n"), 'pull_request_target is used'],
    'a path filter on the pull request' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "  pull_request:\n", "  pull_request:\n    paths: ['app/**']\n"), 'pull_request has `paths`'],
    'a branch filter, which a stacked pull request does not match' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "  pull_request:\n", "  pull_request:\n    branches: [main]\n"), 'pull_request has `branches`'],
    'no re-run after a retarget' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), ', edited]', ']'), 'pull_request.types lacks `edited`'],
    'a deploying branch that is not listed' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "branches: ['main', 'develop']", "branches: ['main']"), 'does not list the deploying branch develop'],
    'a condition on the checks job' => [fn (): string => inGithubJob('checks', "if: github.event_name == 'pull_request'", "if: github.event_name == 'pull_request' && github.actor != 'renovate[bot]'"), 'job `checks` has another condition'],
    'a part without a matrix entry' => [fn (): string => inGithubJob('checks', 'part: [gate, static, guards, repo]', 'part: [gate, static, guards]'), 'the matrix of job `checks` is not exactly the parts'],
    'a checks step that may fail' => [fn (): string => inGithubJob('checks', "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n", "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n        continue-on-error: true\n"), 'a step may fail'],
    'a command that succeeds anyway' => [fn (): string => inGithubJob('checks', 'run: composer install', 'run: composer install || true'), 'a check is made to succeed anyway'],
    'an action on a tag' => [fn (): string => inGithubJob('frontend-tests', CI_SHA, 'actions/checkout@v4'), 'not pinned to a full commit sha'],
    'a runner on a moving label' => [fn (): string => inGithubJob('checks-result', 'runs-on: ubuntu-24.04', 'runs-on: ubuntu-latest'), 'a moving label'],
    'a container without a digest' => [fn (): string => inGithubJob('smoke', 'php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000', 'php:8.4'), 'not pinned by digest'],
    'a job without a maximum time' => [fn (): string => inGithubJob('checks-result', "    timeout-minutes: 5\n", ''), 'has no integer timeout-minutes'],
    'a shallow clone' => [fn (): string => inGithubJob('frontend-tests', 'fetch-depth: 0', 'fetch-depth: 1'), 'a shallow clone'],
    'credentials that the checkout drops' => [fn (): string => inGithubJob('checks', 'fetch-depth: 0', "fetch-depth: 0\n          persist-credentials: false"), 'persist-credentials: false'],
    'a head that the job does not check out' => [fn (): string => inGithubJob('checks', 'CHECK_BASE: ${{ github.base_ref }}', "CHECK_BASE: \${{ github.base_ref }}\n      CHECK_HEAD: \${{ github.event.pull_request.head.sha }}"), 'sets CHECK_HEAD but does not check out that revision'],
    'no base for the ratchets' => [fn (): string => inGithubJob('checks', "    env:\n      CHECK_BASE: \${{ github.base_ref }}\n", ''), 'does not set CHECK_BASE'],
    'a service image that is not the production engine' => [fn (): string => inGithubJob('backend-tests', 'image: postgres:17', 'image: postgres:16'), 'is not DATABASE_IMAGE'],
    'a service without a health check' => [fn (): string => inGithubJob('smoke', "        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n          --health-interval 5s --health-timeout 5s --health-retries 12\n", ''), 'no env or no health check'],
    'no concurrency group' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "concurrency:\n  group: kit-\${{ github.event_name }}-\${{ github.event.pull_request.number || github.sha }}\n  cancel-in-progress: \${{ github.event_name == 'pull_request' }}\n\n", ''), 'no concurrency group'],
    'a group that cancels a push to a deploying branch' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "cancel-in-progress: \${{ github.event_name == 'pull_request' }}", 'cancel-in-progress: true'), 'concurrency cancels runs of every event'],
    'a token that writes' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "permissions:\n  contents: read\n\ndefaults", "permissions:\n  contents: write\n\ndefaults"), 'a permission is `write`'],
    'an aggregate job that passes whatever happened' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'run: test "$RESULT" = success', 'run: echo done'), 'no aggregate job'],
    'the tests twice on one event' => [fn (): string => CiHostExamples::githubWorkflow() . "  again:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 5\n    steps:\n      - run: composer test:unit\n", 'the backend tests run 2 times on one pull_request event'],
]);

it('detects a GitHub workflow that narrows a check or skips a run', function (Closure $workflow, string $reason): void {
    expect(githubViolations($workflow()))->toContain($reason);
})->with('GitHub workflows that narrow a check or skip a run');

it('accepts the GitHub skeleton with its placeholders filled, and a deploy workflow that waits for it', function (): void {
    $deploy = "name: Deploy\non:\n  workflow_run:\n    workflows: [Kit checks]\n    types: [completed]\npermissions:\n  contents: read\njobs:\n  deploy:\n    if: github.event.workflow_run.conclusion == 'success'\n    runs-on: ubuntu-24.04\n    timeout-minutes: 10\n    steps:\n      - run: echo deploy\n";
    $guard = new GithubWorkflowGuard('', ['main', 'develop'], 'composer test:unit');

    expect($guard->inWorkflows(['kit-checks.yml' => CiHostExamples::githubWorkflow()]))->toBe([])
        ->and($guard->inWorkflows(['kit-checks.yml' => CiHostExamples::githubWorkflow(), 'deploy.yml' => $deploy]))->toBe([])
        ->and(implode("\n", $guard->inWorkflows(['deploy.yml' => $deploy])))->toContain('no workflow runs `bash scripts/checks.sh`');
});

function inGitlabJob(string $job, string $from, string $to): string
{
    return CiExamples::changeAfter(CiHostExamples::gitlabPipeline(), "\n" . $job . ":\n", $from, $to);
}

dataset('GitLab pipelines that narrow a check or skip a run', [
    'a shallow clone' => [fn (): string => CiExamples::change(CiHostExamples::gitlabPipeline(), 'GIT_DEPTH: "0"', 'GIT_DEPTH: 20'), 'GIT_DEPTH is not 0'],
    'a branch rule that lets every source through' => [fn (): string => CiExamples::change(CiHostExamples::gitlabPipeline(), '- if: $CI_PIPELINE_SOURCE == "push" && $CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH', '- if: $CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH'), 'lets every pipeline source through'],
    'a part without a matrix entry' => [fn (): string => inGitlabJob('checks', 'PART: [gate, static, guards, repo]', 'PART: [gate, static, guards]'), 'is not exactly the parts'],
    'a changes filter on the checks job' => [fn (): string => inGitlabJob('checks', "    - if: \$CI_PIPELINE_SOURCE == \"merge_request_event\"\n", "    - if: \$CI_PIPELINE_SOURCE == \"merge_request_event\"\n      changes: ['app/**']\n"), 'has another rule than'],
    'a job that may fail' => [fn (): string => inGitlabJob('smoke', "  stage: verify\n", "  stage: verify\n  allow_failure: true\n"), 'may fail or waits for a click'],
    'a job without a maximum time' => [fn (): string => inGitlabJob('scheduled', "  timeout: 20 minutes\n", ''), 'job `scheduled` has no timeout'],
    'an image that is not pinned by digest' => [fn (): string => CiExamples::change(CiHostExamples::gitlabPipeline(), 'php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000', 'php:8.4'), 'image is not pinned by digest'],
    'a service image that is not the production engine' => [fn (): string => inGitlabJob('backend-tests', 'name: postgres:17', 'name: postgres:16'), 'is not DATABASE_IMAGE'],
    'a service without a password' => [fn (): string => inGitlabJob('scheduled', "    POSTGRES_PASSWORD: ci\n", ''), 'no password variable'],
    'no base for the ratchets' => [fn (): string => inGitlabJob('checks', "    CHECK_BASE: \$CI_MERGE_REQUEST_TARGET_BRANCH_NAME\n", ''), 'does not set CHECK_BASE'],
    'a command that succeeds anyway' => [fn (): string => inGitlabJob('checks', '- composer install', '- composer install || true'), 'a check is made to succeed anyway'],
    'the scheduled checks on another source' => [fn (): string => inGitlabJob('scheduled', '- if: $CI_PIPELINE_SOURCE == "schedule"', '- if: $CI_PIPELINE_SOURCE == "web"'), 'runs the scheduled checks on another rule'],
    'the tests twice on one pipeline' => [fn (): string => CiHostExamples::gitlabPipeline() . "again:\n  timeout: 5 minutes\n  script:\n    - composer test:unit\n", 'the backend tests run 2 times'],
]);

it('detects a GitLab pipeline that narrows a check or skips a run', function (Closure $pipeline, string $reason): void {
    $violations = new GitlabPipelineGuard('', ['main', 'develop'], 'composer test:unit')->inPipeline($pipeline());

    expect(implode("\n", $violations))->toContain($reason);
})->with('GitLab pipelines that narrow a check or skip a run');

it('accepts the GitLab skeleton with its placeholders filled', function (): void {
    expect(new GitlabPipelineGuard('', ['main', 'develop'], 'composer test:unit')->inPipeline(CiHostExamples::gitlabPipeline()))->toBe([]);
});

// Point 22: the files that carry the guards are covered by a code-owners rule that names an owner.
dataset('code-owners files that leave a guard file without a human review', [
    'the scripts not covered' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", ''), 'scripts/debt-caps.json is covered by no code-owners rule'],
    'the workflow directory not covered' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/.github/workflows/ @lead\n", ''), '.github/workflows/kit-checks.yml is covered by no'],
    'the guard folder not covered' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/tests/Support/Architecture/ @lead\n", ''), 'tests/Support/Architecture/CiContractGuard.php is covered by no'],
    'the file not covering itself' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/.github/CODEOWNERS @lead\n", ''), '.github/CODEOWNERS is covered by no'],
    'a later rule that takes a script out of the review' => [fn (): string => CiHostExamples::codeOwners() . "/scripts/kit-config.sh\n", 'scripts/kit-config.sh is matched last by a rule with no owner'],
    'a pattern that matches nothing' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", "/script/ @lead\n"), 'scripts/checks.sh is covered by no'],
]);

it('detects a code-owners file that leaves a guard file without the review of its owner', function (Closure $codeOwners, string $reason): void {
    $violations = new CodeOwnersGuard('', CiHostExamples::guardedPaths())->inCodeOwners($codeOwners());

    expect(implode("\n", $violations))->toContain($reason);
})->with('code-owners files that leave a guard file without a human review');

it('accepts a code-owners file that covers every guard file, and a host that enforces the owners outside the repository', function (): void {
    expect(new CodeOwnersGuard('', CiHostExamples::guardedPaths())->inCodeOwners(CiHostExamples::codeOwners()))->toBe([])
        ->and(new CodeOwnersGuard('/no/such/CODEOWNERS', CiHostExamples::guardedPaths(), hostEnforced: true)->violations())->toBe([])
        ->and(implode("\n", new CodeOwnersGuard('/no/such/CODEOWNERS', CiHostExamples::guardedPaths())->violations()))->toContain('no code-owners file');
});
