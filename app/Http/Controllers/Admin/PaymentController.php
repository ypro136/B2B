<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Company;
use App\Models\FinancialTransaction;
use App\Models\PaymentRecord;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    /**
     * List payment records with filters.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $companyId = $request->query('company_id');

        return view('admin::payments.index', [
            'status' => $status,
            'companyId' => $companyId,
            'payments' => PaymentRecord::query()
                ->with(['company', 'recorder', 'confirmer'])
                ->withSum('allocations as allocated_total', 'amount')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->latest('received_date')
                ->paginate(15)
                ->withQueryString(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $pendingBookings = Booking::query()
            ->with(['company', 'trip'])
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereColumn('settled_amount', '<', 'total_amount')
            ->latest('id')
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->getKey(),
                'company_id' => $booking->company_id,
                'company' => $booking->company?->name,
                'trip' => $booking->trip?->route_name,
                'outstanding' => round((float) $booking->total_amount - (float) $booking->settled_amount, 2),
                'currency' => $booking->currency,
            ]);

        return view('admin::payments.create', [
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(),
            'pendingBookings' => $pendingBookings,
        ]);
    }

    public function store(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'received_date' => ['required', 'date'],
            'method' => ['required', 'in:CASH,BANK'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $payment = $payments->record($company, $data, $request->user());

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('status', 'Payment recorded as pending. Confirm it to post it to the ledger.');
    }

    /**
     * Show a payment with allocation and refund controls.
     */
    public function show(PaymentRecord $payment): View
    {
        $payment->load(['company', 'recorder', 'confirmer', 'allocations.booking.trip', 'documents']);

        $bookings = $payment->company->bookings()
            ->with('trip')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereColumn('settled_amount', '<', 'total_amount')
            ->latest()
            ->get();

        return view('admin::payments.show', [
            'payment' => $payment,
            'bookings' => $bookings,
            'available' => $payment->availableAmount(),
        ]);
    }

    public function confirm(Request $request, PaymentRecord $payment, PaymentService $payments): RedirectResponse
    {
        $payments->confirm($payment, $request->user());

        return back()->with('status', 'Payment confirmed and posted to the ledger. A receipt draft was created.');
    }

    public function reject(Request $request, PaymentRecord $payment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payments->reject($payment, $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Payment rejected.');
    }

    public function allocate(Request $request, PaymentRecord $payment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payments->allocate($payment, $data['allocations'], $request->user());

        return back()->with('status', 'Allocation saved. Fully settled bookings created invoice drafts.');
    }

    public function refund(Request $request, PaymentRecord $payment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'kind' => ['required', 'in:cash,credit'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payments->refund($payment, (float) $data['amount'], $data['kind'], $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Refund recorded as '.$data['kind'].'.');
    }

    public function issueCredit(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $payments->issueCredit($company, (float) $data['amount'], $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Company credit issued.');
    }

    public function applyCredit(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $booking = Booking::query()->findOrFail($data['booking_id']);

        $payments->applyCredit($company, $booking, (float) $data['amount'], $request->user());

        return back()->with('status', 'Credit applied to booking #'.$booking->getKey().'.');
    }

    public function adjustment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $transaction = FinancialTransaction::query()->create([
            'company_id' => $data['company_id'],
            'type' => FinancialTransaction::TYPE_ADJUSTMENT,
            'amount' => $data['amount'],
            'currency' => 'EGP',
            'description' => $data['description'],
            'created_by' => $request->user()->getKey(),
        ]);

        AuditLog::record($transaction, 'finance.adjustment_posted', $request->user(), null, $data);

        return back()->with('status', 'Adjustment posted to the immutable ledger.');
    }
}
