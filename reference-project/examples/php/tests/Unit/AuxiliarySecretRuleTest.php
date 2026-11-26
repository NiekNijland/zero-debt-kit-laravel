<?php

declare(strict_types=1);

use Tests\Support\Architecture\AuxiliarySecretRule;

uses()->group('arch');

it('passes a sync workflow that only a schedule or a manual run starts and that reads its declared secret', function (): void {
    $rule = new AuxiliarySecretRule(['KIT_SYNC_TOKEN']);

    expect($rule->violations(['schedule', 'workflow_dispatch'], ['KIT_SYNC_TOKEN']))->toBe([])
        ->and($rule->violations(['workflow_dispatch'], ['KIT_SYNC_TOKEN']))->toBe([]);
});

it('fails a workflow that a pull request, a push or another workflow starts', function (): void {
    $rule = new AuxiliarySecretRule(['KIT_SYNC_TOKEN']);

    expect($rule->violations(['schedule', 'pull_request'], ['KIT_SYNC_TOKEN']))->toHaveCount(1)
        ->and($rule->violations(['push'], ['KIT_SYNC_TOKEN'])[0])->toContain('started by `push`')
        ->and($rule->violations(['workflow_run'], ['KIT_SYNC_TOKEN']))->toHaveCount(1);
});

it('fails a secret the owner did not declare, and a declared secret the file no longer reads', function (): void {
    $rule = new AuxiliarySecretRule(['KIT_SYNC_TOKEN']);

    expect($rule->violations(['schedule'], ['KIT_SYNC_TOKEN', 'DEPLOY_KEY']))->toBe(['the workflow reads `secrets.DEPLOY_KEY`, which the owner did not declare for this file'])
        ->and($rule->violations(['schedule'], []))->toBe(['the declared secret `KIT_SYNC_TOKEN` is not read by the workflow: remove it from the declaration']);
});
