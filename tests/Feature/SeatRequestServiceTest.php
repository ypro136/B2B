<?php

namespace Tests\Feature;

use App\Models\SeatRequest;
use App\Services\SeatRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class SeatRequestServiceTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_a_company_user_can_submit_a_seat_request(): void
    {
        $company = $this->makeCompany('Acme Logistics', 10);
        $user = $this->makeCompanyUser($company);

        $seatRequest = app(SeatRequestService::class)->request($company, $user, 5, 'Growing demand');

        $this->assertSame(SeatRequest::STATUS_PENDING, $seatRequest->status);
        $this->assertSame(5, $seatRequest->requested_seats);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'seat_request.created',
            'auditable_id' => $seatRequest->id,
        ]);
    }

    public function test_a_second_pending_request_is_blocked(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);

        $service = app(SeatRequestService::class);
        $service->request($company, $user, 5);

        try {
            $service->request($company, $user, 3);
            $this->fail('A second pending request should be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('requested_seats', $exception->errors());
        }

        $this->assertDatabaseCount('seat_requests', 1);
    }

    public function test_approval_raises_the_limit_and_writes_audit_entries(): void
    {
        $company = $this->makeCompany('Acme Logistics', 20);
        $user = $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $seatRequest = app(SeatRequestService::class)->request($company, $user, 5);

        $approved = app(SeatRequestService::class)->approve($seatRequest, $admin, 'Approved for Q4');

        $this->assertSame(SeatRequest::STATUS_APPROVED, $approved->status);
        $this->assertSame($admin->id, $approved->reviewed_by);
        $this->assertSame(20, $company->fresh()->seat_limit);
        $this->assertSame(5, $company->fresh()->bonus_seats);
        $this->assertSame(25, $company->fresh()->effectiveSeatLimit());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'seat_request.approved',
            'auditable_id' => $seatRequest->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company.seat_limit_changed',
            'auditable_id' => $company->id,
        ]);
    }

    public function test_rejection_does_not_change_the_limit(): void
    {
        $company = $this->makeCompany('Acme Logistics', 20);
        $user = $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $seatRequest = app(SeatRequestService::class)->request($company, $user, 5);

        $rejected = app(SeatRequestService::class)->reject($seatRequest, $admin, 'Not enough capacity');

        $this->assertSame(SeatRequest::STATUS_REJECTED, $rejected->status);
        $this->assertSame(20, $company->fresh()->seat_limit);
    }

    public function test_only_pending_requests_can_be_reviewed(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeCompanyUser($company);
        $admin = $this->makeAdmin();

        $service = app(SeatRequestService::class);
        $seatRequest = $service->request($company, $user, 5);
        $service->approve($seatRequest, $admin);

        $this->expectException(ValidationException::class);

        $service->reject($seatRequest->fresh(), $admin);
    }
}
