<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The skeletons of ci/github-actions.yml and ci/gitlab-ci.yml with every placeholder filled, and a code-owners file that covers the
 * guard files: what the contract tests of the GitHub and GitLab forms change one line of. Keep them equal to the skeletons: the proof scenario CX27 fills the placeholders of each skeleton and compares.
 */
final class CiHostExamples
{
    public const string CHECKOUT_PIN = 'actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1';

    public static function githubWorkflow(): string
    {
        return <<<'YAML'
            name: Kit checks

            on:
              pull_request:
                types: [opened, synchronize, reopened, edited]
              push:
                branches: ['main', 'develop']
              schedule:
                - cron: '17 3 * * *'

            concurrency:
              group: kit-${{ github.event_name }}-${{ github.event.pull_request.number || github.sha }}
              cancel-in-progress: ${{ github.event_name == 'pull_request' }}

            permissions:
              contents: read

            defaults:
              run:
                shell: bash

            jobs:
              checks:
                if: github.event_name == 'pull_request'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - name: Install gitleaks
                    if: matrix.part == 'repo'
                    env:
                      GITLEAKS_VERSION: 8.30.1
                      GITLEAKS_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      curl -sSfL -o gitleaks.tgz "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz"
                      echo "${GITLEAKS_SHA256}  gitleaks.tgz" | sha256sum -c -
                      tar -xzf gitleaks.tgz gitleaks
                      install -m 0755 gitleaks /usr/local/bin/gitleaks
                      rm gitleaks.tgz gitleaks
                  - run: bash scripts/checks.sh --part ${{ matrix.part }}

              checks-result:
                if: always() && github.event_name == 'pull_request'
                needs: [checks]
                runs-on: ubuntu-24.04
                timeout-minutes: 5
                steps:
                  - name: Every part of the checks run passed
                    env:
                      RESULT: ${{ needs.checks.result }}
                    run: test "$RESULT" = success

              checks-main:
                if: github.event_name == 'push'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: bash scripts/checks.sh --main --part ${{ matrix.part }}

              backend-tests:
                if: github.event_name != 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                  DB_HOST: database
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: composer test:unit
                  - run: bash scripts/checks.sh --coverage coverage/clover.xml

              frontend-tests:
                if: github.event_name != 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: npm test

              smoke:
                if: github.event_name != 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                env:
                  DB_HOST: database
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: php artisan serve --env=ci &
                  - run: npx playwright test

              scheduled:
                if: github.event_name == 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                permissions:
                  contents: read
                  actions: read
                env:
                  GH_TOKEN: ${{ github.token }}
                  GH_REPO: ${{ github.repository }}
                  DB_HOST: database
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - name: Install gh
                    env:
                      GH_VERSION: 2.106.0
                      GH_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      command -v gh > /dev/null && exit 0
                      curl -sSfL -o gh.tgz "https://github.com/cli/cli/releases/download/v${GH_VERSION}/gh_${GH_VERSION}_linux_amd64.tar.gz"
                      echo "${GH_SHA256}  gh.tgz" | sha256sum -c -
                      tar -xzf gh.tgz --strip-components=2 "gh_${GH_VERSION}_linux_amd64/bin/gh"
                      install -m 0755 gh /usr/local/bin/gh
                      rm gh.tgz gh
                  - run: bash scripts/checks.sh --scheduled
                  - run: bash scripts/mutation.sh
            YAML . "\n";
    }

    /**
     * The runner form of the skeleton (ci/github-actions-runner.yml) with every placeholder filled: no container, the toolchain from
     * setup-php and setup-node, the services reached at 127.0.0.1 on a published port. The proof scenario PF03 compares it with the
     * skeleton.
     */
    public static function githubRunnerWorkflow(): string
    {
        return <<<'YAML'
            name: Kit checks

            on:
              pull_request:
                types: [opened, synchronize, reopened, edited]
              push:
                branches: ['main', 'develop']
              schedule:
                - cron: '17 3 * * *'

            concurrency:
              group: kit-${{ github.event_name }}-${{ github.event.pull_request.number || github.sha }}
              cancel-in-progress: ${{ github.event_name == 'pull_request' }}

            permissions:
              contents: read

            defaults:
              run:
                shell: bash

            jobs:
              checks:
                if: github.event_name == 'pull_request'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - name: Install gitleaks
                    if: matrix.part == 'repo'
                    env:
                      GITLEAKS_VERSION: 8.30.1
                      GITLEAKS_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      curl -sSfL -o gitleaks.tgz "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz"
                      echo "${GITLEAKS_SHA256}  gitleaks.tgz" | sha256sum -c -
                      tar -xzf gitleaks.tgz gitleaks
                      sudo install -m 0755 gitleaks /usr/local/bin/gitleaks
                      rm gitleaks.tgz gitleaks
                  - run: bash scripts/checks.sh --part ${{ matrix.part }}

              checks-result:
                if: always() && github.event_name == 'pull_request'
                needs: [checks]
                runs-on: ubuntu-24.04
                timeout-minutes: 5
                steps:
                  - name: Every part of the checks run passed
                    env:
                      RESULT: ${{ needs.checks.result }}
                    run: test "$RESULT" = success

              checks-main:
                if: github.event_name == 'push'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - run: bash scripts/checks.sh --main --part ${{ matrix.part }}

              backend-tests:
                if: github.event_name != 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                  DB_HOST: 127.0.0.1
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - '5432:5432'
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                      coverage: pcov
                  - run: composer install
                  - run: composer test:unit
                  - run: bash scripts/checks.sh --coverage coverage/clover.xml

              frontend-tests:
                if: github.event_name != 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: npm ci
                  - run: npm test

              smoke:
                if: github.event_name != 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                env:
                  DB_HOST: 127.0.0.1
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - '5432:5432'
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - run: php artisan serve --env=ci &
                  - run: npx playwright test

              scheduled:
                if: github.event_name == 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                permissions:
                  contents: read
                  actions: read
                env:
                  GH_TOKEN: ${{ github.token }}
                  GH_REPO: ${{ github.repository }}
                  DB_HOST: 127.0.0.1
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - '5432:5432'
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                      coverage: pcov
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - run: bash scripts/checks.sh --scheduled
                  - run: bash scripts/mutation.sh
            YAML . "\n";
    }

    public static function gitlabPipeline(): string
    {
        return <<<'YAML'
            image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000

            variables:
              GIT_DEPTH: "0"

            workflow:
              rules:
                - if: $CI_PIPELINE_SOURCE == "merge_request_event"
                - if: $CI_PIPELINE_SOURCE == "schedule"
                - if: $CI_PIPELINE_SOURCE == "push" && $CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH
                - if: $CI_PIPELINE_SOURCE == "push" && $CI_COMMIT_BRANCH == "develop"

            stages:
              - verify

            checks:
              stage: verify
              interruptible: true
              timeout: 20 minutes
              parallel:
                matrix:
                  - PART: [gate, static, guards, repo]
              rules:
                - if: $CI_PIPELINE_SOURCE == "merge_request_event"
              variables:
                CHECK_BASE: $CI_MERGE_REQUEST_TARGET_BRANCH_NAME
                CHECK_HEAD: $CI_COMMIT_SHA
              script:
                - composer install
                - curl -sSfL -o gitleaks.tgz https://example.test/gitleaks.tgz
                - bash scripts/checks.sh --part $PART

            checks-main:
              stage: verify
              timeout: 20 minutes
              parallel:
                matrix:
                  - PART: [gate, static, guards, repo]
              rules:
                - if: $CI_PIPELINE_SOURCE == "push" && ($CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH || $CI_COMMIT_BRANCH == "develop")
              script:
                - composer install
                - bash scripts/checks.sh --main --part $PART

            backend-tests:
              stage: verify
              interruptible: true
              timeout: 20 minutes
              variables:
                POSTGRES_PASSWORD: ci
                DB_HOST: database
              services:
                - name: postgres:17
                  alias: database
              rules:
                - if: $CI_PIPELINE_SOURCE != "schedule"
              script:
                - composer install
                - composer test:unit

            frontend-tests:
              stage: verify
              interruptible: true
              timeout: 20 minutes
              rules:
                - if: $CI_PIPELINE_SOURCE != "schedule"
              script:
                - composer install
                - npm test

            smoke:
              stage: verify
              interruptible: true
              timeout: 20 minutes
              variables:
                POSTGRES_PASSWORD: ci
                DB_HOST: database
              services:
                - name: postgres:17
                  alias: database
              rules:
                - if: $CI_PIPELINE_SOURCE != "schedule"
              script:
                - composer install
                - php artisan serve --env=ci &
                - npx playwright test

            scheduled:
              stage: verify
              timeout: 20 minutes
              variables:
                POSTGRES_PASSWORD: ci
                DB_HOST: database
              services:
                - name: postgres:17
                  alias: database
              rules:
                - if: $CI_PIPELINE_SOURCE == "schedule"
              script:
                - composer install
                - bash scripts/checks.sh --scheduled
                - bash scripts/mutation.sh
            YAML . "\n";
    }

    public static function codeOwners(): string
    {
        return <<<'OWNERS'
            # the guard files
            /scripts/ @lead
            /.github/workflows/ @lead
            /.gitlab-ci.yml @lead
            /tests/Support/Architecture/ @lead
            /tests/Unit/*ArchitectureTest.php @lead
            /.github/CODEOWNERS @lead
            OWNERS . "\n";
    }

    /**
     * @return list<string> the guard files of the example project
     */
    public static function guardedPaths(): array
    {
        return ['scripts/checks.sh', 'scripts/kit-config.sh', 'scripts/debt-caps.json', 'scripts/ci-ratchets.sh', '.github/workflows/kit-checks.yml', '.gitlab-ci.yml', 'tests/Support/Architecture/CiContractGuard.php', 'tests/Unit/CiContractArchitectureTest.php', '.github/CODEOWNERS'];
    }
}
