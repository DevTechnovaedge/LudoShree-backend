<?php

namespace App\Livewire\Admin;

use App\Models\Financial\TransferCashback;
use App\Models\User;
use App\Services\AdminDashboardStatsService;
use Livewire\Component;

class TodayBusinessStatics extends Component
{
    protected $listeners = ['refreshComponent' => 'loadStatistics'];

    public string $filter_date = '';

    public $users_count = 0;
    public $users_kyc_complete_count = 0;
    public $users_kyc_pending_count = 0;
    public $successful_game_amount_count = 0;
    public $successful_games_count = 0;
    public $classic_games_count = 0;
    public $ulta_games_count = 0;
    public $deposit_amount_count = 0;
    public $withdrawal_amount_count = 0;
    public $total_refer_commission_amount = 0;
    public $total_admin_commission_amount = 0;
    public $win_to_game_cashback = 0;

    public function mount(): void
    {
        $this->filter_date = $this->resolveFilterDate(request()->query('stats_date'));
        $this->loadStatistics();
    }

    public function loadStatistics(): void
    {
        $stats = app(AdminDashboardStatsService::class);
        $this->filter_date = $this->resolveFilterDate($this->filter_date);
        $filterDay = $stats->filterDateString($this->filter_date);

        // Same base query as admin users list (includes inactive accounts).
        $users = User::withoutGlobalScope('active')->whereDate('created_at', $filterDay);
        $this->users_count = $users->clone()->count();
        $this->users_kyc_complete_count = $users->clone()->whereKycStatus(1)->count();
        $this->users_kyc_pending_count = $users->clone()->whereKycStatus(0)->count();

        $game_challenges = $stats->completedGamesQuery($this->filter_date);
        $this->successful_game_amount_count = (float) $game_challenges->clone()->sum('amount');
        $this->successful_games_count = $game_challenges->clone()->count();
        $this->classic_games_count = $game_challenges->clone()->whereGameTypeId(1)->count();
        $this->ulta_games_count = $game_challenges->clone()->whereGameTypeId(2)->count();

        $this->deposit_amount_count = $stats->depositApprovedSumForDate($this->filter_date);
        $this->withdrawal_amount_count = $stats->withdrawalApprovedSumForDate($this->filter_date);

        $commission_history = $stats->commissionHistoryForDate($this->filter_date);
        $this->total_refer_commission_amount = (float) $commission_history->clone()->sum('refer_commission_amount');
        $this->total_admin_commission_amount = (float) $commission_history->clone()->sum('game_commission_amount');

        $this->win_to_game_cashback = (float) TransferCashback::whereDate('created_at', $filterDay)->sum('cashback_amount');
    }

    public function render()
    {
        return view('livewire.admin.today-business-statics');
    }

    private function resolveFilterDate(?string $date): string
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return carbon()->now()->format('Y-m-d');
        }

        return $date;
    }
}
