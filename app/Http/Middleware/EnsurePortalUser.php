<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalUser
{
    /**
     * Ensure the authenticated user belongs to the requested portal.
     */
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            abort(403, 'This account is inactive.');
        }

        $allowed = match ($portal) {
            'admin' => $user->isAdmin(),
            'company' => $user->company_id !== null && $user->company?->is_active === true,
            default => false,
        };

        abort_unless($allowed, 403, 'This account cannot access the requested portal.');

        return $next($request);
    }
}
