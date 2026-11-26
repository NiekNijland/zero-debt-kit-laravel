<?php

declare(strict_types=1);

use Tests\Support\Architecture\GuideRuleExamples;
use Tests\Support\Architecture\GuideRulesGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('rules and guides that disagree', [
    'a rule without a row' => [['unique-job-key', 'queue-name'], "A job declares uniqueId(). One key per record.\n", 'rule queue-name has no row'],
    'a row without a rule' => [[], "A job declares uniqueId(). One key per record.\n", 'row unique-job-key has no rule in the detector'],
    'words the guide lost' => [['unique-job-key'], "A job declares a key.\n", 'the guides no longer say "uniqueId()"'],
    'half of the words' => [['unique-job-key'], "A job declares uniqueId().\n", 'the guides no longer say "one key per record"'],
]);

dataset('tables that are not one row per rule', [
    'a row inside a fenced block' => ["| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n```\n| unique-job-key | `uniqueId()` | declare uniqueId() |\n```\n", 'rule unique-job-key has no row'],
    'a row inside an indented block' => ["| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n\n    | unique-job-key | `uniqueId()` | declare uniqueId() |\n", 'rule unique-job-key has no row'],
    'a rule id that appears twice' => [GuideRuleExamples::ROWS . "| unique-job-key | `a key` | declare a key |\n", 'row unique-job-key appears twice'],
]);

it('reads a table row only outside a code block and only once', function (string $rows, string $reason): void {
    $root = SourceSandbox::create(['docs/guards/rules.md' => $rows, 'AGENTS.md' => "A job declares uniqueId().\n"]);

    try {
        expect(implode("\n", new GuideRulesGuard($root . '/docs/guards/rules.md', [$root . '/AGENTS.md'], ['unique-job-key'])->violations()))->toContain($reason);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('tables that are not one row per rule');

it('fails a guide that lost the words of a rule, and a rule and a row that do not match', function (array $ruleIds, string $guide, string $reason): void {
    $root = SourceSandbox::create(['docs/guards/rules.md' => GuideRuleExamples::ROWS, 'AGENTS.md' => $guide]);

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
        'docs/guards/rules.md' => GuideRuleExamples::ROWS . "Text, and | not | a | row | of five cells\n",
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
