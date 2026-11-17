<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;

abstract class BaseController extends Controller
{
    /**
     * The portal handled by this controller.
     */
    protected string $portal = 'company';

    /**
     * The Blade layout shared by every company view.
     */
    protected string $layout = 'company::layouts.app';

    public function __construct()
    {
        View::share('portal', $this->portal);
        View::share('layout', $this->layout);
        View::share('portalName', 'Company Portal');
    }

    /**
     * Return the shared layout name.
     */
    protected function layout(): string
    {
        return $this->layout;
    }
}
