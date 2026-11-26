<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

use Tests\Support\Architecture\GithubWorkflowGuard;
use Tests\Support\Architecture\GitlabPipelineGuard;

/**
 * The guards of the GitHub and GitLab forms as the thin tests build them, and the changes of the skeleton examples that the
 * failing cases make: one job taken out, the services of another engine, a project without a database.
 */
final class CiHostVariants
{
    private const string POSTGRES_SERVICE = "        image: postgres:17\n        env:\n          POSTGRES_PASSWORD: ci\n        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n";

    /**
     * @param list<string> $hardened other workflow files the owner has the guard read
     * @param list<string> $auxiliary other workflow files that run the checks script on purpose
     */
    public static function githubGuard(string $databaseImage = 'postgres:17', bool $frontend = true, array $hardened = [], string $backendTestScript = '', array $auxiliary = []): GithubWorkflowGuard
    {
        return new GithubWorkflowGuard('', '', 'kit-checks.yml', ['main', 'develop'], 'composer test:unit', $databaseImage, $frontend, 'playwright', hardenedWorkflows: $hardened, backendTestScript: $backendTestScript, auxiliaryWorkflows: $auxiliary);
    }

    /**
     * @param array<string, string>|null $files the workflow files by name; the kit's file alone when null
     */
    public static function githubViolations(string $workflow, ?GithubWorkflowGuard $guard = null, ?array $files = null): string
    {
        return implode("\n", ($guard ?? self::githubGuard())->inWorkflows($files ?? ['kit-checks.yml' => $workflow], CiExamples::checks()));
    }

    public static function gitlabGuard(string $databaseImage = 'postgres:17'): GitlabPipelineGuard
    {
        return new GitlabPipelineGuard('', '', ['main', 'develop'], 'composer test:unit', $databaseImage);
    }

    public static function inGithubJob(string $job, string $from, string $to): string
    {
        return CiExamples::changeAfter(CiHostExamples::githubWorkflow(), "\n  " . $job . ":\n", $from, $to);
    }

    public static function inGitlabJob(string $job, string $from, string $to): string
    {
        return CiExamples::changeAfter(CiHostExamples::gitlabPipeline(), "\n" . $job . ":\n", $from, $to);
    }

    public static function withoutGithubJob(string $job): string
    {
        $source = CiHostExamples::githubWorkflow();
        $at = strpos($source, "\n  " . $job . ":\n");
        $next = preg_match('/\n  [a-z-]+:\n/', $source, $match, PREG_OFFSET_CAPTURE, ($at === false ? 0 : $at) + 3) === 1 ? $match[0][1] : strlen($source);

        return $at === false ? $source : substr($source, 0, $at) . substr($source, $next);
    }

    /**
     * The workflow with the service containers of another engine than the postgres of the example.
     */
    public static function githubWorkflowFor(string $engine): string
    {
        $service = match ($engine) {
            'mysql' => "        image: mysql:8.4\n        env:\n          MYSQL_ROOT_PASSWORD: ci\n        options: >-\n          --health-cmd \"mysqladmin ping -h 127.0.0.1 -uroot -pci\"\n",
            'mariadb' => "        image: mariadb:11.4\n        env:\n          MARIADB_ROOT_PASSWORD: ci\n        options: >-\n          --health-cmd \"healthcheck.sh --connect --innodb_initialized\"\n",
            default => self::POSTGRES_SERVICE,
        };

        return str_replace(self::POSTGRES_SERVICE, $service, CiHostExamples::githubWorkflow());
    }

    /**
     * The workflow of a project that has no database: no service container and no DB_HOST.
     */
    public static function githubWorkflowWithoutDatabase(): string
    {
        $source = str_replace("    services:\n      database:\n" . self::POSTGRES_SERVICE . "          --health-interval 5s --health-timeout 5s --health-retries 12\n", '', CiHostExamples::githubWorkflow());

        return str_replace("      DB_HOST: database\n", '', str_replace("    env:\n      DB_HOST: database\n", '', $source));
    }

    /**
     * The owner exception of ci/github-actions.yml for a project without an image of its own: the jobs run on the runner (no
     * container), the database is a service with a published port that the job reaches on 127.0.0.1, the smoke job fetches the
     * application under test, and the coverage line ends the Composer script of the backend test command.
     */
    public static function githubWorkflowOnRunner(): string
    {
        $container = "    container:\n      image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000\n";
        $source = str_replace(
            [$container, "        image: postgres:17\n", 'DB_HOST: database', "      - run: bash scripts/checks.sh --coverage coverage/clover.xml\n"],
            ['', "        image: postgres:17\n        ports:\n          - 5432:5432\n", 'DB_HOST: 127.0.0.1', ''],
            CiHostExamples::githubWorkflow(),
        );

        return CiExamples::changeAfter(
            CiExamples::changeAfter($source, "\n  smoke:\n", '      DB_HOST: 127.0.0.1', "      DB_HOST: 127.0.0.1\n      SMOKE_BASE_URL: http://127.0.0.1:8000"),
            "\n  smoke:\n",
            "      - run: npx playwright test\n",
            "      - run: curl -sS --retry 30 --retry-connrefused -o /dev/null \"\$SMOKE_BASE_URL/\"\n      - run: npx playwright test\n",
        );
    }

    public static function canaryWorkflow(): string
    {
        return "name: Canary\non:\n  schedule:\n    - cron: '41 4 * * *'\n  workflow_dispatch:\npermissions:\n  contents: read\njobs:\n  canary:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 30\n    steps:\n      - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1\n        with:\n          fetch-depth: 0\n      - run: composer install\n      - run: bash scripts/checks.sh --main --part static\n";
    }

    public const string RUNNER_TEST_SCRIPT = "php -d memory_limit=-1 vendor/bin/pest --coverage-clover=coverage/clover.xml\nbash scripts/checks.sh --coverage coverage/clover.xml";

    public static function deployWorkflow(): string
    {
        return "name: Deploy\non:\n  push:\n    branches: [main]\njobs:\n  deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - uses: actions/checkout@v4\n      - uses: appleboy/ssh-action@v1.0.3\n        with:\n          key: \${{ secrets.DEPLOY_KEY }}\n";
    }
}
