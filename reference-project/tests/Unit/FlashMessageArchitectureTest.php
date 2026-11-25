<?php

declare(strict_types=1);

use Tests\Support\Architecture\FlashMessageGuard;
use Tests\Support\Architecture\PhpFileInspector;

dataset('flashes that are not one translated line', [
    'a literal text' => ["<?php\nnamespace App;\nuse Inertia\\Inertia;\nInertia::flash('success', 'Saved');\n", 'the message is not a translate call'],
    'a translation key' => ["<?php\nnamespace App;\nuse Inertia\\Inertia;\nInertia::flash('success', 'admin.products.toasts.saved');\n", 'the message is not a translate call'],
    'a variable' => ["<?php\nnamespace App;\nuse Inertia\\Inertia;\nInertia::flash('error', \$message);\n", 'the message is not a translate call'],
    'the message of an exception' => ["<?php\nnamespace App;\nuse Inertia\\Inertia;\nInertia::flash('error', \$e->getMessage());\n", 'the message is not a translate call'],
    'an array as message' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash('success', ['title' => Copy::translate('a.b')]);\n", 'the message is not a translate call'],
    'a Data object as message' => ["<?php\nnamespace App;\nuse Inertia\\Inertia;\nInertia::flash('success', new ToastData('a.b'));\n", 'the message is not a translate call'],
    'a branch that is not translated' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash('success', \$one ? Copy::translate('a.one') : 'Saved');\n", 'the message is not a translate call'],
    'text glued to a translation' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash('success', Copy::translate('a.b') . ' for ' . \$name);\n", 'the message is not a translate call'],
    'a helper that returns raw text' => ["<?php\nnamespace App;\nuse Inertia\\Inertia;\nfinal class Line { public static function for(string \$reason): string { return 'Failed: ' . \$reason; } }\nInertia::flash('error', Line::for('x'));\n", 'the message is not a translate call'],
    'another method of the translation facade' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash('success', Copy::clearCache());\n", 'the message is not a translate call'],
    'an unknown type' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash('warning', Copy::translate('a.b'));\n", "takes 'success', 'error' or 'info'"],
    'a type from a variable' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash(\$type, Copy::translate('a.b'));\n", "takes 'success', 'error' or 'info'"],
    'the array form' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash(['success' => Copy::translate('a.b')]);\n", "takes 'success', 'error' or 'info'"],
    'a third argument' => ["<?php\nnamespace App;\nuse App\\Modules\\Copy\\Facades\\Copy;\nuse Inertia\\Inertia;\nInertia::flash('success', Copy::translate('a.b'), true);\n", "takes 'success', 'error' or 'info'"],
    'the fully qualified facade' => ["<?php\nnamespace App;\n\\Inertia\\Inertia::flash('success', 'Saved');\n", 'the message is not a translate call'],
    'the session' => ["<?php\nnamespace App;\nsession()->flash('success', __('a.b'));\n", 'does not go through Inertia'],
    'the session of the request' => ["<?php\nnamespace App;\n\$request->session()->flash('status', 'saved');\n", 'does not go through Inertia'],
    'the Session facade' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Session;\nSession::flash('success', __('a.b'));\n", 'does not go through Inertia'],
]);

it('detects a flash that is not a known type with one translated line', function (string $source, string $reason): void {
    $violations = new FlashMessageGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain("Inertia::flash('success', Copy::translate('area.toasts.saved'))");
})->with('flashes that are not one translated line');

it('accepts a translate call, a choice between translate calls and a helper that returns one', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use App\Modules\Copy\Facades\Copy;
        use App\Modules\Copy\Facades\Copy as CopyFacade;
        use Inertia\Inertia;
        final class FailureLine
        {
            public static function forReason(string $reason): string { return Copy::translate('failures.' . $reason); }
            public static function for(Failure $failure): string { return self::forReason($failure->reason); }
        }
        Inertia::flash('success', Copy::translate('a.saved'));
        Inertia::flash('error', CopyFacade::translate('a.failed', ['name' => $name]));
        Inertia::flash('info', $this->translationService->translate('a.queued'));
        Inertia::flash('error', __('errors.page_expired', [], $locale))->back(303);
        Inertia::flash('success', $deleted === 1 ? Copy::translate('a.deleted_one') : Copy::translate('a.deleted', ['count' => $deleted]));
        Inertia::flash('success', Copy::translate('a.created') . '. ' . Copy::translate('a.created_body'));
        Inertia::flash('error', match ($reason) { 'a' => Copy::translate('a.a'), default => Copy::translate('a.b') });
        Inertia::flash('error', FailureLine::for($failure));
        $toast->flashed();
        PHP;

    expect(new FlashMessageGuard(new PhpFileInspector(), dirname(__DIR__, 2))->inSource($source))->toBe([]);
});

it('flashes one translated line of a known type everywhere in app', function (): void {
    expect(new FlashMessageGuard(new PhpFileInspector(), dirname(__DIR__, 2))->violations())->toBe([]);
});
