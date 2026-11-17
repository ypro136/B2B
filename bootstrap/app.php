<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsurePortalUser;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolvePortal;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi();

        $middleware->alias([
            'portal' => ResolvePortal::class,
            'portal.user' => EnsurePortalUser::class,
            'feature' => EnsureFeature::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            $pathPortal = $request->is('company', 'company/*') ? 'company' : 'admin';
            $domains = array_map('strtolower', config('portal.domains', []));
            $hostPortal = array_search(strtolower($request->getHost()), $domains, true);

            if ($hostPortal !== false && $hostPortal !== $pathPortal) {
                abort(404);
            }

            return route($pathPortal.'.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
