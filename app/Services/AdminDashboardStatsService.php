<?php

namespace App\Services;

use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Date-scoped business stats for the admin dashboard (aligned with Ludo Cashier withdrawal totals).
 */
class AdminDashboardStatsService
{
    public function parseFilterDate(?string $date): Carbon
    {
        return $date
            ? Carbon::parse($date)->startOfDay()
            : Carbon::today();
    }

    public function filterDateString(?string $date): string
    {
        return $this->parseFilterDate($date)->toDateString();
    }

    /**
     * Successful deposits credited/approved on the selected day (by updated_at).
     */
    public function depositApprovedSumForDate(?string $date): float
    {
        return round((float) $this->successfulDepositsQuery($date)->sum('amount'), 2);
    }

    /**
     * Successful withdrawals processed on the selected day (by updated_at — same as cashier app).
     */
    public function withdrawalApprovedSumForDate(?string $date): float
    {
        return round((float) $this->successfulWithdrawalsQuery($date)->sum('amount'), 2);
    }

    public function successfulDepositsQuery(?string $date): Builder
    {
        $day = $this->filterDateString($date);

        return Transaction::query()
            ->where('transfer_type', 'deposit')
            ->where('status', 1)
            ->whereDate('updated_at', $day);
    }

    public function successfulWithdrawalsQuery(?string $date): Builder
    {
        $day = $this->filterDateString($date);

        return Transaction::query()
            ->where('transfer_type', 'withdrawal')
            ->where('status', 1)
            ->whereDate('updated_at', $day);
    }

    /**
     * Completed games for the selected calendar day (closed_at, fallback created_at).
     */
    public function completedGamesQuery(?string $date): Builder
    {
        $day = $this->filterDateString($date);

        return GameChallenge::query()
            ->where('status', 4)
            ->where(function (Builder $query) use ($day) {
                $query->whereDate('closed_at', $day)
                    ->orWhere(function (Builder $inner) use ($day) {
                        $inner->whereNull('closed_at')
                            ->whereDate('created_at', $day);
                    });
            });
    }

    public function commissionHistoryForDate(?string $date): Builder
    {
        return CommissionHistory::query()
            ->whereDate('created_at', $this->filterDateString($date));
    }
}
