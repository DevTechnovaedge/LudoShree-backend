<?php

namespace App\Observers;

use App\Events\DemoEvent;
use App\Models\User;
use App\Support\SafeBroadcast;

class UserObserver
{
    public function created(User $user): void
    {
        SafeBroadcast::event(new DemoEvent(''));
    }

    public function updated(User $user): void
    {
        SafeBroadcast::event(new DemoEvent(''));
    }

    public function deleted(User $user): void
    {
        SafeBroadcast::event(new DemoEvent(''));
    }

    public function restored(User $user): void {}
    public function forceDeleted(User $user): void {}
}
