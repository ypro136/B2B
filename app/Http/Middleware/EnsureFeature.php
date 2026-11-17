<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeature
{
    /**
     * Require an admin feature permission for the current request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->hasFeature($feature),
            403,
            'You do not have permission to perform this action.',
        );

        return $next($request);
    }
}
