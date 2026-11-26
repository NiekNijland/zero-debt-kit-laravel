<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

/**
 * A proof fixture. The filled GitLab skeleton (ci/gitlab-ci.yml), a code-owners file that covers the guard files and the guard paths of
 * the example project. The operators are `{is}` and `{isnt}`, built in `expand()` (see CiHostExamples).
 */
final class CiGitlabExamples
{
    public static function gitlabPipeline(): string
    {
        return self::expand(<<<'YAML'
            image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000

            variables:
              GIT_DEPTH: "0"

            workflow:
              rules:
                - if: $CI_PIPELINE_SOURCE {is} "merge_request_event"
                - if: $CI_PIPELINE_SOURCE {is} "schedule"
                - if: $CI_PIPELINE_SOURCE {is} "push" && $CI_COMMIT_BRANCH {is} $CI_DEFAULT_BRANCH
                - if: $CI_PIPELINE_SOURCE {is} "push" && $CI_COMMIT_BRANCH {is} "develop"

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
                - if: $CI_PIPELINE_SOURCE {is} "merge_request_event"
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
                - if: $CI_PIPELINE_SOURCE {is} "push" && ($CI_COMMIT_BRANCH {is} $CI_DEFAULT_BRANCH || $CI_COMMIT_BRANCH {is} "develop")
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
                - if: $CI_PIPELINE_SOURCE {isnt} "schedule"
              script:
                - composer install
                - composer test:unit

            frontend-tests:
              stage: verify
              interruptible: true
              timeout: 20 minutes
              rules:
                - if: $CI_PIPELINE_SOURCE {isnt} "schedule"
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
                - if: $CI_PIPELINE_SOURCE {isnt} "schedule"
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
                - if: $CI_PIPELINE_SOURCE {is} "schedule"
              script:
                - composer install
                - bash scripts/checks.sh --scheduled
                - bash scripts/mutation.sh
            YAML . "\n");
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

    private static function expand(string $yaml): string
    {
        return str_replace(['{is}', '{isnt}'], ['=' . '=', '!' . '='], $yaml);
    }
}
