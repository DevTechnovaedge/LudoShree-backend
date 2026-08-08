<?php

namespace App\Services;

use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\Wallet;
use App\Models\User;

/**
 * Applies the same winnings settlement as admin "challenger win" / "opponent win" actions.
 *
 * Intended for reuse by admin manual updates and automated LK API resolution.
 */
class GameChallengeWinnerPayoutService
{
    public function awardChallengerWin(GameChallenge $game_challenge): void
    {
        $game_challenge->loadMissing(['challenger', 'opponent']);

        $win_amount = $game_challenge->paid_amount;

        $challenger = User::find($game_challenge->challenger_id);
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

        Wallet::create([
            'user_id' => $game_challenge->challenger_id,
            'game_challenge_id' => $game_challenge->id,
            'type' => 'credit',
            'wallet_type' => 'game',
            'remark' => "Winner Ref: $game_challenge->uid",
            'amount' => $win_amount,
            'total_balance' => $challenger->game_wallet_amount + $win_amount,
            'status' => 1,
        ]);

        $challenger->win_wallet_amount = $challenger->win_wallet_amount + $win_amount;
        $challenger->save();

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

                $refer_user->refer_wallet_amount = $refer_user->refer_wallet_amount + $refer_commission_amount;

                $refer_to = site_setting()->refer_to;
                $refer_user_total_balance = 0;
                if ($refer_to == 'game_amount') {
                    $refer_user->win_wallet_amount = $refer_user->win_wallet_amount + $refer_commission_amount;
                    $refer_user_total_balance = $refer_user->win_wallet_amount;
                } else {
                    $refer_user->game_wallet_amount = $refer_user->game_wallet_amount + $refer_commission_amount;
                    $refer_user_total_balance = $refer_user->game_wallet_amount;
                }

                Wallet::create([
                    'user_id' => $refer_user->id,
                    'game_challenge_id' => $game_challenge->id,
                    'type' => 'credit',
                    'wallet_type' => 'win',
                    'remark' => 'Refer wallet fund - Result update by admin',
                    'amount' => $refer_commission_amount,
                    'total_balance' => $refer_user_total_balance,
                    'status' => 1,
                ]);

                $refer_user->save();
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

        safe_notify(
            optional($game_challenge->challenger)->fcm_device_token,
            'Winner',
            "Congratulation, you win. Ref: $game_challenge->uid",
            'winner',
            $game_challenge->challenger_id,
            ['game_challenge_id' => $game_challenge->id]
        );
    }

    public function awardOpponentWin(GameChallenge $game_challenge): void
    {
        $game_challenge->loadMissing(['challenger', 'opponent']);

        $win_amount = $game_challenge->paid_amount;

        $opponent = User::find($game_challenge->opponent_id);
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

        Wallet::create([
            'user_id' => $game_challenge->opponent_id,
            'game_challenge_id' => $game_challenge->id,
            'type' => 'credit',
            'wallet_type' => 'game',
            'remark' => "Winner Ref: $game_challenge->uid",
            'amount' => $win_amount,
            'total_balance' => $opponent->game_wallet_amount + $win_amount,
            'status' => 1,
        ]);

        $opponent->win_wallet_amount = $opponent->win_wallet_amount + $win_amount;
        $opponent->save();

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

                $refer_user->refer_wallet_amount = $refer_user->refer_wallet_amount + $refer_commission_amount;

                $refer_to = site_setting()->refer_to;
                $refer_user_total_balance = 0;
                if ($refer_to == 'game_amount') {
                    $refer_user->win_wallet_amount = $refer_user->win_wallet_amount + $refer_commission_amount;
                    $refer_user_total_balance = $refer_user->win_wallet_amount;
                } else {
                    $refer_user->game_wallet_amount = $refer_user->game_wallet_amount + $refer_commission_amount;
                    $refer_user_total_balance = $refer_user->game_wallet_amount;
                }

                Wallet::create([
                    'user_id' => $refer_user->id,
                    'game_challenge_id' => $game_challenge->id,
                    'type' => 'credit',
                    'wallet_type' => 'win',
                    'remark' => 'Refer wallet fund - Result update by admin',
                    'amount' => $refer_commission_amount,
                    'total_balance' => $refer_user_total_balance,
                    'status' => 1,
                ]);

                $refer_user->save();
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

        safe_notify(
            optional($game_challenge->opponent)->fcm_device_token,
            'Winner',
            "Congratulation, you win. Ref: $game_challenge->uid",
            'winner',
            $game_challenge->opponent_id,
            ['game_challenge_id' => $game_challenge->id]
        );
    }
}
