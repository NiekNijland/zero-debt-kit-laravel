<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The owner exception of `04-guards.md` C4 for an auxiliary workflow that reads a secret: a sync run that opens a pull request with a
 * token of its own. The generic hazard rule fails any workflow that a pull request or the schedule starts and that reads a secret; this
 * rule replaces it for a workflow the owner names, and only when the workflow cannot be started by a pull request:
 *
 * - every trigger is `schedule` or `workflow_dispatch` (a schedule and a manual run use the copy on the default branch only),
 * - every secret the file reads is named in the declaration of that file, and every declared secret is read (a stale name fails),
 * - the workflow token (`secrets.GITHUB_TOKEN`) is no secret here: the reader that finds the names leaves it out.
 */
final readonly class AuxiliarySecretRule
{
    private const array SAFE_TRIGGERS = ['schedule', 'workflow_dispatch'];

    /**
     * @param list<string> $declared the secret names the owner declared for this file, for example `['KIT_SYNC_TOKEN']`
     */
    public function __construct(private array $declared)
    {
    }

    /**
     * @param list<string> $triggers the event names under `on:` of the workflow
     * @param list<string> $secrets the names read through `secrets.<NAME>`, without the workflow token
     * @return list<string>
     */
    public function violations(array $triggers, array $secrets): array
    {
        $violations = [];

        foreach (array_diff($triggers, self::SAFE_TRIGGERS) as $trigger) {
            $violations[] = "a workflow that reads a secret is started by `{$trigger}`: only `schedule` and `workflow_dispatch` run the copy on the default branch";
        }

        foreach (array_diff($secrets, $this->declared) as $name) {
            $violations[] = "the workflow reads `secrets.{$name}`, which the owner did not declare for this file";
        }

        foreach (array_diff($this->declared, $secrets) as $name) {
            $violations[] = "the declared secret `{$name}` is not read by the workflow: remove it from the declaration";
        }

        return $violations;
    }
}
