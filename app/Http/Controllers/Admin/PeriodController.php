<?php

namespace App\Http\Controllers\Admin;

use App\Services\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PeriodController extends BaseController
{
    public function close(Request $request, string $period, PeriodService $periods): RedirectResponse
    {
        $periods->close($period, $request->user());

        return back()->with('status', 'Period '.$period.' closed. Records in it are now locked.');
    }

    public function reopen(Request $request, string $period, PeriodService $periods): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $periods->reopen($period, $request->user(), $data['reason']);

        return back()->with('status', 'Period '.$period.' reopened. Re-close it after corrections.');
    }
}
