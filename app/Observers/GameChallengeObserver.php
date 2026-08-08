<?php

namespace App\Observers;

use App\Events\DemoEvent;
use App\Events\GameChallengeChanged;
use App\Models\GameChallenge\GameChallenge;
use App\Support\SafeBroadcast;

class GameChallengeObserver
{
    public function created(GameChallenge $record): void
    {
        $this->broadcast($record, 'created');
    }

    public function updated(GameChallenge $record): void
    {
        // Ignore lock-only toggles (concurrency flag, not a gameplay change).
        $meaningful = collect($record->getChanges())->except(['updated_at', 'is_lock']);
        if ($meaningful->isEmpty()) {
            return;
        }

        $this->broadcast($record, 'updated');
    }

    public function deleted(GameChallenge $record): void
    {
        $this->broadcast($record, 'deleted');
    }

    public function restored(GameChallenge $record): void
    {
        //
    }

    public function forceDeleted(GameChallenge $record): void
    {
        //
    }

    private function broadcast(GameChallenge $record, string $action): void
    {
        SafeBroadcast::event(new DemoEvent(''));
        SafeBroadcast::event(GameChallengeChanged::fromModel($record, $action));
    }
}
