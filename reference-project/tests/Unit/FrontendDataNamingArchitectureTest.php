<?php

declare(strict_types=1);

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tests\Support\Architecture\FrontendDataNamingGuard;
use Tests\Support\Architecture\PhpFileInspector;

uses()->group('arch');

it('detects a snake_case property of a Data class the frontend reads', function (): void {
    $promoted = new #[TypeScript] class() extends Data
    {
        public function __construct(public string $first_name = '', public string $lastName = '')
        {
        }
    };
    $declared = new #[TypeScript] class() extends Data
    {
        public ?int $unit_price = null;

        #[MapOutputName('created_at')]
        public ?string $created_at = null;

        public ?string $Sku = null;
    };

    expect(FrontendDataNamingGuard::misnamedProperties($promoted::class))->toHaveCount(1)
        ->and(FrontendDataNamingGuard::misnamedProperties($promoted::class)[0])->toContain('::$first_name is not camelCase', "#[MapOutputName('snake_case')]")
        ->and(FrontendDataNamingGuard::misnamedProperties($declared::class))->toHaveCount(3)
        ->and(implode(' ', FrontendDataNamingGuard::misnamedProperties($declared::class)))->toContain('::$unit_price', '::$created_at', '::$Sku');
});

it('accepts camelCase properties, a snake_case key on the wire and a class the frontend never reads', function (): void {
    $frontend = new #[TypeScript] class() extends Data
    {
        public static string $wire_format = 'json';

        #[MapOutputName('unit_price')]
        public ?int $unitPrice = null;

        public function __construct(public string $firstName = '', public bool $canEdit2 = false, protected string $raw_payload = '')
        {
        }
    };
    $api = new class() extends Data
    {
        public ?string $tracking_code = null;
    };
    $plain = new #[TypeScript] class()
    {
        public ?string $tracking_code = null;
    };

    expect(FrontendDataNamingGuard::misnamedProperties($frontend::class))->toBe([])
        ->and(FrontendDataNamingGuard::misnamedProperties($api::class))->toBe([])
        ->and(FrontendDataNamingGuard::misnamedProperties($plain::class))->toBe([]);
});

it('names every property of a Data class with a TypeScript type in camelCase', function (): void {
    expect(FrontendDataNamingGuard::violations(new PhpFileInspector(), dirname(__DIR__, 2)))->toBe([]);
});
