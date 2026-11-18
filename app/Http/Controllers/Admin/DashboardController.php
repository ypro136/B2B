<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Document;
use App\Models\PaymentRecord;
use App\Models\SeatRequest;
use App\Models\Trip;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends BaseController
{
    /**
     * Show the admin dashboard with platform-wide counters.
     */
    public function index(): View
    {
        $outstanding = (float) Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->selectRaw('COALESCE(SUM(total_amount - settled_amount), 0) as due')
            ->value('due');

        return view('admin::dashboard', [
            'stats' => [
                ['label' => 'Companies', 'value' => Company::query()->count()],
                ['label' => 'Scheduled Trips', 'value' => Trip::query()->upcoming()->count()],
                ['label' => 'Pending Requests', 'value' => SeatRequest::query()->pending()->count()],
                ['label' => 'Pending Payments', 'value' => PaymentRecord::query()->where('status', PaymentRecord::STATUS_PENDING)->count()],
                ['label' => 'Outstanding', 'value' => number_format($outstanding, 2).' EGP'],
                ['label' => 'Documents to approve', 'value' => Document::query()->where('state', Document::STATE_DRAFT)->count()],
            ],
            'todaysBookings' => Booking::query()
                ->whereDate('created_at', today())
                ->with(['company', 'trip'])
                ->withCount([
                    'allocations as confirmed_payment_allocations_count' => fn (Builder $query) => $query->whereHas(
                        'payment',
                        fn (Builder $payment) => $payment->where('status', PaymentRecord::STATUS_CONFIRMED),
                    ),
                ])
                ->latest()
                ->get(),
            'recentRequests' => SeatRequest::query()
                ->with(['company', 'requester'])
                ->latest()
                ->limit(5)
                ->get(),
            'recentPayments' => PaymentRecord::query()
                ->with('company')
                ->latest()
                ->limit(5)
                ->get(),
            'upcomingTrips' => Trip::query()
                ->upcoming()
                ->withSum([
                    'bookings as confirmed_seats_count' => fn (Builder $query) => $query->where('status', Booking::STATUS_CONFIRMED),
                ], 'seats_count')
                ->orderBy('departure_datetime')
                ->limit(4)
                ->get(),
        ]);
    }
}
