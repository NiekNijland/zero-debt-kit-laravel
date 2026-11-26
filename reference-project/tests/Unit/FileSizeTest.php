<?php

declare(strict_types=1);

use Tests\Support\Architecture\FileSizeGuard;

uses()->group('arch');

it('keeps every file in app, resources and tests at 400 lines or fewer', function (): void {
    expect(FileSizeGuard::oversized(dirname(__DIR__, 2)))->toBe([]);
});
