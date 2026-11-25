<?php

declare(strict_types=1);

use App\Support\Json;

it('decodes JSON to arrays and scalars', function (string $json, mixed $decoded): void {
    expect(Json::decodeOrNull($json))->toBe($decoded);
})->with([
    'an object' => ['{"sku":"MUG-001","quantity":3}', ['sku' => 'MUG-001', 'quantity' => 3]],
    'a list' => ['[1,"two",null]', [1, 'two', null]],
    'a string' => ['"MUG-001"', 'MUG-001'],
    'the literal null' => ['null', null],
]);

it('answers null for text that is not JSON', function (string $text): void {
    expect(Json::decodeOrNull($text))->toBeNull();
})->with([
    'empty' => [''],
    'plain text' => ['The carrier did not answer.'],
    'a cut-off object' => ['{"sku":"MUG-001"'],
]);

it('encodes a value to JSON', function (): void {
    expect(Json::encodeOrNull(['sku' => 'MUG-001', 'quantity' => 3]))->toBe('{"sku":"MUG-001","quantity":3}');
});

it('answers null for a value that cannot be encoded', function (): void {
    expect(Json::encodeOrNull(['name' => "\xB1\x31"]))->toBeNull();
});
