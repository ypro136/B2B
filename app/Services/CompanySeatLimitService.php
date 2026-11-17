<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanySeatLimitService
{
    /**
     * Update a company's seat limit while protecting seats already in use.
     *
     * @throws ValidationException
     */
    public function update(Company $company, int $seatLimit, User $actor): Company
    {
        return DB::transaction(function () use ($company, $seatLimit, $actor) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->getKey());

            if ($seatLimit < 0) {
                throw ValidationException::withMessages([
                    'seat_limit' => 'The seat limit cannot be negative.',
                ]);
            }

            $seatsInUse = $company->confirmedSeats();

            if ($seatLimit < $seatsInUse) {
                throw ValidationException::withMessages([
                    'seat_limit' => "The seat limit cannot be lower than the {$seatsInUse} seat(s) already held.",
                ]);
            }

            $oldLimit = $company->seat_limit;

            if ($oldLimit === $seatLimit) {
                return $company;
            }

            $company->update(['seat_limit' => $seatLimit]);

            AuditLog::record($company, 'company.seat_limit_changed', $actor, [
                'seat_limit' => $oldLimit,
            ], [
                'seat_limit' => $seatLimit,
            ]);

            return $company->refresh();
        });
    }
}
