<?php
namespace App\Livewire\Admin;

use App\Models\Financial\TransferCashback;
use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Models\User;
use Livewire\Component;

class TotalBusinessStatics extends Component
{
       protected $listeners = ['refreshComponent' => '$refresh'];
    // protected $listeners = ['refreshTotalBusinessStatics' => '$refresh'];

    public $users_count, $users_kyc_complete_count, $users_kyc_pending_count, $total_win_amount, $total_game_amount;
    public $successful_game_amount_count, $successful_games_count, $classic_games_count, $ulta_games_count;
    public $deposit_amount_count, $withdrawal_amount_count;
    public $total_refer_commission_amount, $total_admin_commission_amount, $win_to_game_cashback;

    public function render()
    {
        # User
        $users                                  = User::query();
        $this->users_count                      = $users->clone()->count();
        $this->total_win_amount                 = $users->clone()->sum('win_wallet_amount');
        $this->total_game_amount                = $users->clone()->sum('game_wallet_amount');
        $this->users_kyc_complete_count         = $users->clone()->whereStatus(1)->whereKycStatus(1)->count();
        $this->users_kyc_pending_count          = $users->clone()->whereStatus(1)->whereKycStatus(0)->count();
        # End User

        # Game Challenges
        $game_challenges                        =   GameChallenge::query();
        $this->successful_game_amount_count     =   $game_challenges->clone()->whereStatus(4)->sum('amount');
        $this->successful_games_count           =   $game_challenges->clone()->whereStatus(4)->count();
        $this->classic_games_count              =   $game_challenges->clone()->whereStatus(4)->whereGameTypeId(1)->count();
        $this->ulta_games_count                 =   $game_challenges->clone()->whereStatus(4)->whereGameTypeId(2)->count();
        # End Game Challenges

        # Transactions
        $transactions                           =   Transaction::query();
        $this->deposit_amount_count             =   $transactions->clone()->whereTransferType('deposit')->whereStatus(1)->sum('amount');
        $this->withdrawal_amount_count          =   $transactions->clone()->whereTransferType('withdrawal')->whereStatus(1)->sum('amount');
        # End Transactions
        
        # Commission History
        $commission_history                     =   CommissionHistory::query();
        $this->total_refer_commission_amount    =   $commission_history->clone()->sum('refer_commission_amount');
        $this->total_admin_commission_amount    =   $commission_history->clone()->sum('game_commission_amount');
        # End Commission History
        
        # 
        $transferCashback   =   TransferCashback::query();
        $this->win_to_game_cashback    =   $transferCashback->sum('cashback_amount');

        return view('livewire.admin.total-business-statics');
    }
}
