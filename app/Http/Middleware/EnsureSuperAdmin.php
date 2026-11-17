<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Restrict the request to super administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isSuperAdmin() === true,
            403,
            'Only a super administrator may perform this action.',
        );

        return $next($request);
    }
}
