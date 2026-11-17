<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SeatRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatRequestService
{
    public function __construct(private QuotaService $quotas) {}

    /**
     * Submit a formal request for additional seats.
     *
     * @throws ValidationException
     */
    public function request(Company $company, User $requester, int $seats, ?string $reason = null): SeatRequest
    {
        return DB::transaction(function () use ($company, $requester, $seats, $reason) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->getKey());

            if ($requester->company_id !== $company->getKey()) {
                throw ValidationException::withMessages([
                    'requested_seats' => 'The requesting user does not belong to this company.',
                ]);
            }

            if (! $company->is_active) {
                throw ValidationException::withMessages([
                    'requested_seats' => 'This company account is inactive.',
                ]);
            }

            if ($seats < 1) {
                throw ValidationException::withMessages([
                    'requested_seats' => 'At least one seat must be requested.',
                ]);
            }

            if ($company->seatRequests()->pending()->exists()) {
                throw ValidationException::withMessages([
                    'requested_seats' => 'A seat request is already awaiting review.',
                ]);
            }

            $seatRequest = SeatRequest::query()->create([
                'company_id' => $company->getKey(),
                'requested_by' => $requester->getKey(),
                'requested_seats' => $seats,
                'reason' => $reason,
                'status' => SeatRequest::STATUS_PENDING,
            ]);

            AuditLog::record($seatRequest, 'seat_request.created', $requester, null, [
                'company_id' => $company->getKey(),
                'requested_seats' => $seats,
                'status' => SeatRequest::STATUS_PENDING,
            ]);

            return $seatRequest;
        });
    }

    /**
     * Approve a pending seat request and raise the company limit.
     *
     * @throws ValidationException
     */
    public function approve(SeatRequest $seatRequest, User $reviewer, ?string $note = null): SeatRequest
    {
        return DB::transaction(function () use ($seatRequest, $reviewer, $note) {
            $seatRequest = SeatRequest::query()->lockForUpdate()->findOrFail($seatRequest->getKey());

            if (! $seatRequest->isPending()) {
                throw ValidationException::withMessages([
                    'review_note' => 'Only pending seat requests can be approved.',
                ]);
            }

            $company = Company::query()->lockForUpdate()->findOrFail($seatRequest->company_id);
            $oldLimit = $company->seat_limit;

            $seatRequest->update([
                'status' => SeatRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->getKey(),
                'review_note' => $note,
                'reviewed_at' => now(),
            ]);

            $this->quotas->grantBonus($company, $seatRequest->requested_seats, $reviewer, $note);

            AuditLog::record($seatRequest, 'seat_request.approved', $reviewer, [
                'status' => SeatRequest::STATUS_PENDING,
            ], [
                'status' => SeatRequest::STATUS_APPROVED,
                'requested_seats' => $seatRequest->requested_seats,
                'bonus_granted' => true,
            ]);

            AuditLog::record($company->refresh(), 'company.seat_limit_changed', $reviewer, [
                'seat_limit' => $oldLimit,
            ], [
                'seat_limit' => $company->seat_limit,
                'bonus_seats' => $company->bonus_seats,
            ], [
                'seat_request_id' => $seatRequest->getKey(),
            ]);

            return $seatRequest->refresh();
        });
    }

    /**
     * Reject a pending seat request.
     *
     * @throws ValidationException
     */
    public function reject(SeatRequest $seatRequest, User $reviewer, ?string $note = null): SeatRequest
    {
        return DB::transaction(function () use ($seatRequest, $reviewer, $note) {
            $seatRequest = SeatRequest::query()->lockForUpdate()->findOrFail($seatRequest->getKey());

            if (! $seatRequest->isPending()) {
                throw ValidationException::withMessages([
                    'review_note' => 'Only pending seat requests can be rejected.',
                ]);
            }

            $seatRequest->update([
                'status' => SeatRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->getKey(),
                'review_note' => $note,
                'reviewed_at' => now(),
            ]);

            AuditLog::record($seatRequest, 'seat_request.rejected', $reviewer, [
                'status' => SeatRequest::STATUS_PENDING,
            ], [
                'status' => SeatRequest::STATUS_REJECTED,
            ]);

            return $seatRequest->refresh();
        });
    }
}
