<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\PaymentRecord;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    /**
     * Generate the next gapless document number for the current year.
     */
    public function nextNumber(string $type): string
    {
        $prefix = $type === Document::TYPE_PAYMENT_RECEIPT
            ? Setting::get('receipt_prefix', 'RCT')
            : Setting::get('invoice_prefix', 'INV');
        $year = now()->format('Y');
        $pattern = $prefix.'-'.$year.'-';

        $latest = Document::query()
            ->where('number', 'like', $pattern.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $latest !== null ? ((int) substr($latest, -4)) + 1 : 1;

        return $pattern.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a draft invoice for a fully settled booking.
     */
    public function draftBookingInvoice(Booking $booking, ?User $actor = null): Document
    {
        $existing = Document::query()
            ->where('type', Document::TYPE_BOOKING_INVOICE)
            ->where('booking_id', $booking->getKey())
            ->where('state', '!=', Document::STATE_VOIDED)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($booking, $actor) {
            $booking->loadMissing('trip.companyPrices', 'seats');

            $document = Document::query()->create([
                'company_id' => $booking->company_id,
                'type' => Document::TYPE_BOOKING_INVOICE,
                'number' => $this->nextNumber(Document::TYPE_BOOKING_INVOICE),
                'booking_id' => $booking->getKey(),
                'total' => $booking->total_amount,
                'currency' => $booking->currency,
                'state' => Document::STATE_DRAFT,
                'prepared_by' => $actor?->getKey(),
                'meta' => [
                    'trip_id' => $booking->trip_id,
                    'seat_numbers' => $booking->seats->pluck('seat_number')->all(),
                ],
            ]);

            DocumentLine::query()->create([
                'document_id' => $document->getKey(),
                'booking_id' => $booking->getKey(),
                'trip_id' => $booking->trip_id,
                'description' => $booking->trip->route_name.' — '.$booking->trip->departure_datetime?->format('M j, Y H:i'),
                'seats' => $booking->seats_count,
                'unit_price' => $booking->unit_price,
                'line_total' => $booking->total_amount,
            ]);

            AuditLog::record($document, 'document.drafted', $actor, null, [
                'type' => $document->type,
                'number' => $document->number,
                'total' => $document->total,
            ]);

            return $document;
        });
    }

    /**
     * Create a draft receipt for a confirmed payment.
     */
    public function draftReceipt(PaymentRecord $payment, ?User $actor = null): Document
    {
        $existing = Document::query()
            ->where('type', Document::TYPE_PAYMENT_RECEIPT)
            ->where('payment_record_id', $payment->getKey())
            ->where('state', '!=', Document::STATE_VOIDED)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($payment, $actor) {
            $document = Document::query()->create([
                'company_id' => $payment->company_id,
                'type' => Document::TYPE_PAYMENT_RECEIPT,
                'number' => $this->nextNumber(Document::TYPE_PAYMENT_RECEIPT),
                'payment_record_id' => $payment->getKey(),
                'total' => $payment->amount,
                'currency' => $payment->currency,
                'state' => Document::STATE_DRAFT,
                'prepared_by' => $actor?->getKey(),
                'meta' => [
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                ],
            ]);

            DocumentLine::query()->create([
                'document_id' => $document->getKey(),
                'description' => 'Payment received — '.$payment->method.($payment->reference ? ' / '.$payment->reference : ''),
                'seats' => 0,
                'unit_price' => $payment->amount,
                'line_total' => $payment->amount,
            ]);

            AuditLog::record($document, 'document.drafted', $actor, null, [
                'type' => $document->type,
                'number' => $document->number,
                'total' => $document->total,
            ]);

            return $document;
        });
    }

    /**
     * Generate a monthly invoice for the bookings settled in the period.
     */
    public function generateMonthlyInvoice(Company $company, string $period, User $actor): Document
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $existing = Document::query()
            ->where('company_id', $company->getKey())
            ->where('type', Document::TYPE_MONTHLY_INVOICE)
            ->whereDate('period_start', $start)
            ->where('state', '!=', Document::STATE_VOIDED)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'period' => 'A monthly invoice already exists for this company and period.',
            ]);
        }

        $bookings = $company->bookings()
            ->with('trip')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('settlement_status', Booking::SETTLEMENT_FULL)
            ->whereBetween('settled_at', [$start, $end])
            ->get();

        if ($bookings->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'No fully settled bookings were completed in this period.',
            ]);
        }

        return DB::transaction(function () use ($company, $start, $end, $bookings, $actor) {
            $document = Document::query()->create([
                'company_id' => $company->getKey(),
                'type' => Document::TYPE_MONTHLY_INVOICE,
                'number' => $this->nextNumber(Document::TYPE_MONTHLY_INVOICE),
                'period_start' => $start,
                'period_end' => $end,
                'total' => round((float) $bookings->sum('total_amount'), 2),
                'currency' => $bookings->first()->currency,
                'state' => Document::STATE_DRAFT,
                'prepared_by' => $actor->getKey(),
            ]);

            foreach ($bookings as $booking) {
                DocumentLine::query()->create([
                    'document_id' => $document->getKey(),
                    'booking_id' => $booking->getKey(),
                    'trip_id' => $booking->trip_id,
                    'description' => $booking->trip->route_name.' — '.$booking->trip->departure_datetime?->format('M j, Y'),
                    'seats' => $booking->seats_count,
                    'unit_price' => $booking->unit_price,
                    'line_total' => $booking->total_amount,
                ]);
            }

            AuditLog::record($document, 'document.drafted', $actor, null, [
                'type' => $document->type,
                'number' => $document->number,
                'total' => $document->total,
                'period' => $start->format('Y-m'),
            ]);

            return $document;
        });
    }

    /**
     * Approve a draft document and freeze its snapshot.
     */
    public function approve(Document $document, User $actor): Document
    {
        if ($document->state !== Document::STATE_DRAFT) {
            throw ValidationException::withMessages([
                'document' => 'Only draft documents can be approved.',
            ]);
        }

        $document->update([
            'state' => Document::STATE_APPROVED,
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
            'meta' => array_merge($document->meta ?? [], [
                'approved_snapshot' => [
                    'total' => (string) $document->total,
                    'currency' => $document->currency,
                ],
            ]),
        ]);

        AuditLog::record($document, 'document.approved', $actor, [
            'state' => Document::STATE_DRAFT,
        ], [
            'state' => Document::STATE_APPROVED,
            'number' => $document->number,
        ]);

        return $document->refresh();
    }

    /**
     * Void a document without deleting its history.
     */
    public function void(Document $document, User $actor): Document
    {
        if ($document->state === Document::STATE_VOIDED) {
            throw ValidationException::withMessages([
                'document' => 'This document is already voided.',
            ]);
        }

        $document->update([
            'state' => Document::STATE_VOIDED,
            'voided_at' => now(),
        ]);

        AuditLog::record($document, 'document.voided', $actor, [
            'state' => $document->state,
        ], [
            'state' => Document::STATE_VOIDED,
        ]);

        return $document->refresh();
    }
}
