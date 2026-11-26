<?php

declare(strict_types=1);

use Tests\Support\Architecture\DatabaseEngine;
use Tests\Support\Architecture\GithubWorkflowGuard;
use Tests\Support\Ci\CiExamples;
use Tests\Support\Ci\CiHostExamples;
use Tests\Support\Ci\CiHostVariants;
use Tests\Support\SourceSandbox;

uses()->group('arch');

// The GitHub form of the contract in 04-guards.md C4: a workflow directory, with the kit's jobs in a file of their own. A project on another host keeps the
// test of its own host and deletes this one with its guard.

dataset('GitHub workflows that narrow a check or skip a run', [
    'a manual trigger' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "on:\n", "on:\n  workflow_dispatch:\n"), 'the trigger `workflow_dispatch` is not one of'],
    'a trigger that runs with secrets' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "on:\n", "on:\n  pull_request_target:\n"), 'pull_request_target is used'],
    'a path filter on the pull request' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "  pull_request:\n", "  pull_request:\n    paths: ['app/**']\n"), 'pull_request has `paths`'],
    'a branch filter, which a stacked pull request does not match' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "  pull_request:\n", "  pull_request:\n    branches: [main]\n"), 'pull_request has `branches`'],
    'no re-run after a retarget' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), ', edited]', ']'), 'pull_request.types lacks `edited`'],
    'a deploying branch that is not listed' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "branches: ['main', 'develop']", "branches: ['main']"), 'does not list the deploying branch develop'],
    'a schedule trigger that is gone' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "  schedule:\n    - cron: '17 3 * * *'\n", ''), 'the workflow has no `schedule` trigger'],
    'a cron that is a placeholder' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "'17 3 * * *'", "'<cron expression>'"), 'still holds a placeholder'],
    'a condition on the checks job' => [fn (): string => CiHostVariants::inGithubJob('checks', "if: github.event_name == 'pull_request'", "if: github.event_name == 'pull_request' && github.actor != 'renovate[bot]'"), 'job `checks` has another condition'],
    'a checks job that another job can skip' => [fn (): string => CiHostVariants::inGithubJob('checks', "    runs-on: ubuntu-24.04\n", "    needs: [smoke]\n    runs-on: ubuntu-24.04\n"), 'job `checks` waits for smoke'],
    'a part without a matrix entry' => [fn (): string => CiHostVariants::inGithubJob('checks', 'part: [gate, static, guards, repo]', 'part: [gate, static, guards]'), 'the matrix of job `checks` is not exactly the parts'],
    'a part dropped by an exclude' => [fn (): string => CiHostVariants::inGithubJob('checks', "        part: [gate, static, guards, repo]\n", "        part: [gate, static, guards, repo]\n        exclude:\n          - part: static\n"), 'has the key `exclude` besides `part`'],
    'a matrix that is an expression' => [fn (): string => CiHostVariants::inGithubJob('checks', 'part: [gate, static, guards, repo]', "part: \${{ fromJSON('[\"gate\"]') }}"), 'the matrix of job `checks` is not exactly the parts'],
    'a job switched off with the boolean false' => [fn (): string => CiHostVariants::inGithubJob('checks-main', "if: github.event_name == 'push'", 'if: false'), 'is switched off'],
    'a job switched off with the expression false' => [fn (): string => CiHostVariants::inGithubJob('checks-main', "if: github.event_name == 'push'", 'if: ${{ false }}'), 'is switched off'],
    'a step switched off with the boolean false' => [fn (): string => CiHostVariants::inGithubJob('smoke', 'run: composer install', "run: composer install\n        if: false"), 'a step may fail or is forced on'],
    'a checks step that may fail' => [fn (): string => CiHostVariants::inGithubJob('checks', "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n", "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n        continue-on-error: true\n"), 'a step may fail'],
    'a checks step with a base of its own' => [fn (): string => CiHostVariants::inGithubJob('checks', "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n", "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n        env:\n          CHECK_BASE: main\n"), 'sets CHECK_BASE in its own env'],
    'a checks step under another shell' => [fn (): string => CiHostVariants::inGithubJob('checks', "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n", "      - run: bash scripts/checks.sh --part \${{ matrix.part }}\n        shell: bash -c true {0}\n"), 'sets `shell`'],
    'a command that succeeds anyway' => [fn (): string => CiHostVariants::inGithubJob('checks', 'run: composer install', 'run: composer install || true'), 'a check is made to succeed anyway'],
    'a placeholder in a run line' => [fn (): string => CiHostVariants::inGithubJob('checks', 'run: composer install', 'run: <install dependencies from the lock file>'), 'still holds a placeholder'],
    'an install that does not come from the lock file' => [fn (): string => CiHostVariants::inGithubJob('frontend-tests', 'run: composer install', 'run: npm install'), 'does not come from the lock file'],
    'an action on a tag' => [fn (): string => CiHostVariants::inGithubJob('frontend-tests', CiHostExamples::CHECKOUT_PIN, 'actions/checkout@v4'), 'not pinned to a full commit sha'],
    'a reusable workflow on a branch' => [fn (): string => CiHostExamples::githubWorkflow() . "  reuse:\n    uses: org/repo/.github/workflows/x.yml@main\n", 'calls the workflow'],
    'a runner on a moving label' => [fn (): string => CiHostVariants::inGithubJob('checks-result', 'runs-on: ubuntu-24.04', 'runs-on: ubuntu-latest'), 'a moving label'],
    'a container without a digest' => [fn (): string => CiHostVariants::inGithubJob('smoke', 'php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000', 'php:8.4'), 'not pinned by digest'],
    'a privileged container' => [fn (): string => CiHostVariants::inGithubJob('checks', "    container:\n", "    container:\n      options: --privileged\n"), 'passes `--privileged` to its container'],
    'a job without a maximum time' => [fn (): string => CiHostVariants::inGithubJob('checks-result', "    timeout-minutes: 5\n", ''), 'has no integer timeout-minutes'],
    'a maximum time that is the default of the host' => [fn (): string => CiHostVariants::inGithubJob('smoke', 'timeout-minutes: 20', 'timeout-minutes: 360'), 'above the 120 the guard allows'],
    'a shallow clone' => [fn (): string => CiHostVariants::inGithubJob('frontend-tests', 'fetch-depth: 0', 'fetch-depth: 1'), 'a shallow clone'],
    'credentials that the checkout drops' => [fn (): string => CiHostVariants::inGithubJob('checks', 'fetch-depth: 0', "fetch-depth: 0\n          persist-credentials: false"), 'persist-credentials: false'],
    'a head that the job does not check out' => [fn (): string => CiHostVariants::inGithubJob('checks', 'CHECK_BASE: ${{ github.base_ref }}', "CHECK_BASE: \${{ github.base_ref }}\n      CHECK_HEAD: \${{ github.event.pull_request.head.sha }}"), 'sets CHECK_HEAD but does not check out that revision'],
    'no base for the ratchets' => [fn (): string => CiHostVariants::inGithubJob('checks', "    env:\n      CHECK_BASE: \${{ github.base_ref }}\n", ''), 'does not set CHECK_BASE'],
    'a tool downloaded without its checksum' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "          echo \"\${GITLEAKS_SHA256}  gitleaks.tgz\" | sha256sum -c -\n", ''), 'checks no checksum'],
    'a download piped into a shell' => [fn (): string => CiHostVariants::inGithubJob('smoke', 'run: composer install', 'run: curl -sSfL https://example.test/install.sh | sh'), 'pipes a download into a shell'],
    'a service image that is not the production engine' => [fn (): string => CiHostVariants::inGithubJob('backend-tests', 'image: postgres:17', 'image: postgres:16'), 'is not DATABASE_IMAGE'],
    'a service without a health check' => [fn (): string => CiHostVariants::inGithubJob('smoke', "        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n          --health-interval 5s --health-timeout 5s --health-retries 12\n", ''), 'no health check of its engine'],
    'a service without a password' => [fn (): string => CiHostVariants::inGithubJob('smoke', "        env:\n          POSTGRES_PASSWORD: ci\n", ''), 'no password variable of its engine'],
    'a service that the job does not reach by its name' => [fn (): string => CiHostVariants::inGithubJob('backend-tests', "      DB_HOST: database\n", ''), 'is not the DB_HOST of its job'],
    'a test run without its database service' => [fn (): string => CiExamples::changeAfter(CiHostExamples::githubWorkflow(), "\n  backend-tests:\n", "    services:\n      database:\n        image: postgres:17\n        env:\n          POSTGRES_PASSWORD: ci\n        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n          --health-interval 5s --health-timeout 5s --health-retries 12\n", ''), 'job `backend-tests` has no database service'],
    'a smoke run that does not run in a browser' => [fn (): string => CiHostVariants::inGithubJob('smoke', 'run: npx playwright test', 'run: php artisan test'), 'does not run `playwright`'],
    'a backend run that holds no coverage floor' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "      - run: bash scripts/checks.sh --coverage coverage/clover.xml\n", ''), 'has no line `bash scripts/checks.sh --coverage'],
    'a scheduled job without the mutation run' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "      - run: bash scripts/mutation.sh\n", ''), 'does not run `bash scripts/mutation.sh`'],
    'a deleted backend test job' => [fn (): string => CiHostVariants::withoutGithubJob('backend-tests'), 'no job `backend-tests`'],
    'a deleted smoke job' => [fn (): string => CiHostVariants::withoutGithubJob('smoke'), 'no job `smoke`'],
    'a deleted frontend test job' => [fn (): string => CiHostVariants::withoutGithubJob('frontend-tests'), 'no job `frontend-tests`'],
    'a test job that skips the pull requests' => [fn (): string => CiHostVariants::inGithubJob('frontend-tests', "if: github.event_name != 'schedule'", "if: github.event_name == 'push'"), 'does not run on every pull request'],
    'two jobs that report one check name' => [fn (): string => CiHostVariants::inGithubJob('smoke', "    timeout-minutes: 20\n", "    timeout-minutes: 20\n    name: backend-tests\n"), 'is the name of another job'],
    'no concurrency group' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "concurrency:\n  group: kit-\${{ github.event_name }}-\${{ github.event.pull_request.number || github.sha }}\n  cancel-in-progress: \${{ github.event_name == 'pull_request' }}\n\n", ''), 'no concurrency group'],
    'a group that every run shares' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'group: kit-${{ github.event_name }}-${{ github.event.pull_request.number || github.sha }}', 'group: ci'), 'does not hold `github.event_name`'],
    'a group on the ref alone' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'group: kit-${{ github.event_name }}-${{ github.event.pull_request.number || github.sha }}', 'group: kit-${{ github.ref }}'), 'does not hold `github.event_name`'],
    'a group that cancels a push to a deploying branch' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "cancel-in-progress: \${{ github.event_name == 'pull_request' }}", 'cancel-in-progress: true'), 'concurrency cancels runs of every event'],
    'a token that writes' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "permissions:\n  contents: read\n\ndefaults", "permissions:\n  contents: write\n\ndefaults"), 'a permission is `write`'],
    'a default shell without pipefail' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "defaults:\n  run:\n    shell: bash\n\n", ''), 'does not set `defaults.run.shell: bash`'],
    'a secret in a job that runs the pull request\'s scripts' => [fn (): string => CiHostVariants::inGithubJob('checks', "      CHECK_BASE: \${{ github.base_ref }}\n", "      CHECK_BASE: \${{ github.base_ref }}\n      TOKEN: \${{ secrets.DEPLOY_KEY }}\n"), 'reads a secret'],
    'an aggregate job that passes whatever happened' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'run: test "$RESULT" = success', 'run: echo done'), 'no aggregate job'],
    'an aggregate job that needs nothing' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'needs: [checks]', 'needs: []'), 'must need exactly the checks job'],
    'an aggregate job that needs the other checks' => [fn (): string => CiExamples::change(CiExamples::change(CiHostExamples::githubWorkflow(), 'needs: [checks]', 'needs: [checks-main]'), 'needs.checks.result', 'needs.checks-main.result'), 'must need exactly the checks job'],
    'an aggregate job that reads another result' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'needs.checks.result', 'needs.checks-main.result'), 'does not read RESULT'],
    'an aggregate job without always()' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), "if: always() && github.event_name == 'pull_request'", "if: github.event_name == 'pull_request'"), 'must need exactly the checks job'],
    'the tests twice on one event' => [fn (): string => CiHostExamples::githubWorkflow() . "  again:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 5\n    steps:\n      - run: composer test:unit\n", 'the backend tests run 2 times on one pull_request event'],
]);

it('detects a GitHub workflow that narrows a check or skips a run', function (Closure $workflow, string $reason): void {
    expect(CiHostVariants::githubViolations($workflow()))->toContain($reason);
})->with('GitHub workflows that narrow a check or skip a run');

dataset('GitHub service containers that do not match the engine', [
    'a mysql image with the variable and the health check of postgres' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'image: postgres:17', 'image: mysql:8.4'), 'mysql:8.4', 'no password variable of its engine (mysql: MYSQL_ROOT_PASSWORD'],
    'a mysql image with the health check of postgres only' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowFor('mysql'), 'mysqladmin ping -h 127.0.0.1 -uroot -pci', 'pg_isready -U postgres'), 'mysql:8.4', 'no health check of its engine (mysqladmin)'],
    'a mariadb image with the variable of mysql only' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowFor('mariadb'), 'MARIADB_ROOT_PASSWORD: ci', 'POSTGRES_PASSWORD: ci'), 'mariadb:11.4', 'no password variable of its engine (mariadb'],
    'a postgres image with the variable of mysql' => [fn (): string => CiExamples::change(CiHostExamples::githubWorkflow(), 'POSTGRES_PASSWORD: ci', 'MYSQL_ROOT_PASSWORD: ci'), 'postgres:17', 'no password variable of its engine (postgres: POSTGRES_PASSWORD'],
]);

it('reads the engine from DATABASE_IMAGE and fails a service that its engine cannot start or wait for', function (Closure $workflow, string $image, string $reason): void {
    expect(CiHostVariants::githubViolations($workflow(), CiHostVariants::githubGuard($image)))->toContain($reason);
})->with('GitHub service containers that do not match the engine');

it('accepts the GitHub skeleton with its placeholders filled, for each engine and for none, and a deploy workflow that waits for it', function (): void {
    $deploy = "name: Deploy\non:\n  workflow_run:\n    workflows: [Kit checks]\n    types: [completed]\npermissions:\n  contents: read\njobs:\n  deploy:\n    if: github.event.workflow_run.conclusion == 'success'\n    runs-on: ubuntu-24.04\n    timeout-minutes: 10\n    steps:\n      - run: echo deploy\n";
    $guard = CiHostVariants::githubGuard();

    expect($guard->inWorkflows(['kit-checks.yml' => CiHostExamples::githubWorkflow()], CiExamples::checks()))->toBe([])
        ->and($guard->inWorkflows(['kit-checks.yml' => CiHostExamples::githubWorkflow(), 'deploy.yml' => $deploy], CiExamples::checks()))->toBe([])
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard('mysql:8.4'), ['kit-checks.yml' => CiHostVariants::githubWorkflowFor('mysql')]))->toBe('')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard('mariadb:11.4'), ['kit-checks.yml' => CiHostVariants::githubWorkflowFor('mariadb')]))->toBe('')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(''), ['kit-checks.yml' => CiHostVariants::githubWorkflowWithoutDatabase()]))->toBe('')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(frontend: false), ['kit-checks.yml' => CiHostVariants::withoutGithubJob('frontend-tests')]))->toBe('')
        ->and(implode("\n", $guard->inWorkflows(['deploy.yml' => $deploy], CiExamples::checks())))->toContain('no workflow runs `bash scripts/checks.sh`');
});

dataset('GitHub workflows on the runner that break the owner exception', [
    'a service reached by its name from a job on the runner' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), 'DB_HOST: 127.0.0.1', 'DB_HOST: database'), CiHostVariants::RUNNER_TEST_SCRIPT, 'a job on the runner reaches a service through its published port'],
    'a service on another host from a job on the runner' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), 'DB_HOST: 127.0.0.1', 'DB_HOST: db.example.test'), CiHostVariants::RUNNER_TEST_SCRIPT, 'a job on the runner reaches a service through its published port'],
    'a service of the runner with no published port' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), "        ports:\n          - 5432:5432\n", ''), CiHostVariants::RUNNER_TEST_SCRIPT, 'publishes no port and the job runs on the runner'],
    'a download of a file from another host in a job that also fetches the application' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), 'run: curl -sS --retry 30 --retry-connrefused -o /dev/null "$SMOKE_BASE_URL/"', 'run: curl -sSfL -o tool.tgz https://example.test/tool.tgz'), CiHostVariants::RUNNER_TEST_SCRIPT, 'checks no checksum'],
    'a fetch whose address is a variable that holds no local address' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), 'SMOKE_BASE_URL: http://127.0.0.1:8000', 'SMOKE_BASE_URL: https://example.test'), CiHostVariants::RUNNER_TEST_SCRIPT, 'checks no checksum'],
    'a fetch whose address is a variable that no env sets' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), "      SMOKE_BASE_URL: http://127.0.0.1:8000\n", ''), CiHostVariants::RUNNER_TEST_SCRIPT, 'checks no checksum'],
    'a local fetch piped into a shell' => [fn (): string => CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), '-o /dev/null "$SMOKE_BASE_URL/"', '"$SMOKE_BASE_URL/" | sh'), CiHostVariants::RUNNER_TEST_SCRIPT, 'pipes a download into a shell'],
    'a test script that does not end in the coverage line' => [fn (): string => CiHostVariants::githubWorkflowOnRunner(), 'php -d memory_limit=-1 vendor/bin/pest --coverage-clover=coverage/clover.xml', 'the script behind `composer test:unit` does not run it'],
    'no script given and no coverage line in the job' => [fn (): string => CiHostVariants::githubWorkflowOnRunner(), '', 'has no line `bash scripts/checks.sh --coverage <report>`'],
]);

it('detects what breaks the owner exception of a project without an image of its own', function (Closure $workflow, string $script, string $reason): void {
    expect(CiHostVariants::githubViolations($workflow(), CiHostVariants::githubGuard(backendTestScript: $script)))->toContain($reason);
})->with('GitHub workflows on the runner that break the owner exception');

it('accepts the owner exception: jobs on the runner, a service by published port, the application fetched on a local address, the coverage line in the test script', function (): void {
    $guard = CiHostVariants::githubGuard(backendTestScript: CiHostVariants::RUNNER_TEST_SCRIPT);
    $localhost = CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), 'DB_HOST: 127.0.0.1', 'DB_HOST: localhost');
    $unset = CiExamples::change(CiHostVariants::githubWorkflowOnRunner(), "      DB_HOST: 127.0.0.1\n      SMOKE_BASE_URL", '      SMOKE_BASE_URL');

    expect(CiHostVariants::githubViolations(CiHostVariants::githubWorkflowOnRunner(), $guard))->toBe('')
        ->and(CiHostVariants::githubViolations($localhost, $guard))->toBe('')
        ->and(CiHostVariants::githubViolations($unset, $guard))->toBe('')
        ->and(CiHostVariants::githubViolations(CiHostExamples::githubWorkflow(), CiHostVariants::githubGuard(backendTestScript: CiHostVariants::RUNNER_TEST_SCRIPT)))->toBe('');
});

it('reads a second workflow that runs part of the checks script as the kit file unless the owner names it auxiliary', function (): void {
    $files = ['kit-checks.yml' => CiHostExamples::githubWorkflow(), 'canary.yml' => CiHostVariants::canaryWorkflow()];
    $unpinned = ['kit-checks.yml' => CiHostExamples::githubWorkflow(), 'canary.yml' => CiExamples::change(CiHostVariants::canaryWorkflow(), 'actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1', 'actions/checkout@v4')];

    expect(CiHostVariants::githubViolations('', files: $files))->toContain('canary.yml: the trigger `workflow_dispatch` is not one of')
        ->and(CiHostVariants::githubViolations('', files: $files))->toContain('canary.yml: the workflow has no `pull_request` trigger')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(auxiliary: ['canary.yml']), $files))->toBe('')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(auxiliary: ['canary.yml']), $unpinned))->toContain('canary.yml: job `canary` uses `actions/checkout@v4`, which is not pinned to a full commit sha')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(auxiliary: ['canary.yml']), ['canary.yml' => CiHostVariants::canaryWorkflow()]))->toContain('no workflow runs `bash scripts/checks.sh`');
});

it('flags the kit\'s own file and leaves a pre-existing deploy workflow alone unless the owner names it', function (): void {
    $risky = CiHostVariants::deployWorkflow() . "  other:\n    runs-on: ubuntu-latest\n";
    $workflowRun = "name: Release\non:\n  workflow_run:\n    workflows: [Kit checks]\n    types: [completed]\njobs:\n  release:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 10\n    steps:\n      - uses: actions/checkout@v4\n        with:\n          ref: \${{ github.event.workflow_run.head_sha }}\n      - run: composer install\n";
    $files = ['kit-checks.yml' => CiHostExamples::githubWorkflow(), 'deploy.yml' => $risky, 'release.yml' => $workflowRun];

    expect(CiHostVariants::githubViolations('', files: $files))->toBe('')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(hardened: ['deploy.yml']), $files))->toContain('deploy.yml: job `deploy` has no integer timeout-minutes')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(hardened: ['deploy.yml']), $files))->toContain('deploy.yml: job `deploy` runs on a moving label')
        ->and(CiHostVariants::githubViolations('', CiHostVariants::githubGuard(hardened: ['release.yml']), $files))->toContain('release.yml: a workflow_run workflow checks out the code of the pull request');
});

it('reads the parts from the checks script, so a part added there and left out of the matrix fails', function (): void {
    $script = CiExamples::change(CiExamples::checks(), "    \"guards::Guard tests\"\n", "    \"guards::Guard tests\"\n    \"frontend::\"\n");

    expect(implode("\n", CiHostVariants::githubGuard()->inWorkflows(['kit-checks.yml' => CiHostExamples::githubWorkflow()], $script)))->toContain('the matrix of job `checks` is not exactly the parts of PARTS (gate, static, guards, frontend, repo)');
});

it('reads a workflow directory from the repository, a dot directory and a missing checks script included', function (): void {
    $root = SourceSandbox::create([
        '.github/workflows/kit-checks.yml' => CiHostExamples::githubWorkflow(),
        '.github/workflows/deploy.yaml' => CiHostVariants::deployWorkflow(),
        'scripts/checks.sh' => CiExamples::checks(),
    ], '.checkout-');
    $guard = new GithubWorkflowGuard($root . '/.github/workflows', $root . '/scripts/checks.sh', 'kit-checks.yml', ['main', 'develop'], 'composer test:unit', 'postgres:17', smokeBrowser: 'playwright');

    try {
        expect($guard->violations())->toBe([])
            ->and(implode("\n", new GithubWorkflowGuard($root . '/.github/workflows', $root . '/scripts/none.sh', 'kit-checks.yml', ['main'], 'composer test:unit', 'postgres:17')->violations()))->toContain('does not exist: the parts of the checks step are read from it')
            ->and(implode("\n", new GithubWorkflowGuard($root . '/.github/none', $root . '/scripts/checks.sh', 'kit-checks.yml', ['main'], 'composer test:unit', 'postgres:17')->violations()))->toContain('no workflow under');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('names the engine of a database image', function (): void {
    expect(DatabaseEngine::of('postgres:17'))->toBe('postgres')
        ->and(DatabaseEngine::of('postgis/postgis:17-3.5'))->toBe('postgres')
        ->and(DatabaseEngine::of('mysql:8.4'))->toBe('mysql')
        ->and(DatabaseEngine::of('mariadb:11.4@sha256:abc'))->toBe('mariadb')
        ->and(DatabaseEngine::of('ghcr.io/org/mysql:8'))->toBe('mysql')
        ->and(DatabaseEngine::of('valkey:8'))->toBe('');
});

it('keeps the workflow directory of the repository within the contract', function (): void {
    $root = dirname(__DIR__, 2);
    $guard = new GithubWorkflowGuard($root . '/.github/workflows', $root . '/scripts/checks.sh', 'kit-checks.yml', ['main', 'develop'], 'composer test:unit', 'postgres:17', smokeBrowser: 'playwright');

    expect($guard->violations())->toBe([]);
});
