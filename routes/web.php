<?php

use Illuminate\Support\Facades\Route;

Route::middleware('portal')->get('/', function () {
    $user = auth()->user();

    if ($user !== null) {
        $portal = match (true) {
            $user->isAdmin() => 'admin',
            $user->company_id !== null => 'company',
            default => portal_type(),
        };

        return redirect()->route($portal.'.dashboard');
    }

    $portal = portal_type();

    if (! in_array($portal, config('portal.supported', ['admin', 'company']), true)) {
        $portal = config('portal.default', 'admin');
    }

    return redirect()->route($portal.'.login');
})->name('home');
