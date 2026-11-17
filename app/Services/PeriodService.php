<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MonthlyClosing;
use App\Models\PaymentRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PeriodService
{
    /**
     * Trailing 12-month close summary for the Finance tab.
     *
     * @return list<array<string, mixed>>
     */
    public function summary(): array
    {
        $closings = MonthlyClosing::query()->get()->keyBy('period');
        $periods = [];

        for ($i = 0; $i < 12; $i++) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');
            $closing = $closings->get($key);

            $periods[] = [
                'period' => $key,
                'label' => $month->format('F Y'),
                'status' => $closing?->status ?? MonthlyClosing::STATUS_OPEN,
                'closed_at' => $closing?->closed_at,
                'reopened_at' => $closing?->reopened_at,
                'reopen_reason' => $closing?->reopen_reason,
                'confirmed_payments' => (float) PaymentRecord::query()
                    ->where('status', PaymentRecord::STATUS_CONFIRMED)
                    ->whereBetween('received_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->sum('amount'),
            ];
        }

        return $periods;
    }

    public function periodFor(Carbon|string $date): string
    {
        return $date instanceof Carbon
            ? $date->format('Y-m')
            : Carbon::parse($date)->format('Y-m');
    }

    public function isClosed(Carbon|string $date): bool
    {
        $period = strlen((string) $date) === 7 && ! $date instanceof Carbon
            ? (string) $date
            : $this->periodFor($date);

        return MonthlyClosing::query()
            ->where('period', $period)
            ->where('status', MonthlyClosing::STATUS_CLOSED)
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    public function assertOpen(Carbon|string $date): void
    {
        if ($this->isClosed($date)) {
            throw ValidationException::withMessages([
                'period' => 'This financial period is closed. Reopen it before making changes.',
            ]);
        }
    }

    public function close(string $period, User $actor): MonthlyClosing
    {
        $this->assertPeriodFormat($period);

        $closing = MonthlyClosing::query()->firstOrCreate(['period' => $period]);

        if ($closing->isClosed()) {
            throw ValidationException::withMessages([
                'period' => 'This period is already closed.',
            ]);
        }

        $closing->update([
            'status' => MonthlyClosing::STATUS_CLOSED,
            'closed_by' => $actor->getKey(),
            'closed_at' => now(),
        ]);

        AuditLog::record($closing, 'period.closed', $actor, null, [
            'period' => $period,
        ]);

        return $closing->refresh();
    }

    public function reopen(string $period, User $actor, string $reason): MonthlyClosing
    {
        $this->assertPeriodFormat($period);

        $closing = MonthlyClosing::query()->where('period', $period)->first();

        if ($closing === null || ! $closing->isClosed()) {
            throw ValidationException::withMessages([
                'period' => 'Only a closed period can be reopened.',
            ]);
        }

        $closing->update([
            'status' => MonthlyClosing::STATUS_REOPENED,
            'reopened_by' => $actor->getKey(),
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ]);

        AuditLog::record($closing, 'period.reopened', $actor, [
            'status' => MonthlyClosing::STATUS_CLOSED,
        ], [
            'status' => MonthlyClosing::STATUS_REOPENED,
            'reason' => $reason,
        ]);

        return $closing->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function assertPeriodFormat(string $period): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            throw ValidationException::withMessages([
                'period' => 'The period must use the YYYY-MM format.',
            ]);
        }
    }
}
