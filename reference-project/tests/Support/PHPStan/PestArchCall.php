<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use LogicException;
use Pest\Expectation;
use Pest\PendingCalls\TestCall;
use Pest\Preset;

/**
 * @mixin TestCall
 *
 * @internal The type PHPStan gives arch(): Pest forwards expect() and preset() to the generated test case.
 */
final readonly class PestArchCall
{
    /**
     * @return Expectation<mixed>
     */
    public function expect(mixed $value): Expectation
    {
        throw new LogicException('Only PHPStan reads this type.');
    }

    public function preset(): Preset
    {
        throw new LogicException('Only PHPStan reads this type.');
    }
}
