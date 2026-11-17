<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Company;
use App\Models\FinancialTransaction;
use App\Models\PaymentRecord;
use App\Models\SeatRequest;
use App\Models\Trip;
use App\Services\PeriodService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends BaseController
{
    /**
     * Show platform-wide operational reports.
     */
    public function index(): View
    {
        $totalCompanies = Company::query()->count();
        $activeCompanies = Company::query()->where('is_active', true)->count();
        $seatLimits = (int) Company::query()->sum('seat_limit');

        $seatsInUse = (int) Booking::query()
            ->confirmed()
            ->whereHas('trip', fn (Builder $query) => $query->where('status', '!=', Trip::STATUS_CANCELLED))
            ->sum('seats_count');

        $utilization = $seatLimits > 0 ? (int) round($seatsInUse / $seatLimits * 100) : 0;

        $confirmedBookings = Booking::query()->confirmed()->count();
        $cancelledBookings = Booking::query()->where('status', Booking::STATUS_CANCELLED)->count();
        $pendingRequests = SeatRequest::query()->pending()->count();
        $approvedSeats = (int) SeatRequest::query()
            ->where('status', SeatRequest::STATUS_APPROVED)
            ->sum('requested_seats');

        $completedTrips = Trip::query()->where('status', Trip::STATUS_COMPLETED)->count();

        return view('admin::reports.index', [
            'summary' => [
                [
                    'label' => 'Active companies',
                    'value' => $activeCompanies.' / '.$totalCompanies,
                    'note' => 'client accounts',
                ],
                [
                    'label' => 'Seat utilization',
                    'value' => $utilization.'%',
                    'note' => $seatsInUse.' of '.$seatLimits.' seats held',
                ],
                [
                    'label' => 'Upcoming trips',
                    'value' => Trip::query()->upcoming()->count(),
                    'note' => $completedTrips.' completed to date',
                ],
                [
                    'label' => 'Confirmed bookings',
                    'value' => $confirmedBookings,
                    'note' => $cancelledBookings.' cancelled',
                ],
                [
                    'label' => 'Pending requests',
                    'value' => $pendingRequests,
                    'note' => $approvedSeats.' seats approved',
                ],
            ],
            'topCompanies' => Company::query()
                ->withSum([
                    'bookings as confirmed_seats_count' => fn (Builder $query) => $query
                        ->where('status', Booking::STATUS_CONFIRMED)
                        ->whereHas('trip', fn (Builder $trip) => $trip->where('status', '!=', Trip::STATUS_CANCELLED)),
                ], 'seats_count')
                ->orderByDesc('confirmed_seats_count')
                ->limit(5)
                ->get()
                ->map(fn (Company $company) => [
                    'name' => $company->name,
                    'seats_used' => (int) $company->confirmed_seats_count,
                    'seat_limit' => $company->seat_limit,
                    'utilization' => $company->seat_limit > 0
                        ? (int) round($company->confirmed_seats_count / $company->seat_limit * 100)
                        : 0,
                ]),
            'upcomingTrips' => Trip::query()
                ->upcoming()
                ->withSum([
                    'bookings as confirmed_seats_count' => fn (Builder $query) => $query
                        ->where('status', Booking::STATUS_CONFIRMED),
                ], 'seats_count')
                ->orderBy('departure_datetime')
                ->limit(5)
                ->get()
                ->map(fn (Trip $trip) => [
                    'route_name' => $trip->route_name,
                    'departure_datetime' => $trip->departure_datetime,
                    'total_seats' => $trip->total_seats,
                    'seats_used' => (int) $trip->confirmed_seats_count,
                    'utilization' => $trip->total_seats > 0
                        ? (int) round($trip->confirmed_seats_count / $trip->total_seats * 100)
                        : 0,
                ]),
            'recentActivity' => AuditLog::query()
                ->with('actor')
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Finance reporting: confirmed revenue basis, profit and internal VAT.
     */
    public function finance(Request $request, PeriodService $periods): View
    {
        [$from, $to] = $this->range($request);

        return view('admin::reports.finance', [
            'from' => $from,
            'to' => $to,
            'periods' => $periods->summary(),
            ...$this->financeData($from, $to),
        ]);
    }

    /**
     * Export the finance summary as CSV.
     */
    public function exportFinance(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $data = $this->financeData($from, $to);

        return response()->streamDownload(function () use ($data, $from, $to) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Transportation Sys - Finance report']);
            fputcsv($handle, ['From', $from->toDateString(), 'To', $to->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Confirmed revenue', number_format($data['confirmedRevenue'], 2, '.', '')]);
            fputcsv($handle, ['Seat cost', number_format($data['seatCost'], 2, '.', '')]);
            fputcsv($handle, ['Net profit', number_format($data['netProfit'], 2, '.', '')]);
            fputcsv($handle, ['Internal VAT component', number_format($data['vatComponent'], 2, '.', '')]);
            fputcsv($handle, []);
            fputcsv($handle, ['Company', 'Confirmed revenue']);
            foreach ($data['revenueByCompany'] as $row) {
                fputcsv($handle, [$row['company'], number_format($row['revenue'], 2, '.', '')]);
            }
            fclose($handle);
        }, 'finance-report-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.csv');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function financeData(Carbon $from, Carbon $to): array
    {
        $confirmedRevenue = (float) PaymentRecord::query()
            ->where('status', PaymentRecord::STATUS_CONFIRMED)
            ->whereBetween('received_date', [$from, $to])
            ->sum('amount');

        $seatCost = 0.0;

        $bookings = Booking::query()
            ->with('trip')
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->whereHas('trip', fn (Builder $query) => $query->whereBetween('departure_datetime', [$from, $to]))
            ->get();

        foreach ($bookings as $booking) {
            $seatCost += (float) $booking->seats_count * (float) ($booking->trip?->per_seat_cost ?? 0);
        }

        $vatComponent = 0.0;

        foreach ($bookings as $booking) {
            if ($booking->vat_enabled && (float) $booking->vat_rate > 0) {
                $gross = (float) $booking->total_amount;
                $vatComponent += $gross - ($gross / (1 + ((float) $booking->vat_rate / 100)));
            }
        }

        $revenueByCompany = PaymentRecord::query()
            ->where('status', PaymentRecord::STATUS_CONFIRMED)
            ->whereBetween('received_date', [$from, $to])
            ->selectRaw('company_id, SUM(amount) as revenue')
            ->groupBy('company_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(function (PaymentRecord $row) {
                return [
                    'company' => Company::query()->whereKey($row->company_id)->value('name') ?? 'Unknown',
                    'revenue' => (float) $row->revenue,
                ];
            });

        return [
            'confirmedRevenue' => round($confirmedRevenue, 2),
            'seatCost' => round($seatCost, 2),
            'netProfit' => round($confirmedRevenue - $seatCost, 2),
            'vatComponent' => round($vatComponent, 2),
            'revenueByCompany' => $revenueByCompany,
            'transactions' => FinancialTransaction::query()
                ->with('company')
                ->whereBetween('created_at', [$from, $to])
                ->latest()
                ->limit(30)
                ->get(),
            'pendingPayments' => PaymentRecord::query()->where('status', PaymentRecord::STATUS_PENDING)->count(),
        ];
    }
}
