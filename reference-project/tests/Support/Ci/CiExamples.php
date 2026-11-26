<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

use LogicException;
use Tests\Support\Architecture\CiContractGuard;

final class CiExamples
{
    /**
     * @param list<string> $deployBranches
     */
    public static function pipelineGuard(array $deployBranches = ['main']): CiContractGuard
    {
        return new CiContractGuard('', '', $deployBranches, 'composer test:unit');
    }

    public static function pipeline(): string
    {
        return <<<'YAML'
            image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
            clone:
              depth: full
            pipelines:
              pull-requests:
                '**':
                  - parallel:
                      - step:
                          name: Checks gate
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --part gate
                      - step:
                          name: Checks static
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --part static
                      - step:
                          name: Checks guards
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --part guards
                      - step:
                          name: Checks repo
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --part repo
                      - step:
                          name: Backend tests
                          max-time: 20
                          script:
                            - composer test:unit
              branches:
                main:
                  - parallel:
                      - step:
                          name: Checks gate on the main branch
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --main --part gate
                      - step:
                          name: Checks static on the main branch
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --main --part static
                      - step:
                          name: Checks guards on the main branch
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --main --part guards
                      - step:
                          name: Checks repo on the main branch
                          max-time: 20
                          script:
                            - bash scripts/checks.sh --main --part repo
                      - step:
                          name: Backend tests
                          max-time: 20
                          script:
                            - composer test:unit
              custom:
                scheduled:
                  - step:
                      name: Scheduled
                      max-time: 30
                      script:
                        - bash scripts/checks.sh --scheduled
            YAML . "\n";
    }

    public static function checks(): string
    {
        return <<<'SH'
            # The one command.
            PARTS=(
                "gate::"
                "static::Format|Refactor|Analyser|Type coverage|Lint"
                "guards::Guard tests"
                "repo::Audit|Commit messages|Secret scan"
            )
            set -euo pipefail
            bash "$GUARD/ci-ratchets.sh" --guard scripts/ci-ratchets.sh
            bash scripts/ci-ratchets.sh
            bash scripts/debt-dashboard.sh $DASHBOARD_MODE --offline --details
            TASKS=(
                "Format::vendor/bin/pint --test"
                "Refactor::vendor/bin/rector --dry-run"
                "Analyser::vendor/bin/phpstan analyse"
                "Type coverage::vendor/bin/pest --type-coverage --min=100"
                "Lint::npx eslint . --max-warnings 0"
                "Guard tests::vendor/bin/pest --group arch"
            )
            if [ "$RUN" = "--main" ]; then
                TASKS+=(
                    "Audit::composer audit --locked --no-dev --format=json | bash scripts/audit-gate.sh"
                )
            else
                TASKS+=(
                    "Audit::composer audit --locked --format=json | bash scripts/audit-gate.sh"
                )
            fi
            TASKS+=(
                "Commit messages::bash scripts/ci-commit-messages.sh"
                "Secret scan::gitleaks git --redact --no-banner"
            )
            bash scripts/ci-parallel.sh "${TASKS[@]}"
            SH . "\n";
    }

    public static function kitConfig(): string
    {
        return "DEFAULT_BASE_BRANCH=\"develop\"\nREMOTE=\"origin\"\n";
    }

    public static function change(string $source, string $from, string $to): string
    {
        if (! str_contains($source, $from)) {
            throw new LogicException('The example does not hold the text it should change: ' . $from);
        }

        return str_replace($from, $to, $source);
    }

    /**
     * Changes the first `$from` that comes after `$marker`: one job of a workflow, where the same line sits in several jobs.
     */
    public static function changeAfter(string $source, string $marker, string $from, string $to): string
    {
        $at = strpos($source, $marker);
        $in = $at === false ? false : strpos($source, $from, $at);

        if ($in === false) {
            throw new LogicException('The example does not hold the text it should change after ' . $marker . ': ' . $from);
        }

        return substr($source, 0, $in) . $to . substr($source, $in + strlen($from));
    }
}
