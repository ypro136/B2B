<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;

abstract class BaseController extends Controller
{
    /**
     * The portal handled by this controller.
     */
    protected string $portal = 'admin';

    /**
     * The Blade layout shared by every admin view.
     */
    protected string $layout = 'admin::layouts.app';

    public function __construct()
    {
        View::share('portal', $this->portal);
        View::share('layout', $this->layout);
        View::share('portalName', 'Administration');
    }

    /**
     * Return the shared layout name.
     */
    protected function layout(): string
    {
        return $this->layout;
    }
}
