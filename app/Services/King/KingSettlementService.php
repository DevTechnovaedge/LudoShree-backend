<?php

namespace App\Services\King;

use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Wallet;
use App\Models\King\KingEventLog;
use App\Models\King\KingOutbox;
use App\Models\Notification\Notification;
use App\Models\User;
use App\Services\GameChallengeStakeRefundService;
use App\Services\GameChallengeWaitingDismissService;
use App\Services\GameChallengeWinnerPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Money + game-state transitions for cross-platform (Daddy King) games.
 *
 * Rules (as agreed with the King network):
 *  - Each platform handles ONLY its own users' wallets. Ghost users never
 *    receive or pay real money here.
 *  - Every mutation is idempotent: the daemon may process the same remote
 *    event twice (push + poll) without double-crediting.
 */
class KingSettlementService
{
    public function __construct(
        private readonly GameChallengeStakeRefundService $refunds,
        private readonly GameChallengeWaitingDismissService $waitingDismiss,
        private readonly GameChallengeWinnerPayoutService $payouts,
    ) {}

    /* =====================================================================
     * Accept (local user joins a King-synced table, confirmed by King first)
     * ===================================================================== */

    /**
     * Called by the daemon after the King server confirmed KingAcceptRequest.
     * Debits the joiner and attaches them as opponent.
     *
     * @return array{ok: bool, reason: string}
     */
    public function finalizeLocalAccept(KingOutbox $outbox): array
    {
        $challenge = GameChallenge::find($outbox->game_challenge_id);
        if (! $challenge) {
            return ['ok' => false, 'reason' => 'Game Challenge not found'];
        }

        $payload = $outbox->payloadArray();
        $joiner = User::find((int) ($payload['userId'] ?? $outbox->acting_user_id));
        if (! $joiner) {
            return ['ok' => false, 'reason' => 'Joining user not found'];
        }

        // Idempotent: already finalized.
        if ((int) $challenge->opponent_id === (int) $joiner->id && (int) $challenge->status === 1) {
            return ['ok' => true, 'reason' => 'already accepted'];
        }

        if ($challenge->opponent_id) {
            KingEventLog::write('sys', 'KingAcceptRequest', 'error',
                "CONSISTENCY: King confirmed accept for table {$challenge->king_table_id} but challenge #{$challenge->id} already has opponent #{$challenge->opponent_id}");

            return ['ok' => false, 'reason' => 'Game Challenge already accepted'];
        }

        $fee = (float) $challenge->amount;
        $debited = false;

        DB::transaction(function () use ($challenge, $joiner, $fee, &$debited) {
            // Close other waiting tables (their King copies are deleted via
            // the dismiss service hook).
            $this->waitingDismiss->dismissWaitingGamesForChallenger($joiner->id);

            if (! is_king_ghost_user($challenge->challenger_id)) {
                $this->waitingDismiss->dismissWaitingGamesForChallenger((int) $challenge->challenger_id, (int) $challenge->id);
            }

            $debited = $this->debitStake($joiner, $challenge, $fee, "Challenge accepted. Ref: $challenge->uid");
        });

        if (! $debited) {
            // Extremely rare race: balance dropped between the HTTP pre-check
            // and King's confirmation. The King table is already "Start", so
            // cancel it on the network and locally.
            KingEventLog::write('sys', 'KingAcceptRequest', 'warning',
                "Accept confirmed by King but user #{$joiner->id} had insufficient balance. Cancelling table {$challenge->king_table_id}.");

            app(KingOutboxService::class)->enqueueResult($challenge, $joiner->id, 'Cancel');

            $challenge->status = 7;
            $challenge->challenger_status = 3;
            $challenge->opponent_status = 3;
            $challenge->is_lock = 0;
            $challenge->save();

            if (! is_king_ghost_user($challenge->challenger_id)) {
                $this->refunds->refundUserStake((int) $challenge->id, (int) $challenge->challenger_id, "Challenge Refund Ref: {$challenge->uid}");
            }

            return ['ok' => false, 'reason' => 'Insufficient Balance'];
        }

        $challenge->opponent_id = $joiner->id;
        $challenge->opponent_amount = $fee;
        $challenge->amount = $fee * 2; // same as the local accept flow: amount becomes the total pot
        $challenge->status = 1;
        $challenge->is_lock = 0;
        $challenge->save();

        $this->notifyUser($challenge->challenger, 'Challenge accepted', 'Game Challenge accepted: ' . $challenge->uid, 'accept', $joiner->id);

        return ['ok' => true, 'reason' => 'accepted'];
    }

    /* =====================================================================
     * Remote events applied to our local tables
     * ===================================================================== */

    /**
     * A Daddy King network player joined a table created by OUR user.
     * No local debit for the ghost - their stake lives on their platform.
     */
    public function applyRemoteAccept(GameChallenge $challenge, User $ghost): void
    {
        if ($challenge->opponent_id) {
            if ((int) $challenge->opponent_id !== (int) $ghost->id) {
                KingEventLog::write('sys', 'KingAcceptRequest', 'error',
                    "CONSISTENCY: table {$challenge->king_table_id} joined remotely by ghost #{$ghost->id} but challenge #{$challenge->id} already has opponent #{$challenge->opponent_id}. Remote join rejected - cancelling on King network.");

                // Our local accept wins (money already moved locally).
                app(KingOutboxService::class)->enqueueResult($challenge, (int) $challenge->challenger_id, 'Cancel');
            }

            return;
        }

        if ((int) $challenge->status !== 0) {
            return;
        }

        $fee = (float) $challenge->amount;

        $challenge->opponent_id = $ghost->id;
        $challenge->opponent_amount = $fee;
        $challenge->amount = $fee * 2; // same as the local accept flow: amount becomes the total pot
        $challenge->status = 1;
        $challenge->is_lock = 0;
        $challenge->save();

        // Creator is now in a live game - close their other waiting tables.
        if (! is_king_ghost_user($challenge->challenger_id)) {
            try {
                $this->waitingDismiss->dismissWaitingGamesForChallenger((int) $challenge->challenger_id, (int) $challenge->id);
            } catch (\Throwable $e) {
                Log::warning('[King] waiting dismiss after remote accept failed', ['error' => $e->getMessage()]);
            }
        }

        $this->notifyUser($challenge->challenger, 'Challenge accepted', 'Game Challenge accepted: ' . $challenge->uid, 'accept', $ghost->id);
    }

    /**
     * Room code set by the remote creator of a table our user joined.
     */
    public function applyRemoteRoomcode(GameChallenge $challenge, string $code): void
    {
        $code = trim($code);
        if ($code === '' || $challenge->roomcode) {
            return;
        }

        $isCancelled = ((int) $challenge->challenger_status === 3) || ((int) $challenge->opponent_status === 3);

        $challenge->roomcode = $code;
        $challenge->ludo_king_game_id = $code;
        $challenge->roomcode_datetime = date('Y-m-d H:i:s');
        $challenge->status = $isCancelled ? 3 : 1;
        $challenge->is_lock = 0;
        $challenge->save();

        $localSide = $this->localSideUser($challenge);
        if ($localSide) {
            $this->notifyUser($localSide, 'Roomcode added', 'Game Challenge roomcode updated Ref: ' . $challenge->uid, 'roomcode', $localSide->id);
        }
    }

    /**
     * Result reported by the remote platform for THEIR player (the ghost side).
     *
     * @param  string  $outcome  win | loss | cancel
     */
    public function applyRemoteResult(GameChallenge $challenge, string $outcome): void
    {
        if (in_array((int) $challenge->status, [4, 6, 7], true)) {
            return; // already terminal
        }

        $ghostIsChallenger = is_king_ghost_user($challenge->challenger_id);
        $ghostStatusField = $ghostIsChallenger ? 'challenger_status' : 'opponent_status';
        $localStatusField = $ghostIsChallenger ? 'opponent_status' : 'challenger_status';

        $ghostStatus = (int) $challenge->{$ghostStatusField};
        $localStatus = (int) $challenge->{$localStatusField};

        $localUser = $this->localSideUser($challenge);

        switch ($outcome) {
            case 'win':
                if ($ghostStatus === 1) {
                    return; // already applied
                }

                $challenge->{$ghostStatusField} = 1;

                if ($localStatus === 2) {
                    $challenge->status = 4; // local user already admitted loss
                } elseif ($localStatus === 1) {
                    $challenge->status = 5; // both claim win -> dispute for admin
                    KingEventLog::write('sys', 'ResultUpdateRequest', 'warning',
                        "DISPUTE: both sides claim win on table {$challenge->king_table_id} (challenge #{$challenge->id}). Admin action required.");
                } else {
                    $challenge->status = 8; // waiting for local user's result
                }

                $challenge->is_lock = 0;
                $challenge->save();
                break;

            case 'loss':
                if ($ghostStatus === 2) {
                    return;
                }

                $challenge->{$ghostStatusField} = 2;
                $challenge->is_lock = 0;
                $challenge->save();

                // Local user already claimed win (or claims it later via the
                // app - handled by the gateway hook).
                if ($localStatus === 1 && $localUser) {
                    $this->completeWithLocalWinner($challenge, $localUser);
                }
                break;

            case 'cancel':
                if ($ghostStatus === 3) {
                    return;
                }

                $challenge->{$ghostStatusField} = 3;

                if (! $challenge->roomcode || $localStatus === 3) {
                    // Mutual cancel (or never started) -> refund local stakes.
                    $challenge->status = ! $challenge->roomcode ? 3 : 2;
                    if (! $challenge->roomcode) {
                        $challenge->{$localStatusField} = 3;
                    }
                    $challenge->is_lock = 0;
                    $challenge->save();

                    $this->refundLocalSides($challenge);
                } elseif ($localStatus === 1) {
                    $challenge->status = 5; // remote cancels while local claims win -> admin review
                    $challenge->is_lock = 0;
                    $challenge->save();
                } else {
                    $challenge->status = 2; // cancel in progress, local user can still respond
                    $challenge->is_lock = 0;
                    $challenge->save();
                }
                break;
        }
    }

    /**
     * Remote table (ghost creator, still waiting) was deleted / taken on
     * another platform: close the local mirror challenge. Nothing to refund -
     * the ghost never paid locally.
     */
    public function closeRemoteWaitingChallenge(GameChallenge $challenge, string $reason): void
    {
        if ((int) $challenge->status !== 0 || $challenge->opponent_id) {
            return;
        }

        $challenge->status = 7;
        $challenge->challenger_status = 3;
        $challenge->challenger_remark = $reason;
        $challenge->is_lock = 0;
        $challenge->save();
    }

    /* =====================================================================
     * Payout / refunds (idempotent)
     * ===================================================================== */

    /**
     * Finish a cross-platform game with a LOCAL winner: statuses + one-time
     * payout of paid_amount (pot minus commission) into the win wallet.
     */
    public function completeWithLocalWinner(GameChallenge $challenge, User $winner): void
    {
        $winnerIsChallenger = (int) $challenge->challenger_id === (int) $winner->id;

        $challenge->challenger_status = $winnerIsChallenger ? 1 : 2;
        $challenge->opponent_status = $winnerIsChallenger ? 2 : 1;
        $challenge->status = 4;
        $challenge->closed_at = date('Y-m-d H:i:s');
        $challenge->is_lock = 0;
        $challenge->save();

        $this->creditWinnerPayoutIfMissing($challenge, $winner);
    }

    /**
     * One-time winner credit. Safe to call from multiple code paths.
     */
    public function creditWinnerPayoutIfMissing(GameChallenge $challenge, User $winner): bool
    {
        if (is_king_ghost_user($winner)) {
            return false;
        }

        return (bool) DB::transaction(function () use ($challenge, $winner) {
            $locked = User::query()->withoutGlobalScopes()->lockForUpdate()->find($winner->id);
            if (! $locked) {
                return false;
            }

            $alreadyPaid = Wallet::query()
                ->where('game_challenge_id', $challenge->id)
                ->where('user_id', $locked->id)
                ->where('type', 'credit')
                ->where(function ($q) {
                    $q->where('remark', 'like', 'Winner amount Ref:%')
                        ->orWhere('remark', 'like', 'Winner Ref:%');
                })
                ->exists();

            if ($alreadyPaid) {
                return false;
            }

            $winAmount = (float) $challenge->paid_amount;
            $total = (float) $locked->win_wallet_amount + $winAmount;

            Wallet::create([
                'user_id' => $locked->id,
                'game_challenge_id' => $challenge->id,
                'type' => 'credit',
                'wallet_type' => 'win',
                'remark' => "Winner amount Ref: $challenge->uid",
                'amount' => $winAmount,
                'total_balance' => $total,
                'status' => 1,
            ]);

            $locked->win_wallet_amount = $total;
            $locked->save();

            $this->notifyUser($locked, 'Winner', "Congratulation, you win. Ref: $challenge->uid", 'winner', $locked->id);

            return true;
        });
    }

    /**
     * Refund all real (non-ghost) participants' stakes.
     */
    public function refundLocalSides(GameChallenge $challenge): void
    {
        foreach ([(int) $challenge->challenger_id, (int) $challenge->opponent_id] as $userId) {
            if (! $userId || is_king_ghost_user($userId)) {
                continue;
            }

            try {
                $this->refunds->refundUserStake((int) $challenge->id, $userId, "Challenge Refund Ref: {$challenge->uid}");
            } catch (\Throwable $e) {
                Log::error('[King] refund failed', ['challenge_id' => $challenge->id, 'user_id' => $userId, 'error' => $e->getMessage()]);
                KingEventLog::write('sys', null, 'error', "Refund failed for user #$userId on challenge #{$challenge->id}: " . $e->getMessage());
            }
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    /**
     * Debit stake game-wallet-first then win-wallet, inside the caller's
     * transaction. Returns false when balance is insufficient.
     */
    private function debitStake(User $user, GameChallenge $challenge, float $fee, string $remark): bool
    {
        $locked = User::query()->withoutGlobalScopes()->lockForUpdate()->find($user->id);
        if (! $locked) {
            return false;
        }

        $gameWallet = (float) $locked->game_wallet_amount;
        $winWallet = (float) $locked->win_wallet_amount;

        if ($gameWallet + $winWallet < $fee) {
            return false;
        }

        if ($gameWallet >= $fee) {
            $newBalance = $gameWallet - $fee;
            Wallet::create([
                'user_id' => $locked->id,
                'game_challenge_id' => $challenge->id,
                'type' => 'debit',
                'wallet_type' => 'game',
                'remark' => $remark,
                'amount' => $fee,
                'total_balance' => $newBalance,
                'status' => 0,
            ]);
            $locked->game_wallet_amount = $newBalance;
        } else {
            $remaining = $fee - $gameWallet;

            if ($gameWallet > 0) {
                Wallet::create([
                    'user_id' => $locked->id,
                    'game_challenge_id' => $challenge->id,
                    'type' => 'debit',
                    'wallet_type' => 'game',
                    'remark' => $remark,
                    'amount' => $gameWallet,
                    'total_balance' => 0,
                    'status' => 0,
                ]);
                $locked->game_wallet_amount = 0;
            }

            $newWinBalance = $winWallet - $remaining;
            Wallet::create([
                'user_id' => $locked->id,
                'game_challenge_id' => $challenge->id,
                'type' => 'debit',
                'wallet_type' => 'win',
                'remark' => $remark,
                'amount' => $remaining,
                'total_balance' => $newWinBalance,
                'status' => 0,
            ]);
            $locked->win_wallet_amount = $newWinBalance;
        }

        $locked->save();

        return true;
    }

    private function localSideUser(GameChallenge $challenge): ?User
    {
        if ($challenge->challenger_id && ! is_king_ghost_user($challenge->challenger_id)) {
            return User::find($challenge->challenger_id);
        }

        if ($challenge->opponent_id && ! is_king_ghost_user($challenge->opponent_id)) {
            return User::find($challenge->opponent_id);
        }

        return null;
    }

    private function notifyUser(?User $user, string $title, string $body, string $type, int $notificationUserId): void
    {
        if (! $user || is_king_ghost_user($user)) {
            return;
        }

        try {
            if ($user->fcm_device_token) {
                fcm()->send((object) [
                    'title' => $title,
                    'body' => $body,
                    'notification_type' => $type,
                    'fcm_device_token' => $user->fcm_device_token,
                ]);
            }

            Notification::create([
                'user_ids' => (string) $notificationUserId,
                'title' => $title,
                'content' => $body,
                'notification_type' => $type,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[King] notification failed', ['error' => $e->getMessage()]);
        }
    }
}
