<?php

declare(strict_types=1);

use Tests\Support\Architecture\CodeOwnersGuard;
use Tests\Support\Ci\CiExamples;
use Tests\Support\Ci\CiHostExamples;

uses()->group('arch');

// Point 22 of the contract in 04-guards.md C4: the files that carry the guards have an owner.

dataset('code-owners files that leave a guard file without a human review', [
    'the scripts not covered' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", ''), 'scripts/debt-caps.json is covered by no code-owners rule'],
    'the workflow directory not covered' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/.github/workflows/ @lead\n", ''), '.github/workflows/kit-checks.yml is covered by no'],
    'the guard folder not covered' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/tests/Support/Architecture/ @lead\n", ''), 'tests/Support/Architecture/CiContractGuard.php is covered by no'],
    'the file not covering itself' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/.github/CODEOWNERS @lead\n", ''), '.github/CODEOWNERS is covered by no'],
    'a later rule that takes a script out of the review' => [fn (): string => CiHostExamples::codeOwners() . "/scripts/kit-config.sh\n", 'scripts/kit-config.sh is matched last by a rule with no owner'],
    'a pattern that matches nothing' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", "/script/ @lead\n"), 'scripts/checks.sh is covered by no'],
    'a negated rule' => [fn (): string => CiHostExamples::codeOwners() . "!/scripts/checks.sh\n", 'negates a rule'],
    'a bot as the only owner' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", "/scripts/ @release-bot\n"), 'scripts/checks.sh is owned by a bot only'],
    'an automation account as the only owner' => [fn (): string => CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", "/scripts/ @renovate[bot]\n"), 'scripts/checks.sh is owned by a bot only'],
]);

it('detects a code-owners file that leaves a guard file without the review of its owner', function (Closure $codeOwners, string $reason): void {
    $violations = new CodeOwnersGuard('', CiHostExamples::guardedPaths())->inCodeOwners($codeOwners());

    expect(implode("\n", $violations))->toContain($reason);
})->with('code-owners files that leave a guard file without a human review');

it('accepts a code-owners file that covers every guard file, and a host that enforces the owners outside the repository', function (): void {
    $withBot = CiExamples::change(CiHostExamples::codeOwners(), "/scripts/ @lead\n", "/scripts/ @lead @release-bot\n");

    expect(new CodeOwnersGuard('', CiHostExamples::guardedPaths())->inCodeOwners(CiHostExamples::codeOwners()))->toBe([])
        ->and(new CodeOwnersGuard('', CiHostExamples::guardedPaths())->inCodeOwners($withBot))->toBe([])
        ->and(new CodeOwnersGuard('/no/such/CODEOWNERS', CiHostExamples::guardedPaths(), hostEnforced: true)->violations())->toBe([])
        ->and(implode("\n", new CodeOwnersGuard('/no/such/CODEOWNERS', CiHostExamples::guardedPaths())->violations()))->toContain('no code-owners file');
});

it('covers every guard file of the repository with a code owner', function (): void {
    expect(new CodeOwnersGuard(dirname(__DIR__, 2) . '/.github/CODEOWNERS', CiHostExamples::guardedPaths())->violations())->toBe([]);
});
