<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Invoices\Jobs\ExportInvoiceJob;
use App\Modules\Invoices\Notifications\InvoiceSentNotification;
use App\Modules\Orders\Notifications\OrderShippedNotification;
use App\Modules\Users\Mail\WelcomeMail;
use Illuminate\Support\ServiceProvider;
use Override;

final class MovedClassServiceProvider extends ServiceProvider
{
    // Queued payloads (jobs, failed_jobs, Horizon) store the class name they were dispatched under.
    // Keep an old name here until no job under it can still be queued, delayed or retried.
    // One line per moved job, mail or notification, for one release.
    public const array ALIASES = [
        'App\Jobs\ExportInvoiceJob' => ExportInvoiceJob::class,
        'App\Mail\WelcomeMail' => WelcomeMail::class,
        'App\Notifications\InvoiceSentNotification' => InvoiceSentNotification::class,
        'App\Notifications\OrderShippedNotification' => OrderShippedNotification::class,
    ];

    #[Override]
    public function register(): void
    {
        foreach (self::ALIASES as $old => $new) {
            if (! class_exists($old, false)) {
                class_alias($new, $old);
            }
        }
    }
}
