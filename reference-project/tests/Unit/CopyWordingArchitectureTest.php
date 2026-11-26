<?php

declare(strict_types=1);

use Tests\Support\Architecture\CopyWordingGuard;

uses()->group('arch');

dataset('copy with a word the interface never uses', [
    'cart in a sentence' => ['Your cart is empty.', '"cart": write "basket"'],
    'cart at the start of a sentence' => ['Cart total', '"cart": write "basket"'],
    'carts' => ['Abandoned carts are emptied after a week.', '"carts": write "baskets"'],
    'a compound with a hyphen' => ['An add-to-cart button', '"cart": write "basket"'],
    'the possessive' => ["The cart's total", '"cart": write "basket"'],
    'in capitals' => ['CART', '"cart": write "basket"'],
    'next to a placeholder' => ['{{ cart }} is a cart', '"cart": write "basket"'],
    'next to a placeholder of lang' => [':cart is not available in this cart.', '"cart": write "basket"'],
    'after a colon' => ['Note: cart', '"cart": write "basket"'],
]);

it('detects cart and carts as a whole word in copy', function (string $text, string $violation): void {
    expect(CopyWordingGuard::bannedWords($text))->toBe([$violation]);
})->with('copy with a word the interface never uses');

it('accepts placeholders and words that only contain the letters', function (string $text): void {
    expect(CopyWordingGuard::bannedWords($text))->toBe([]);
})->with([
    'a placeholder' => ['Added to {{cart}}'],
    'a placeholder of lang' => ['The :cart field is required for :Carts.'],
    'a placeholder with spaces' => ['The new price list applies from {{ date }} for {{ carts }}.'],
    'a longer word' => ['The cartridge ships in a carton'],
    'basket' => ['Your basket is empty.'],
]);

it('never says cart in the copy of resources/translations and lang', function (): void {
    expect(CopyWordingGuard::violations())->toBe([]);
});
