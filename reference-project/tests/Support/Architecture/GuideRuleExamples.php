<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * The example data of the guide-rules guard test: a table of one rule, as the guard reads it.
 */
final class GuideRuleExamples
{
    public const string ROWS = "| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n| unique-job-key | `uniqueId()`; one key per record | declare uniqueId() |\n";
}
