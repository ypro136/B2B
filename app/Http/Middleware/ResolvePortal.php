<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortal
{
    public const ADMIN = 'admin';

    public const COMPANY = 'company';

    /**
     * Resolve the portal from the request domain and enforce the required portal.
     */
    public function handle(Request $request, Closure $next, ?string $requiredPortal = null): Response
    {
        $portal = $this->resolvePortal($request, $requiredPortal);

        if ($portal === null) {
            abort(404, 'Portal could not be resolved.');
        }

        if ($requiredPortal !== null && $portal !== $requiredPortal) {
            abort(404, 'This route is not available on the current portal.');
        }

        config(['portal.type' => $portal]);

        $request->attributes->set('portal', $portal);

        View::share('portal', $portal);
        View::share('portalDomain', config('portal.domains.'.$portal));

        return $next($request);
    }

    /**
     * Determine the portal for the current request.
     */
    protected function resolvePortal(Request $request, ?string $requiredPortal = null): ?string
    {
        $host = strtolower($request->getHost());

        $domains = config('portal.domains', []);

        foreach ($domains as $portal => $domain) {
            $domain = strtolower((string) $domain);

            if ($domain !== '' && $host === $domain) {
                return $portal;
            }
        }

        if (str_starts_with($host, self::ADMIN.'.')) {
            return self::ADMIN;
        }

        if (str_starts_with($host, self::COMPANY.'.')) {
            return self::COMPANY;
        }

        // Local development and CLI requests fall back to the route's portal,
        // then to the configured default portal.
        if ($requiredPortal !== null) {
            return $requiredPortal;
        }

        $default = (string) config('portal.default', self::ADMIN);

        return in_array($default, config('portal.supported', [self::ADMIN, self::COMPANY]), true)
            ? $default
            : null;
    }
}
