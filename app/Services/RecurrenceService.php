<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Trip;
use App\Models\TripTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurrenceService
{
    /**
     * Generate trips for the next number of days from a recurring template.
     */
    public function generate(TripTemplate $template, User $actor, int $days = 14): int
    {
        return DB::transaction(function () use ($template, $actor, $days) {
            $created = 0;
            $today = Carbon::today();

            for ($offset = 0; $offset < $days; $offset++) {
                $date = $today->copy()->addDays($offset);

                if (! $template->runsOn($date->dayOfWeek)) {
                    continue;
                }

                if ($template->valid_from !== null && $date->lt($template->valid_from)) {
                    continue;
                }

                if ($template->valid_until !== null && $date->gt($template->valid_until)) {
                    continue;
                }

                $departure = $date->copy()->setTimeFromTimeString($template->departure_time);

                if ($departure->isPast()) {
                    continue;
                }

                $trip = Trip::query()->firstOrCreate(
                    [
                        'template_id' => $template->getKey(),
                        'departure_start' => $date->copy()->startOfDay(),
                    ],
                    [
                        'route_name' => $template->route_name,
                        'origin' => $template->origin,
                        'destination' => $template->destination,
                        'departure_datetime' => $departure,
                        'total_seats' => $template->total_seats,
                        'standard_price' => $template->standard_price,
                        'per_seat_cost' => $template->per_seat_cost,
                        'currency' => 'EGP',
                        'status' => Trip::STATUS_SCHEDULED,
                        'created_by' => $actor->getKey(),
                    ],
                );

                if ($trip->wasRecentlyCreated) {
                    $trip->ensureSeats();
                    $created++;
                }
            }

            AuditLog::record($template, 'template.trips_generated', $actor, null, [
                'days' => $days,
                'created' => $created,
            ]);

            return $created;
        });
    }
}
