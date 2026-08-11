<?php

namespace App\Observers;

use App\Models\GameChallenge\Transaction;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        Log::info('Transaction created:');
        SafeBroadcast::demoEventPing();
    }

    public function updated(Transaction $transaction): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function deleted(Transaction $transaction): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function restored(Transaction $transaction): void {}

    public function forceDeleted(Transaction $transaction): void {}
}
