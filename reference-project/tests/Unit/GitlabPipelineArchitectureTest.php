<?php

declare(strict_types=1);

use Tests\Support\Architecture\GitlabPipelineGuard;
use Tests\Support\Ci\CiExamples;
use Tests\Support\Ci\CiHostExamples;
use Tests\Support\Ci\CiHostVariants;

uses()->group('arch');

// The GitLab form of the contract in 04-guards.md C4. A project on another host keeps the test of its own host and deletes this one with its guard.

dataset('GitLab pipelines that narrow a check or skip a run', [
    'a shallow clone' => [fn (): string => CiExamples::change(CiHostExamples::gitlabPipeline(), 'GIT_DEPTH: "0"', 'GIT_DEPTH: 20'), 'GIT_DEPTH is not 0'],
    'a branch rule that lets every source through' => [fn (): string => CiExamples::change(CiHostExamples::gitlabPipeline(), '- if: $CI_PIPELINE_SOURCE == "push" && $CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH', '- if: $CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH'), 'lets every pipeline source through'],
    'a part without a matrix entry' => [fn (): string => CiHostVariants::inGitlabJob('checks', 'PART: [gate, static, guards, repo]', 'PART: [gate, static, guards]'), 'is not exactly the parts'],
    'a changes filter on the checks job' => [fn (): string => CiHostVariants::inGitlabJob('checks', "    - if: \$CI_PIPELINE_SOURCE == \"merge_request_event\"\n", "    - if: \$CI_PIPELINE_SOURCE == \"merge_request_event\"\n      changes: ['app/**']\n"), 'has another rule than'],
    'a job that may fail' => [fn (): string => CiHostVariants::inGitlabJob('smoke', "  stage: verify\n", "  stage: verify\n  allow_failure: true\n"), 'may fail or waits for a click'],
    'a job without a maximum time' => [fn (): string => CiHostVariants::inGitlabJob('scheduled', "  timeout: 20 minutes\n", ''), 'job `scheduled` has no timeout'],
    'an image that is not pinned by digest' => [fn (): string => CiExamples::change(CiHostExamples::gitlabPipeline(), 'php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000', 'php:8.4'), 'image is not pinned by digest'],
    'a service image that is not the production engine' => [fn (): string => CiHostVariants::inGitlabJob('backend-tests', 'name: postgres:17', 'name: postgres:16'), 'is not DATABASE_IMAGE'],
    'a service without a password' => [fn (): string => CiHostVariants::inGitlabJob('scheduled', "    POSTGRES_PASSWORD: ci\n", ''), 'no password variable'],
    'no base for the ratchets' => [fn (): string => CiHostVariants::inGitlabJob('checks', "    CHECK_BASE: \$CI_MERGE_REQUEST_TARGET_BRANCH_NAME\n", ''), 'does not set CHECK_BASE'],
    'a command that succeeds anyway' => [fn (): string => CiHostVariants::inGitlabJob('checks', '- composer install', '- composer install || true'), 'a check is made to succeed anyway'],
    'the scheduled checks on another source' => [fn (): string => CiHostVariants::inGitlabJob('scheduled', '- if: $CI_PIPELINE_SOURCE == "schedule"', '- if: $CI_PIPELINE_SOURCE == "web"'), 'runs the scheduled checks on another rule'],
    'the tests twice on one pipeline' => [fn (): string => CiHostExamples::gitlabPipeline() . "again:\n  timeout: 5 minutes\n  script:\n    - composer test:unit\n", 'the backend tests run 2 times'],
]);

it('detects a GitLab pipeline that narrows a check or skips a run', function (Closure $pipeline, string $reason): void {
    $violations = CiHostVariants::gitlabGuard()->inPipeline($pipeline(), CiExamples::checks());

    expect(implode("\n", $violations))->toContain($reason);
})->with('GitLab pipelines that narrow a check or skip a run');

it('reads the engine of a GitLab service from DATABASE_IMAGE', function (): void {
    $mysql = CiExamples::change(CiExamples::change(CiHostExamples::gitlabPipeline(), 'name: postgres:17', 'name: mysql:8.4'), 'POSTGRES_PASSWORD: ci', 'MYSQL_ROOT_PASSWORD: ci');
    $guard = CiHostVariants::gitlabGuard('mysql:8.4');

    expect($guard->inPipeline($mysql, CiExamples::checks()))->toBe([])
        ->and(implode("\n", $guard->inPipeline(CiExamples::change(CiHostExamples::gitlabPipeline(), 'name: postgres:17', 'name: mysql:8.4'), CiExamples::checks())))->toContain('no password variable of its engine (mysql: MYSQL_ROOT_PASSWORD');
});

it('accepts the GitLab skeleton with its placeholders filled', function (): void {
    expect(CiHostVariants::gitlabGuard()->inPipeline(CiHostExamples::gitlabPipeline(), CiExamples::checks()))->toBe([]);
});

// Point 22: the files that carry the guards are covered by a code-owners rule that names an owner.

it('keeps the pipeline file of the repository within the contract', function (): void {
    $root = dirname(__DIR__, 2);
    $guard = new GitlabPipelineGuard($root . '/.gitlab-ci.yml', $root . '/scripts/checks.sh', ['main', 'develop'], 'composer test:unit', 'postgres:17');

    expect($guard->violations())->toBe([]);
});
