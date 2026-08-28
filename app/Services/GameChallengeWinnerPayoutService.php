<?php

namespace App\Services;

use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies the same winnings settlement as admin "challenger win" / "opponent win" actions.
 *
 * Intended for reuse by admin manual updates and automated LK API resolution.
 */
class GameChallengeWinnerPayoutService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly GameChallengeStakeRefundService $refunds,
    ) {}

    public function awardChallengerWin(GameChallenge $game_challenge): void
    {
        $game_challenge->loadMissing(['challenger', 'opponent']);

        $challenger = User::query()->withoutGlobalScopes()->find($game_challenge->challenger_id);
        if (!$challenger) {
            return;
        }

        if (!$game_challenge->opponent_id) {
            return;
        }

        $game_challenge->challenger_status = 1;
        $game_challenge->opponent_status = 2;
        $game_challenge->status = 4;

        // King ghost winner: statuses only - the payout happens on the
        // player's own platform (Daddy King network rule).
        if (is_king_ghost_user($challenger)) {
            return;
        }

        $this->creditWinnerIfMissing($game_challenge, $challenger);

        $refer_user = null;
        $refer_commission_amount = 0;
        $refer_commission = game_commission_slot()->refer_commission;

        $challenger_refer_by = $game_challenge->challenger->refer_by ?? null;

        if ($challenger_refer_by && $game_challenge->game_commission_amount) {
            $refer_user = User::find($challenger_refer_by);

            if ($refer_user && ((int) $refer_user->refer_income === 1)) {
                if ($refer_user->commission) {
                    $refer_commission = $refer_user->commission;
                }

                $refer_commission_amount = ($game_challenge->challenger_amount * $refer_commission) / 100;

                $this->creditReferCommission(
                    (int) $refer_user->id,
                    (int) $game_challenge->id,
                    (float) $refer_commission_amount,
                    'Refer wallet fund - Result update by admin'
                );
            }
        }

        if ($game_challenge->game_commission) {
            CommissionHistory::create([
                'refer_by' => $refer_user->id ?? 0,
                'user_id' => $game_challenge->challenger->id ?? 0,
                'game_challenge_id' => $game_challenge->id,
                'total_amount' => $game_challenge->amount,
                'game_commission' => $game_challenge->game_commission,
                'game_commission_amount' => $game_challenge->game_commission_amount,
                'refer_commission' => $refer_commission,
                'refer_commission_amount' => $refer_commission_amount,
                'remark' => " Game Ref:$game_challenge->uid",
                'status' => 1,
            ]);
        }
    }

    public function awardOpponentWin(GameChallenge $game_challenge): void
    {
        $game_challenge->loadMissing(['challenger', 'opponent']);

        $opponent = User::query()->withoutGlobalScopes()->find($game_challenge->opponent_id);
        if (!$opponent) {
            return;
        }

        $game_challenge->opponent_status = 1;
        $game_challenge->challenger_status = 2;
        $game_challenge->status = 4;

        // King ghost winner: statuses only - the payout happens on the
        // player's own platform (Daddy King network rule).
        if (is_king_ghost_user($opponent)) {
            return;
        }

        $this->creditWinnerIfMissing($game_challenge, $opponent);

        $refer_user = null;
        $refer_commission_amount = 0;
        $refer_commission = game_commission_slot()->refer_commission;
        $opponent_refer_by = optional($game_challenge->opponent)->refer_by ?? null;

        if ($opponent_refer_by && $game_challenge->game_commission_amount) {
            $refer_user = User::find($opponent_refer_by);

            if ($refer_user && ((int) $refer_user->refer_income === 1)) {
                if ($refer_user->commission) {
                    $refer_commission = $refer_user->commission;
                }

                $refer_commission_amount = ($game_challenge->challenger_amount * $refer_commission) / 100;

                $this->creditReferCommission(
                    (int) $refer_user->id,
                    (int) $game_challenge->id,
                    (float) $refer_commission_amount,
                    'Refer wallet fund - Result update by admin'
                );
            }
        }

        if ($game_challenge->game_commission) {
            CommissionHistory::create([
                'refer_by' => $refer_user->id ?? 0,
                'user_id' => optional($game_challenge->opponent)->id ?? 0,
                'game_challenge_id' => $game_challenge->id,
                'total_amount' => $game_challenge->amount,
                'game_commission' => $game_challenge->game_commission,
                'game_commission_amount' => $game_challenge->game_commission_amount,
                'refer_commission' => $refer_commission,
                'refer_commission_amount' => $refer_commission_amount,
                'remark' => "Game Ref: $game_challenge->uid",
                'status' => 1,
            ]);
        }
    }

    /**
     * One-time winner credit. Safe from winner, loser, auto-settle, and King paths.
     */
    public function creditWinnerIfMissing(GameChallenge $challenge, User $winner): bool
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
                $this->refunds->reverseCancelRefundsBecauseWinnerPaid($challenge);

                return false;
            }

            $winAmount = (float) $challenge->paid_amount;

            $credited = $this->wallet->credit((int) $locked->id, 'win', $winAmount, [
                'game_challenge_id' => $challenge->id,
                'remark' => "Winner amount Ref: $challenge->uid",
                'status' => 1,
            ]);

            if (! $credited) {
                return false;
            }

            $this->refunds->reverseCancelRefundsBecauseWinnerPaid($challenge);

            safe_notify(
                $locked->fcm_device_token,
                'Winner',
                "Congratulation, you win. Ref: $challenge->uid",
                'winner',
                $locked->id,
                ['game_challenge_id' => $challenge->id]
            );

            return true;
        });
    }

    /**
     * Refer commission payout.
     *
     * `refer_to == 'game_amount'` crediting the win wallet is pre-existing
     * behaviour and is kept as-is; only the write is made atomic and the ledger
     * row now names the wallet that actually received the money.
     */
    public function creditReferCommission(int $referUserId, int $gameChallengeId, float $amount, string $remark): void
    {
        $amount = round($amount, 2);
        if ($referUserId <= 0 || $amount <= 0) {
            return;
        }

        $walletType = site_setting()->refer_to == 'game_amount' ? 'win' : 'game';

        $this->wallet->incrementColumn($referUserId, 'refer_wallet_amount', $amount);

        $this->wallet->credit($referUserId, $walletType, $amount, [
            'game_challenge_id' => $gameChallengeId,
            'remark' => $remark,
            'status' => 1,
        ]);
    }
}
