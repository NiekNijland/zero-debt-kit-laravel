<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class RunInTransaction
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        // The router turns an exception of the handler into a response before control returns here, so the
        // transaction only rolls back when the exception the response carries is thrown again inside it.
        $failed = null;

        try {
            return DB::transaction(function () use ($request, $next, &$failed): Response {
                $response = $next($request);
                $exception = $this->renderedException($response);

                if ($exception instanceof Throwable) {
                    $failed = $response;

                    throw $exception;
                }

                return $response;
            });
        } catch (Throwable $exception) {
            if (! $failed instanceof Response) {
                throw $exception;
            }

            return $failed;
        }
    }

    private function renderedException(Response $response): ?Throwable
    {
        if ($response instanceof IlluminateResponse || $response instanceof JsonResponse || $response instanceof RedirectResponse) {
            return $response->exception;
        }

        return null;
    }
}
