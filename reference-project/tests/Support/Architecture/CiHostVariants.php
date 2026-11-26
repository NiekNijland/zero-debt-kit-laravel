<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The guards of the GitHub and GitLab forms as the thin tests build them, and the changes of the skeleton examples that the
 * failing cases make: one job taken out, the services of another engine, a project without a database.
 */
final class CiHostVariants
{
    private const string POSTGRES_SERVICE = "        image: postgres:17\n        env:\n          POSTGRES_PASSWORD: ci\n        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n";

    /**
     * @param list<string> $hardened other workflow files the owner has the guard read
     */
    public static function githubGuard(string $databaseImage = 'postgres:17', bool $frontend = true, array $hardened = []): GithubWorkflowGuard
    {
        return new GithubWorkflowGuard('', '', 'kit-checks.yml', ['main', 'develop'], 'composer test:unit', $databaseImage, $frontend, 'playwright', hardenedWorkflows: $hardened);
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

    public static function deployWorkflow(): string
    {
        return "name: Deploy\non:\n  push:\n    branches: [main]\njobs:\n  deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - uses: actions/checkout@v4\n      - uses: appleboy/ssh-action@v1.0.3\n        with:\n          key: \${{ secrets.DEPLOY_KEY }}\n";
    }
}
