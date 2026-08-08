<?php

namespace App\Observers;
use App\Models\GameChallenge\Wallet;
use App\Events\DemoEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WalletObserver
{
    public function created(Wallet $wallet): void {

        // Log::info('Wallet created');

        $user           =   User::select('id', 'win_wallet_amount', 'game_wallet_amount')->find($wallet->user_id);
        $amount         =   $wallet->amount;
        
        if($wallet->type == 'debit'):
            $wallet->win_and_game_total_amount      =  $user->total_wallet_amount - $amount;
        elseif($wallet->type == 'credit'):
                $amount         =   $wallet->status ? $wallet->amount : 0;
            $wallet->win_and_game_total_amount      =   $user->total_wallet_amount + $amount;
        endif;
        
        $wallet->saveQuietly();

        // Code to run after a user is created
        event(new DemoEvent(''));
    }
    
    public function updated(Wallet $wallet): void {
        Log::info('Wallet updated');
        // $user->name = 'ddd';
        // $user->saveQuietly();
      
        event(new DemoEvent(''));
    }

    public function deleted(Wallet $wallet): void {
        // Code to run after a user is deleted
        event(new DemoEvent(''));
    }
    public function restored(Wallet $wallet): void {
        // Code to run after a user is restored
    }
    public function forceDeleted(Wallet $wallet): void {
        // Code to run after a user is permanently deleted
    }
}

?>