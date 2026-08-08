<?php

namespace App\Observers;
use App\Models\GameChallenge\Transaction;
use App\Events\DemoEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function created(Transaction $user): void {
        // Code to run after a user is created
        Log::info('Transaction created:');
        event(new DemoEvent(''));
    }
    
    public function updated(Transaction $transaction): void {
        
            // Trigger the event
            event(new DemoEvent(''));
       
    }

    public function deleted(Transaction $user): void {
        // Code to run after a user is deleted
        event(new DemoEvent(''));
    }
    public function restored(Transaction $user): void {
        // Code to run after a user is restored
    }
    public function forceDeleted(Transaction $user): void {
        // Code to run after a user is permanently deleted
    }
}

?>