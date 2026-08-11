<?php

namespace App\Observers;

use App\Models\User;
use App\Support\SafeBroadcast;

class UserObserver
{
    public function created(User $user): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function updated(User $user): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function deleted(User $user): void
    {
        SafeBroadcast::demoEventPing();
    }

    public function restored(User $user): void {}

    public function forceDeleted(User $user): void {}
}
