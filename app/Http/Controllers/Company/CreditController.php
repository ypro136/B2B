<?php

namespace App\Http\Controllers\Company;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CreditController extends BaseController
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;

        return view('company::credits.index', [
            'company' => $company,
            'balance' => $company->creditBalance(),
            'movements' => $company->creditMovements()
                ->with(['booking.trip', 'payment'])
                ->latest()
                ->paginate(20),
        ]);
    }
}
