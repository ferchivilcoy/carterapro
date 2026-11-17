<?php

namespace App\Http\Middleware;

use App\Services\OverdueInstallmentsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Refreshes the stored overdue status on the first web request of the day.
 *
 * The nightly schedule in routes/console.php is the primary trigger, but a
 * scheduler is not always running: during local development nothing invokes
 * schedule:run, and the stored status would then stay stale until the clock
 * crossed midnight. Because the status is stored rather than derived on read,
 * the listings, the badges and the loan status only agree while it is fresh.
 */
class RefreshesOverdueInstallments
{
    public function __construct(
        private readonly OverdueInstallmentsService $overdue,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Tests set up their own fixtures and assert on the exact rows they
        // created, so a background sweep would leak into their results.
        if (! app()->environment('testing')) {
            try {
                $this->overdue->refreshOncePerDay();
            } catch (Throwable $e) {
                // A failed sweep must not take the page down with it. Reporting
                // it keeps the failure in the log, and leaving the day marker
                // unset means the next request retries.
                report($e);
            }
        }

        return $next($request);
    }
}
