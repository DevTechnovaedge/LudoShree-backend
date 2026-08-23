<?php

namespace App\Observers;

use App\Models\GameChallenge\Wallet;
use App\Models\User;
use App\Support\SafeBroadcast;

class WalletObserver
{
    public function created(Wallet $wallet): void
    {
        // WalletService writes the row *after* the balance has already moved, so
        // it supplies the real post-change total itself. The estimate below would
        // then add the amount a second time. Legacy callers leave the column unset
        // because they still create the row before saving the user.
        if ($wallet->win_and_game_total_amount !== null) {
            SafeBroadcast::demoEventPing();

            return;
        }

        $user = User::withoutGlobalScopes()
            ->select('id', 'win_wallet_amount', 'game_wallet_amount')
            ->find($wallet->user_id);
        if (! $user) {
            return;
        }

        $amount = $wallet->amount;

        if ($wallet->type == 'debit') {
            $wallet->win_and_game_total_amount = $user->total_wallet_amount - $amount;
        } elseif ($wallet->type == 'credit') {
            $amount = $wallet->status ? $wallet->amount : 0;
            $wallet->win_and_game_total_amount = $user->total_wallet_amount + $amount;
        }

        $wallet->saveQuietly();

        SafeBroadcast::demoEventPing();
    }

    public function updated(Wallet $wallet): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function deleted(Wallet $wallet): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function restored(Wallet $wallet): void {}

    public function forceDeleted(Wallet $wallet): void {}
}
