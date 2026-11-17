<?php

namespace App\Http\Controllers\Company;

use App\Models\Trip;
use App\Services\QuotaService;
use Illuminate\Contracts\View\View;

class DashboardController extends BaseController
{
    /**
     * Show the company dashboard scoped to the signed-in company user.
     */
    public function index(QuotaService $quotas): View
    {
        $company = auth()->user()?->company;

        return view('company::dashboard', [
            'company' => $company,
            'quota' => $company !== null ? $quotas->summary($company) : null,
            'outstanding' => $company?->outstandingBalance() ?? 0,
            'creditBalance' => $company?->creditBalance() ?? 0,
            'stats' => [
                ['label' => 'Used Seats', 'value' => $company?->usedSeats() ?? 0],
                ['label' => 'Remaining Seats', 'value' => $company?->availableSeats() ?? 0],
                ['label' => 'Outstanding', 'value' => number_format($company?->outstandingBalance() ?? 0, 2).' EGP'],
                ['label' => 'Pending Requests', 'value' => $company?->seatRequests()->pending()->count() ?? 0],
            ],
            'upcomingTrips' => Trip::query()->upcoming()->count(),
            'recentBookings' => $company?->bookings()
                ->with(['trip', 'seats'])
                ->latest()
                ->limit(5)
                ->get() ?? collect(),
        ]);
    }
}
