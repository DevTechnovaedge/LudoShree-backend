<?php

namespace App\Services;

use App\Models\GameChallenge\GameChallenge;
use Illuminate\Support\Facades\DB;

/**
 * When a challenge is accepted, close other waiting table entries and refund stakes.
 */
class GameChallengeWaitingDismissService
{
    public function __construct(
        private readonly GameChallengeStakeRefundService $refunds,
    ) {}

    /**
     * @param  int|null  $exceptChallengeId  Keep this row (the challenge being accepted).
     */
    public function dismissWaitingGamesForChallenger(int $challengerId, ?int $exceptChallengeId = null): void
    {
        DB::transaction(function () use ($challengerId, $exceptChallengeId) {
            $query = GameChallenge::query()
                ->where('challenger_id', $challengerId)
                ->where('status', 0)
                ->where(function ($q) {
                    $q->whereNull('opponent_id')->orWhere('opponent_id', 0);
                });

            if ($exceptChallengeId !== null) {
                $query->where('id', '!=', $exceptChallengeId);
            }

            $waiting = $query->lockForUpdate()->get();

            foreach ($waiting as $gameChallenge) {
                $this->refunds->refundAllStakes($gameChallenge);

                $gameChallenge->status = 7;
                $gameChallenge->challenger_status = 3;
                if (! $gameChallenge->challenger_remark) {
                    $gameChallenge->challenger_remark = 'Auto-closed: another challenge was accepted';
                }
                $gameChallenge->save();

                // Table was pushed to the King (Daddy King) network - remove
                // it there too (queued, no network call here).
                try {
                    app(\App\Services\King\KingChallengeGateway::class)->afterWaitingChallengeClosed($gameChallenge);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('[King] dismiss hook failed', ['error' => $e->getMessage()]);
                }
            }
        });
    }
}
