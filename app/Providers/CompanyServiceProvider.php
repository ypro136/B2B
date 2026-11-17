<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CompanyServiceProvider extends ServiceProvider
{
    /**
     * Register company portal services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap the company portal: routes and view namespace.
     */
    public function boot(): void
    {
        View::addNamespace('company', resource_path('views/company'));

        Route::middleware('web')->group(base_path('routes/company.php'));
    }
}
