<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\ApplicationRoutes;

final class RouteRateLimitCoverageHelpers
{
    private const array READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Every route of the application takes exactly one limiter by name (`throttle:<name>`), the limiter is one the rate-limit provider registered (asked of the
     * rate limiter, not read from a table of names here), and a route that writes takes one of the write limiters, a route that reads none of them.
     *
     * @param list<string> $folders the folders that hold a controller or a route closure, relative to the base path
     * @param list<string> $writeLimiters the names of the limiters a write uses
     * @return list<string> `METHODS route-name problem` per route
     */
    public static function problems(array $folders, array $writeLimiters): array
    {
        $problems = [];

        foreach (ApplicationRoutes::all($folders) as $route) {
            $problem = self::problem($route, $writeLimiters);

            if ($problem !== null) {
                $problems[] = implode('|', $route->methods()) . ' ' . ApplicationRoutes::identifier($route) . ' ' . $problem;
            }
        }

        return $problems;
    }

    /**
     * @param list<string> $writeLimiters
     */
    public static function problem(RoutingRoute $route, array $writeLimiters): ?string
    {
        $limiters = self::limiters($route);

        if ($limiters === []) {
            return 'has no throttle:<name> limiter';
        }

        if (count($limiters) > 1) {
            return 'counts in more than one limiter: ' . implode(', ', $limiters);
        }

        $limiter = $limiters[0];

        if (RateLimiter::limiter($limiter) === null) {
            return 'uses ' . $limiter . ', which is not a limiter registered in the rate-limit provider';
        }

        $writes = array_diff($route->methods(), self::READ_METHODS) !== [];

        return $writes === in_array($limiter, $writeLimiters, true)
            ? null
            : ($writes ? 'writes with the read limiter ' . $limiter : 'reads with the write limiter ' . $limiter);
    }

    /**
     * @return list<string> the limiter names of the route, `a throttle without a limiter name` for a bare throttle
     */
    public static function limiters(RoutingRoute $route): array
    {
        $limiters = [];

        foreach (ApplicationRoutes::middleware($route) as $middleware) {
            if ($middleware === ThrottleRequests::class) {
                $limiters[] = 'a throttle without a limiter name';
            } elseif (str_starts_with($middleware, ThrottleRequests::class . ':')) {
                $limiters[] = Str::after($middleware, ':');
            }
        }

        return $limiters;
    }
}
