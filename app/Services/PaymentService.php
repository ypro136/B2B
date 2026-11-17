<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Company;
use App\Models\CompanyCreditMovement;
use App\Models\FinancialTransaction;
use App\Models\PaymentAllocation;
use App\Models\PaymentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private DocumentService $documents,
        private PeriodService $periods,
    ) {}

    /**
     * Record an offline cash or bank payment in the pending state.
     */
    public function record(Company $company, array $data, User $actor): PaymentRecord
    {
        $this->periods->assertOpen($data['received_date']);

        $payment = PaymentRecord::query()->create([
            'company_id' => $company->getKey(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'EGP',
            'received_date' => $data['received_date'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $actor->getKey(),
            'status' => PaymentRecord::STATUS_PENDING,
        ]);

        AuditLog::record($payment, 'payment.recorded', $actor, null, [
            'company_id' => $company->getKey(),
            'amount' => $payment->amount,
            'method' => $payment->method,
            'status' => PaymentRecord::STATUS_PENDING,
        ]);

        return $payment;
    }

    /**
     * Confirm a pending payment and post it to the ledger.
     */
    public function confirm(PaymentRecord $payment, User $actor): PaymentRecord
    {
        return DB::transaction(function () use ($payment, $actor) {
            $payment = PaymentRecord::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only pending payments can be confirmed.',
                ]);
            }

            $this->periods->assertOpen($payment->received_date);

            $payment->update([
                'status' => PaymentRecord::STATUS_CONFIRMED,
                'confirmed_by' => $actor->getKey(),
                'confirmed_at' => now(),
            ]);

            FinancialTransaction::query()->create([
                'company_id' => $payment->company_id,
                'payment_record_id' => $payment->getKey(),
                'type' => FinancialTransaction::TYPE_PAYMENT_CONFIRMED,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'description' => 'Payment confirmed — '.$payment->method.($payment->reference ? ' / '.$payment->reference : ''),
                'created_by' => $actor->getKey(),
            ]);

            $this->documents->draftReceipt($payment, $actor);

            AuditLog::record($payment, 'payment.confirmed', $actor, [
                'status' => PaymentRecord::STATUS_PENDING,
            ], [
                'status' => PaymentRecord::STATUS_CONFIRMED,
                'amount' => $payment->amount,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Reject a pending payment.
     */
    public function reject(PaymentRecord $payment, User $actor, ?string $note = null): PaymentRecord
    {
        if (! $payment->isPending()) {
            throw ValidationException::withMessages([
                'payment' => 'Only pending payments can be rejected.',
            ]);
        }

        $payment->update([
            'status' => PaymentRecord::STATUS_REJECTED,
            'notes' => trim(($payment->notes ? $payment->notes."\n" : '').($note ?? 'Rejected.')),
        ]);

        AuditLog::record($payment, 'payment.rejected', $actor, [
            'status' => PaymentRecord::STATUS_PENDING,
        ], [
            'status' => PaymentRecord::STATUS_REJECTED,
            'note' => $note,
        ]);

        return $payment->refresh();
    }

    /**
     * Allocate a confirmed payment across booking obligations.
     *
     * @param  array<int, float|string>  $allocations  booking id => amount
     */
    public function allocate(PaymentRecord $payment, array $allocations, User $actor): PaymentRecord
    {
        $allocations = collect($allocations)
            ->map(fn ($amount) => round((float) $amount, 2))
            ->filter(fn ($amount) => $amount > 0);

        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Enter at least one allocation amount.',
            ]);
        }

        return DB::transaction(function () use ($payment, $allocations, $actor) {
            $payment = PaymentRecord::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (! $payment->isConfirmed()) {
                throw ValidationException::withMessages([
                    'payment' => 'Only confirmed payments can be allocated.',
                ]);
            }

            $available = $payment->availableAmount();
            $requested = round((float) $allocations->sum(), 2);

            if ($requested > $available) {
                throw ValidationException::withMessages([
                    'allocations' => "The payment only has {$available} available for allocation.",
                ]);
            }

            $company = $payment->company;

            foreach ($allocations as $bookingId => $amount) {
                $booking = Booking::query()->lockForUpdate()->find($bookingId);

                if ($booking === null || (int) $booking->company_id !== (int) $payment->company_id) {
                    throw ValidationException::withMessages([
                        'allocations' => 'One or more bookings do not belong to this company.',
                    ]);
                }

                if ($booking->status !== Booking::STATUS_CONFIRMED) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Cancelled bookings cannot receive allocations.',
                    ]);
                }

                $remainingDue = $booking->remainingDue();

                if ($amount > $remainingDue) {
                    throw ValidationException::withMessages([
                        'allocations' => "The allocation for booking #{$booking->getKey()} exceeds its remaining due of {$remainingDue}.",
                    ]);
                }

                if (! $company->partial_payment_allowed && $amount < $remainingDue) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Partial payments are not allowed for this company.',
                    ]);
                }

                PaymentAllocation::query()->create([
                    'payment_record_id' => $payment->getKey(),
                    'booking_id' => $booking->getKey(),
                    'amount' => $amount,
                    'allocated_by' => $actor->getKey(),
                ]);

                $this->applySettlement($booking, $amount, $actor);
            }

            AuditLog::record($payment, 'payment.allocated', $actor, null, [
                'allocations' => $allocations->all(),
                'total_allocated' => $requested,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Refund a confirmed payment as cash or as a company credit.
     */
    public function refund(PaymentRecord $payment, float $amount, string $kind, User $actor, ?string $note = null): PaymentRecord
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($payment, $amount, $kind, $actor, $note) {
            $payment = PaymentRecord::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (! $payment->isConfirmed()) {
                throw ValidationException::withMessages([
                    'refund' => 'Only confirmed payments can be refunded.',
                ]);
            }

            if ($amount <= 0 || $amount > $payment->availableAmount()) {
                throw ValidationException::withMessages([
                    'refund' => 'The refund amount exceeds the refundable balance.',
                ]);
            }

            if ($kind === 'credit') {
                CompanyCreditMovement::query()->create([
                    'company_id' => $payment->company_id,
                    'type' => CompanyCreditMovement::TYPE_ISSUED,
                    'amount' => $amount,
                    'payment_record_id' => $payment->getKey(),
                    'notes' => $note,
                    'created_by' => $actor->getKey(),
                ]);

                $type = FinancialTransaction::TYPE_CREDIT_ISSUED;
                $description = 'Credit issued from payment #'.$payment->getKey();
            } else {
                $type = FinancialTransaction::TYPE_REFUND;
                $description = 'Cash refund for payment #'.$payment->getKey();
            }

            FinancialTransaction::query()->create([
                'company_id' => $payment->company_id,
                'payment_record_id' => $payment->getKey(),
                'type' => $type,
                'amount' => $amount,
                'currency' => $payment->currency,
                'description' => $description.($note ? ' — '.$note : ''),
                'created_by' => $actor->getKey(),
            ]);

            AuditLog::record($payment, 'payment.refunded', $actor, null, [
                'amount' => $amount,
                'kind' => $kind,
                'note' => $note,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Issue company account credit without a source payment.
     */
    public function issueCredit(Company $company, float $amount, User $actor, ?string $note = null): CompanyCreditMovement
    {
        $movement = CompanyCreditMovement::query()->create([
            'company_id' => $company->getKey(),
            'type' => CompanyCreditMovement::TYPE_ISSUED,
            'amount' => $amount,
            'notes' => $note,
            'created_by' => $actor->getKey(),
        ]);

        FinancialTransaction::query()->create([
            'company_id' => $company->getKey(),
            'type' => FinancialTransaction::TYPE_CREDIT_ISSUED,
            'amount' => $amount,
            'currency' => 'EGP',
            'description' => 'Company credit issued'.($note ? ' — '.$note : ''),
            'created_by' => $actor->getKey(),
        ]);

        AuditLog::record($company, 'credit.issued', $actor, null, [
            'amount' => $amount,
            'note' => $note,
        ]);

        return $movement;
    }

    /**
     * Apply available company credit to a booking obligation.
     */
    public function applyCredit(Company $company, Booking $booking, float $amount, User $actor): Booking
    {
        return DB::transaction(function () use ($company, $booking, $amount, $actor) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());

            if ((int) $booking->company_id !== (int) $company->getKey()) {
                throw ValidationException::withMessages([
                    'credit' => 'The booking does not belong to this company.',
                ]);
            }

            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'credit' => 'Cancelled bookings cannot receive credit.',
                ]);
            }

            if ($amount <= 0 || $amount > $company->creditBalance()) {
                throw ValidationException::withMessages([
                    'credit' => 'The amount exceeds the available company credit.',
                ]);
            }

            if ($amount > $booking->remainingDue()) {
                throw ValidationException::withMessages([
                    'credit' => 'The amount exceeds the remaining due on the booking.',
                ]);
            }

            CompanyCreditMovement::query()->create([
                'company_id' => $company->getKey(),
                'type' => CompanyCreditMovement::TYPE_APPLIED,
                'amount' => $amount,
                'booking_id' => $booking->getKey(),
                'notes' => 'Applied to booking #'.$booking->getKey(),
                'created_by' => $actor->getKey(),
            ]);

            FinancialTransaction::query()->create([
                'company_id' => $company->getKey(),
                'booking_id' => $booking->getKey(),
                'type' => FinancialTransaction::TYPE_CREDIT_APPLIED,
                'amount' => $amount,
                'currency' => $booking->currency,
                'description' => 'Credit applied to booking #'.$booking->getKey(),
                'created_by' => $actor->getKey(),
            ]);

            $this->applySettlement($booking, $amount, $actor);

            AuditLog::record($booking, 'credit.applied', $actor, null, [
                'amount' => $amount,
            ]);

            return $booking->refresh();
        });
    }

    /**
     * Update the settlement snapshot and fire the invoice draft once.
     */
    private function applySettlement(Booking $booking, float $amount, User $actor): void
    {
        $settled = round((float) $booking->settled_amount + $amount, 2);
        $total = (float) $booking->total_amount;

        $status = $settled <= 0
            ? Booking::SETTLEMENT_UNSETTLED
            : ($settled >= $total ? Booking::SETTLEMENT_FULL : Booking::SETTLEMENT_PARTIAL);

        $wasFull = $booking->settlement_status === Booking::SETTLEMENT_FULL;

        $booking->update([
            'settled_amount' => $settled,
            'settlement_status' => $status,
            'settled_at' => $status === Booking::SETTLEMENT_FULL
                ? ($booking->settled_at ?? now())
                : $booking->settled_at,
        ]);

        if ($status === Booking::SETTLEMENT_FULL && ! $wasFull) {
            $this->documents->draftBookingInvoice($booking, $actor);
        }
    }
}
