<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register admin portal services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap the admin portal: routes and view namespace.
     */
    public function boot(): void
    {
        View::addNamespace('admin', resource_path('views/admin'));

        Route::middleware('web')->group(base_path('routes/admin.php'));
    }
}
