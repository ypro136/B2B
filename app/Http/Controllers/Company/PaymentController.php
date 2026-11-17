<?php

namespace App\Http\Controllers\Company;

use App\Models\PaymentRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;

        return view('company::payments.index', [
            'company' => $company,
            'payments' => $company->payments()
                ->where('status', PaymentRecord::STATUS_CONFIRMED)
                ->with(['allocations.booking.trip'])
                ->latest('received_date')
                ->paginate(15),
            'outstanding' => $company->outstandingBalance(),
            'creditBalance' => $company->creditBalance(),
        ]);
    }
}
