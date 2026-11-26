<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Support\ApplicationRoutes;

final class ObjectLevelAccessHelpers
{
    /**
     * @param list<string> $folders the folders that hold a controller or a route closure, relative to the base path
     * @return list<RoutingRoute> the routes of the application that take a record id in the URL
     */
    public static function entryPoints(array $folders): array
    {
        return array_values(array_filter(ApplicationRoutes::all($folders), static fn (RoutingRoute $route): bool => self::recordParameters($route) !== []));
    }

    /**
     * @return array<string, class-string<UrlRoutable>> the URL parameters of a route that bind a record, by parameter name
     */
    public static function recordParameters(RoutingRoute $route): array
    {
        $parameters = [];

        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            if (! $parameter instanceof ReflectionParameter) {
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && in_array($parameter->getName(), $route->parameterNames(), true) && is_a($type->getName(), UrlRoutable::class, true)) {
                $parameters[$parameter->getName()] = $type->getName();
            }
        }

        return $parameters;
    }

    /**
     * A record that belongs to someone else for every record parameter of a route, or the words of what is missing: a model that has no factory in the map
     * fails the test, so a new route with a new model cannot slip through unproved.
     *
     * @param array<class-string, Closure(): Model> $factories the model, and a factory of a record of another owner (another user, another tenant)
     * @return array<string, Model>|string
     */
    public static function foreignRecords(RoutingRoute $route, array $factories): array|string
    {
        $records = [];

        foreach (self::recordParameters($route) as $name => $class) {
            $factory = $factories[$class] ?? null;

            if ($factory === null) {
                return 'has no factory for ' . $class . ' in the map of foreign records';
            }

            $records[$name] = $factory();
        }

        return $records;
    }

    public static function method(RoutingRoute $route): string
    {
        return array_values(array_diff($route->methods(), ['HEAD']))[0] ?? 'GET';
    }
}
