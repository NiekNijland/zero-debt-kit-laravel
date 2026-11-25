<?php

declare(strict_types=1);

use Tests\Support\Architecture\GuideRulesGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

const RULE_ROWS = "| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n| unique-job-key | `uniqueId()`; one key per record | declare uniqueId() |\n";

dataset('rules and guides that disagree', [
    'a rule without a row' => [['unique-job-key', 'queue-name'], "A job declares uniqueId(). One key per record.\n", 'rule queue-name has no row'],
    'a row without a rule' => [[], "A job declares uniqueId(). One key per record.\n", 'row unique-job-key has no rule in the detector'],
    'words the guide lost' => [['unique-job-key'], "A job declares a key.\n", 'the guides no longer say "uniqueId()"'],
    'half of the words' => [['unique-job-key'], "A job declares uniqueId().\n", 'the guides no longer say "one key per record"'],
]);

it('fails a guide that lost the words of a rule, and a rule and a row that do not match', function (array $ruleIds, string $guide, string $reason): void {
    $root = SourceSandbox::create(['docs/guards/rules.md' => RULE_ROWS, 'AGENTS.md' => $guide]);

    try {
        $violations = new GuideRulesGuard($root . '/docs/guards/rules.md', [$root . '/AGENTS.md'], Narrow::list($ruleIds))->violations();

        expect($violations)->not->toBe([])
            ->and(implode("\n", $violations))->toContain($reason);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('rules and guides that disagree');

it('accepts words spread over two guides, other spacing and a table with its header rows', function (): void {
    $root = SourceSandbox::create([
        'docs/guards/rules.md' => RULE_ROWS . "Text, and | not | a | row | of five cells\n",
        'AGENTS.md' => "A job declares\nuniqueId().\n",
        'docs/review-rubric.md' => "A job holds one   key per\nrecord.\n",
    ]);

    try {
        expect(new GuideRulesGuard($root . '/docs/guards/rules.md', [$root . '/AGENTS.md', $root . '/docs/review-rubric.md'], ['unique-job-key'])->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps the guide words of the unique-job rule', function (): void {
    $root = dirname(__DIR__, 2);

    expect(new GuideRulesGuard($root . '/docs/guards/unique-job.md', [$root . '/docs/review-rubric.md'], ['unique-job-key'])->violations())->toBe([]);
});
