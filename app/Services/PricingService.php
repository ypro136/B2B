<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyTripPrice;
use App\Models\Trip;
use App\Models\User;

class PricingService
{
    /**
     * Resolve the final company-facing unit price for a trip.
     */
    public function unitPriceFor(Company $company, Trip $trip): float
    {
        $override = CompanyTripPrice::query()
            ->where('company_id', $company->getKey())
            ->where('trip_id', $trip->getKey())
            ->value('price');

        return round((float) ($override ?? $trip->standard_price), 2);
    }

    /**
     * Set or update a company-specific price override for one trip.
     */
    public function setOverride(Company $company, Trip $trip, float $price, User $actor): CompanyTripPrice
    {
        $override = CompanyTripPrice::query()->updateOrCreate(
            ['company_id' => $company->getKey(), 'trip_id' => $trip->getKey()],
            ['price' => $price, 'created_by' => $actor->getKey()],
        );

        AuditLog::record($trip, 'trip.company_price_set', $actor, null, [
            'company_id' => $company->getKey(),
            'price' => $price,
        ]);

        return $override;
    }

    /**
     * Update the per-seat cost used by profit reporting.
     */
    public function setCost(Trip $trip, float $cost, User $actor): Trip
    {
        $old = $trip->per_seat_cost;
        $trip->update(['per_seat_cost' => $cost]);

        AuditLog::record($trip, 'trip.cost_updated', $actor, [
            'per_seat_cost' => $old,
        ], [
            'per_seat_cost' => $cost,
        ]);

        return $trip->refresh();
    }
}
