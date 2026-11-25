<?php

declare(strict_types=1);

use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Copy\Commands\SeedCopyCommand;
use Tests\Support\FrontendTranslationKeys;
use Tests\Support\FrontendTranslationLiterals;
use Tests\Support\Narrow;

it('seeds the same keys in every language', function (): void {
    $translations = FrontendTranslationKeys::seeded();
    $onlyEn = array_keys(array_diff_key($translations['en'], $translations['fr']));
    $onlyFr = array_keys(array_diff_key($translations['fr'], $translations['en']));

    expect($onlyEn)->toBe([], 'Only in en: ' . implode(', ', $onlyEn))
        ->and($onlyFr)->toBe([], 'Only in fr: ' . implode(', ', $onlyFr));
});

it('seeds no empty text, because the frontend shows the key for an empty text', function (): void {
    $empty = [];

    foreach (FrontendTranslationKeys::seeded() as $locale => $translations) {
        foreach ($translations as $key => $text) {
            if ($text === '') {
                $empty[] = "{$locale} {$key}";
            }
        }
    }

    expect($empty)->toBe([], 'Empty: ' . implode(', ', $empty));
});

it('gives every text the same placeholders in every language', function (): void {
    $translations = FrontendTranslationKeys::seeded();
    $different = [];

    foreach ($translations['en'] as $key => $text) {
        if (isset($translations['fr'][$key]) && FrontendTranslationKeys::placeholders($text) !== FrontendTranslationKeys::placeholders($translations['fr'][$key])) {
            $different[] = $key;
        }
    }

    expect($different)->toBe([], 'Different placeholders: ' . implode(', ', $different));
});

it('gives every text the same singular and plural forms in every language', function (): void {
    $translations = FrontendTranslationKeys::seeded();
    $different = [];

    foreach ($translations['en'] as $key => $text) {
        if (isset($translations['fr'][$key]) && substr_count($text, '|') !== substr_count($translations['fr'][$key], '|')) {
            $different[] = $key;
        }
    }

    expect($different)->toBe([], 'Different plural forms: ' . implode(', ', $different));
});

it('seeds no key that no code reads', function (): void {
    $unused = FrontendTranslationKeys::unused();

    expect($unused)->toBe([], "Delete from resources/translations (the block of every language) and add a migration that deletes the rows:\n" . implode("\n", $unused));
});

it('seeds every key the frontend passes to the translator in every language', function (): void {
    $translations = FrontendTranslationKeys::seeded();
    $missing = [];

    foreach (FrontendTranslationKeys::keys() as $key => $locations) {
        foreach (['en', 'fr'] as $locale) {
            if (! array_key_exists($key, $translations[$locale])) {
                $missing[] = "{$key} ({$locale}) in " . implode(', ', $locations);
            }
        }
    }

    expect(count(FrontendTranslationKeys::keys()))->toBeGreaterThan(0)
        ->and($missing)->toBe([], "Add to resources/translations (the block of every language):\n" . implode("\n", $missing));
});

it('seeds at least one key for every key the frontend builds from a fixed beginning', function (): void {
    $translations = FrontendTranslationKeys::seeded();
    $unmatched = [];

    foreach (FrontendTranslationKeys::templates() as $template => $locations) {
        if (str_starts_with($template, FrontendTranslationKeys::PLACEHOLDER)) {
            continue;
        }

        foreach (['en', 'fr'] as $locale) {
            if (preg_grep(FrontendTranslationKeys::pattern($template), array_keys($translations[$locale])) === []) {
                $unmatched[] = "{$template} ({$locale}) in " . implode(', ', $locations);
            }
        }
    }

    expect(FrontendTranslationKeys::templates())->not->toBeEmpty()
        ->and($unmatched)->toBe([], "No seeded key matches:\n" . implode("\n", $unmatched));
});

it('builds a key without a fixed beginning only where the beginning is passed in', function (): void {
    $allowed = [
        'resources/js/Components/features/notifications/notification-item.tsx' => 'the notification key comes from the server',
        'resources/js/Components/features/products/product-form/field-error.tsx' => 'the messageKey of the field error names the text',
    ];
    $files = [];

    foreach (FrontendTranslationKeys::templates() as $template => $locations) {
        if (str_starts_with($template, FrontendTranslationKeys::PLACEHOLDER)) {
            foreach ($locations as $location) {
                $files[] = (string) preg_replace('/:\d+$/', '', $location);
            }
        }
    }

    $files = array_values(array_unique($files));
    sort($files);

    expect($files)->toBe(array_keys($allowed));
});

it('seeds a key for every value behind a template key of the frontend', function (string $pattern, array $values): void {
    $translations = FrontendTranslationKeys::seeded();
    $missing = [];

    foreach (['en', 'fr'] as $locale) {
        foreach ($values as $value) {
            $key = sprintf($pattern, Narrow::string($value));

            if (! array_key_exists($key, $translations[$locale])) {
                $missing[] = "{$key} ({$locale})";
            }
        }
    }

    expect(array_keys(FrontendTranslationKeys::templates()))->toContain(str_replace('%s', FrontendTranslationKeys::PLACEHOLDER, $pattern))
        ->and($missing)->toBe([], 'Missing: ' . implode(', ', $missing));
})->with([
    'orders.status.%s' => ['orders.status.%s', array_column(OrderStatus::cases(), 'value')],
    'invoices.status.%s' => ['invoices.status.%s', array_column(InvoiceStatus::cases(), 'value')],
    'common.languages.%s' => ['common.languages.%s', array_keys(SeedCopyCommand::LOCALE_NAMES)],
]);

it('reads only the keys that go to the translator', function (): void {
    $source = <<<'TSX'
        const { translation: t } = useTranslations();
        const LABEL_KEYS: Record<string, string> = { "number-range": "map.value", other: cond ? "map.yes" : "map.no" };
        const EXAMPLE_KEYS = ["array.first", "array.second"];
        const VIEW_STORAGE_KEY = "storage.not_a_key";
        const SAVE_FAILED_KEY = "constant.failed";
        export const SOLD_OUT_MESSAGE = "constant.message";
        t(blocker ?? (signedIn ? "group.yes" : "group.no"));
        t(("group.double"));
        t(format("nested.call"));
        t("call.literal");
        t('call.single', { count });
        t(`call.template`);
        translation("call.translation");
        translate("call.translate");
        t(open ? "call.yes" : "call.no");
        t(MAP[kind] ?? "call.fallback");
        t(kind === "compared.value" ? "call.compared" : "call.other");
        t(`call.family.${kind}`);
        t(`${prefix}.title`);
        t("call.concatenated." + kind);
        t(MAP["nested.index"]);
        i18n.t("member.call");
        const item = { labelKey: "prop.label", dataKey: "count", routeKey: "abc" };
        <Field labelKey="jsx.label" placeholderKey={"jsx.braced"} hint="jsx.hint" />;
        TSX;

    $found = array_map(
        static fn (array $literal): string => $literal[0] . ' ' . $literal[1],
        FrontendTranslationLiterals::literalsIn($source),
    );
    sort($found);

    expect($found)->toBe([
        'keys array.first',
        'keys array.second',
        'keys call.compared',
        'keys call.fallback',
        'keys call.literal',
        'keys call.no',
        'keys call.other',
        'keys call.single',
        'keys call.template',
        'keys call.translate',
        'keys call.translation',
        'keys call.yes',
        'keys constant.failed',
        'keys constant.message',
        'keys group.double',
        'keys group.no',
        'keys group.yes',
        'keys jsx.braced',
        'keys jsx.label',
        'keys map.no',
        'keys map.value',
        'keys map.yes',
        'keys prop.label',
        'templates *.title',
        'templates call.family.*',
    ]);
});
