<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\PaymentRecord;
use App\Services\BookingService;
use App\Services\DocumentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class NewPlanPrototypeTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_new_admin_pages_render(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $trip = $this->makeTrip();
        $trip->ensureSeats();

        $this->actingAs($admin)->withoutVite();

        $this->get(route('admin.trips.show', $trip))->assertOk();
        $this->get(route('admin.trips.edit', $trip))->assertOk();
        $this->get(route('admin.templates.index'))->assertOk();
        $this->get(route('admin.templates.create'))->assertOk();
        $this->get(route('admin.companies.edit', $company))->assertOk();
        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.payments.create'))->assertOk();
        $this->get(route('admin.payments.documents.index'))->assertOk();
        $this->get(route('admin.payments.documents.monthly-form'))->assertOk();
        $this->get(route('admin.settings.index'))->assertOk();
        $this->get(route('admin.reports.finance'))->assertOk();
    }

    public function test_new_company_pages_render(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['standard_price' => 100]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 2);
        $payment = app(PaymentService::class)->record($company, [
            'amount' => 100,
            'currency' => 'EGP',
            'received_date' => now()->toDateString(),
            'method' => 'CASH',
        ], $user);

        $this->actingAs($user)->withoutVite();

        $this->get(route('company.trips.show', $trip))->assertOk();
        $this->get(route('company.bookings.show', $booking))->assertOk();
        $this->get(route('company.documents.index'))->assertOk();
        $this->get(route('company.payments.index'))->assertOk();
        $this->get(route('company.credits.index'))->assertOk();
        $this->assertSame(PaymentRecord::STATUS_PENDING, $payment->status);
    }

    public function test_payment_confirmation_allocation_and_document_approval_flow(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $trip = $this->makeTrip(['standard_price' => 100]);

        $booking = app(BookingService::class)->book($company, $trip, $user, 2);

        $payment = app(PaymentService::class)->record($company, [
            'amount' => (float) $booking->total_amount,
            'currency' => 'EGP',
            'received_date' => now()->toDateString(),
            'method' => 'BANK',
            'reference' => 'TRX-1',
        ], $admin);

        app(PaymentService::class)->confirm($payment, $admin);
        app(PaymentService::class)->allocate($payment->fresh(), [
            $booking->id => (float) $booking->total_amount,
        ], $admin);

        $booking->refresh();
        $this->assertSame(Booking::SETTLEMENT_FULL, $booking->settlement_status);

        $document = Document::query()
            ->where('type', Document::TYPE_BOOKING_INVOICE)
            ->where('booking_id', $booking->id)
            ->firstOrFail();

        app(DocumentService::class)->approve($document, $admin);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'state' => Document::STATE_APPROVED,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('company.documents.show', $document->fresh()))
            ->assertOk();
    }
}
