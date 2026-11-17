<?php

if (! function_exists('portal_type')) {
    /**
     * Resolve the active portal for the current request.
     */
    function portal_type(): string
    {
        return (string) config('portal.type', config('portal.default', 'admin'));
    }
}

if (! function_exists('portal_is')) {
    /**
     * Determine whether the active portal matches the given type.
     */
    function portal_is(string $portal): bool
    {
        return portal_type() === $portal;
    }
}

if (! function_exists('portal_route')) {
    /**
     * Generate a named route for the active portal.
     */
    function portal_route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return route(portal_type().'.'.$name, $parameters, $absolute);
    }
}

if (! function_exists('portal_domain')) {
    /**
     * Return the configured domain for a given portal.
     */
    function portal_domain(?string $portal = null): string
    {
        $portal ??= portal_type();

        return (string) config('portal.domains.'.$portal, '');
    }
}
