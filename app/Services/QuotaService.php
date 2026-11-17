<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    /**
     * Summary of the company's seat allowance.
     *
     * @return array{base:int, bonus:int, limit:int, used:int, remaining:int}
     */
    public function summary(Company $company): array
    {
        return [
            'base' => $company->seat_limit,
            'bonus' => $company->bonus_seats,
            'limit' => $company->effectiveSeatLimit(),
            'used' => $company->usedSeats(),
            'remaining' => $company->availableSeats(),
        ];
    }

    /**
     * Reset the usage window and clear unused one-time bonus seats.
     */
    public function reset(Company $company, User $actor): Company
    {
        return DB::transaction(function () use ($company, $actor) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->getKey());

            $old = [
                'quota_reset_at' => $company->quota_reset_at?->toIso8601String(),
                'bonus_seats' => $company->bonus_seats,
            ];

            $company->update([
                'quota_reset_at' => now(),
                'bonus_seats' => 0,
            ]);

            AuditLog::record($company, 'company.quota_reset', $actor, $old, [
                'quota_reset_at' => $company->quota_reset_at?->toIso8601String(),
                'bonus_seats' => 0,
            ]);

            return $company->refresh();
        });
    }

    /**
     * Grant one-time bonus seats (used by approved seat requests).
     */
    public function grantBonus(Company $company, int $seats, User $actor, ?string $note = null): Company
    {
        return DB::transaction(function () use ($company, $seats, $actor, $note) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            $oldBonus = $company->bonus_seats;

            $company->update(['bonus_seats' => $oldBonus + $seats]);

            AuditLog::record($company, 'company.bonus_seats_granted', $actor, [
                'bonus_seats' => $oldBonus,
            ], [
                'bonus_seats' => $company->bonus_seats,
                'granted_seats' => $seats,
            ], $note !== null ? ['note' => $note] : null);

            return $company->refresh();
        });
    }
}
