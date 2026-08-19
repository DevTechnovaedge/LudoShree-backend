<?php

namespace App\Services;

use App\Models\GameChallenge\GameChallenge;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes games that already have a decisive pair of player results:
 * both cancelled, or complementary win+lose.
 */
class GameChallengeAutoSettleService
{
    public function __construct(
        private readonly GameChallengeStakeRefundService $refunds,
        private readonly GameChallengeWinnerPayoutService $payouts,
    ) {}

    public function settleIfDecided(?GameChallenge $challenge): ?GameChallenge
    {
        if (! $challenge || ! $challenge->id) {
            return $challenge;
        }

        try {
            DB::transaction(function () use ($challenge): void {
                $row = GameChallenge::query()->lockForUpdate()->find($challenge->id);
                if (! $row) {
                    return;
                }

                if (in_array((int) $row->status, [4, 6, 7], true)) {
                    if ((int) $row->is_lock === 1) {
                        $row->is_lock = 0;
                        $row->save();
                    }

                    return;
                }

                $challengerStatus = (int) $row->challenger_status;
                $opponentStatus = (int) $row->opponent_status;

                if ($challengerStatus === 3 && $opponentStatus === 3) {
                    if (! in_array((int) $row->status, [3, 7], true)) {
                        $row->status = 3;
                    }
                    $row->closed_at = now();
                    $row->is_lock = 0;
                    $row->save();
                    $this->refunds->refundAllStakes($row);

                    Log::info('[challenge.auto-settle] mutual cancel closed', [
                        'game_challenge_id' => $row->id,
                        'uid' => $row->uid,
                    ]);

                    return;
                }

                $challengerWins = $challengerStatus === 1 && $opponentStatus === 2;
                $opponentWins = $challengerStatus === 2 && $opponentStatus === 1;
                if (! $challengerWins && ! $opponentWins) {
                    return;
                }

                $row->status = 4;
                $row->closed_at = now();
                $row->is_lock = 0;
                $row->save();

                $winnerId = $challengerWins ? $row->challenger_id : $row->opponent_id;
                $winner = User::query()->withoutGlobalScopes()->find($winnerId);
                if ($winner) {
                    $this->payouts->creditWinnerIfMissing($row, $winner);
                }

                Log::info('[challenge.auto-settle] complementary win/lose closed', [
                    'game_challenge_id' => $row->id,
                    'uid' => $row->uid,
                    'winner_id' => $winnerId,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[challenge.auto-settle] failed', [
                'game_challenge_id' => $challenge->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $challenge->fresh() ?: $challenge;
    }

    public function clearStaleLock(?GameChallenge $challenge, int $staleAfterSeconds = 90): bool
    {
        if (! $challenge || ! (int) $challenge->is_lock) {
            return false;
        }

        $raw = $challenge->getRawOriginal('updated_at');
        if (! is_string($raw) && ! $raw instanceof Carbon) {
            unlock_game_challenge($challenge);

            return true;
        }

        try {
            $updated = $raw instanceof Carbon ? $raw : Carbon::parse((string) $raw);
        } catch (\Throwable $e) {
            unlock_game_challenge($challenge);

            return true;
        }

        if ($updated->lt(now()->subSeconds($staleAfterSeconds))) {
            unlock_game_challenge($challenge);

            return true;
        }

        return false;
    }
}
