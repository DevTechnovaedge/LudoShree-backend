<?php

namespace App\Services;

use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent stake refunds for cancelled / auto-closed challenges (never double-credit).
 */
class GameChallengeStakeRefundService
{
    /**
     * Refund stake debits for this user on this challenge that are not yet returned.
     *
     * @return float Amount refunded (sum across wallets)
     */
    public function refundUserStake(int $gameChallengeId, int $userId, string $creditRemark): float
    {
        $runner = fn () => $this->refundUserStakeWithinTransaction($gameChallengeId, $userId, $creditRemark);

        return (float) (DB::transactionLevel() > 0 ? $runner() : DB::transaction($runner));
    }

    private function refundUserStakeWithinTransaction(int $gameChallengeId, int $userId, string $creditRemark): float
    {
        $user = User::query()->lockForUpdate()->find($userId);
        if (! $user) {
            return 0.0;
        }

        // King (Daddy King) ghost players never pay stakes locally, so they
        // must never receive refunds (the fallback below would otherwise
        // credit challenger_amount to a ghost that has no debit rows).
        if ((int) ($user->is_king_player ?? 0) === 1) {
            return 0.0;
        }

        $gameChallenge = GameChallenge::query()->lockForUpdate()->find($gameChallengeId);
        if (! $gameChallenge) {
            return 0.0;
        }

        $debitedByWallet = $this->debitedByWallet($gameChallengeId, $userId);
        $returnedByWallet = $this->stakeReturnCreditsByWallet($gameChallengeId, $userId);

        $totalRefunded = $this->applyRefundCredits($user, $gameChallengeId, $userId, $creditRemark, $debitedByWallet, $returnedByWallet);

        if ($totalRefunded <= 0 && $debitedByWallet->isEmpty()) {
            $totalRefunded = $this->refundFallbackFromChallengeAmount($user, $gameChallenge, $userId, $creditRemark, $returnedByWallet);
        }

        $user->save();

        if ($totalRefunded > 0) {
            $logPayload = [
                'game_challenge_id' => $gameChallengeId,
                'user_id' => $userId,
                'uid' => $gameChallenge->uid,
                'amount' => $totalRefunded,
                'remark' => $creditRemark,
                'game_wallet' => (float) $user->game_wallet_amount,
                'win_wallet' => (float) $user->win_wallet_amount,
            ];

            DB::afterCommit(function () use ($logPayload) {
                Log::info('[GameChallengeRefund] stake returned', $logPayload);
            });
        }

        return $totalRefunded;
    }

    public function hasRefundableStake(int $gameChallengeId, int $userId): bool
    {
        $debited = (float) $this->debitedByWallet($gameChallengeId, $userId)->sum();
        $returned = (float) $this->stakeReturnCreditsByWallet($gameChallengeId, $userId)->sum();

        if ($debited > $returned + 0.01) {
            return true;
        }

        $gameChallenge = GameChallenge::find($gameChallengeId);

        return $gameChallenge
            && (int) $gameChallenge->challenger_id === $userId
            && (float) $gameChallenge->challenger_amount > 0
            && $returned < 0.01
            && $this->debitedByWallet($gameChallengeId, $userId)->isEmpty();
    }

    private function debitedByWallet(int $gameChallengeId, int $userId): Collection
    {
        return Wallet::query()
            ->where('game_challenge_id', $gameChallengeId)
            ->where('user_id', $userId)
            ->where('type', 'debit')
            ->get()
            ->groupBy('wallet_type')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2));
    }

    /**
     * Only count refund / auto-close credits — not winner payouts on the same challenge.
     */
    private function stakeReturnCreditsByWallet(int $gameChallengeId, int $userId): Collection
    {
        return Wallet::query()
            ->where('game_challenge_id', $gameChallengeId)
            ->where('user_id', $userId)
            ->where('type', 'credit')
            ->where(function ($q) {
                $q->where('remark', 'like', 'Challenge Refund Ref:%')
                    ->orWhere('remark', 'like', 'Challenge auto-closed Ref:%');
            })
            ->get()
            ->groupBy('wallet_type')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2));
    }

    private function applyRefundCredits(
        User $user,
        int $gameChallengeId,
        int $userId,
        string $creditRemark,
        Collection $debitedByWallet,
        Collection $returnedByWallet
    ): float {
        $totalRefunded = 0.0;

        foreach ($debitedByWallet as $walletType => $debited) {
            $already = (float) ($returnedByWallet[$walletType] ?? 0);
            $toRefund = round($debited - $already, 2);

            if ($toRefund <= 0) {
                continue;
            }

            $totalRefunded += $this->creditWallet($user, $userId, $gameChallengeId, (string) $walletType, $toRefund, $creditRemark);
        }

        return $totalRefunded;
    }

    /**
     * If create ran but wallet debits were never written, return challenger_amount once.
     */
    private function refundFallbackFromChallengeAmount(
        User $user,
        GameChallenge $gameChallenge,
        int $userId,
        string $creditRemark,
        Collection $returnedByWallet
    ): float {
        if ((int) $gameChallenge->challenger_id !== $userId) {
            return 0.0;
        }

        $amount = round((float) $gameChallenge->challenger_amount, 2);
        if ($amount <= 0) {
            return 0.0;
        }

        $already = round((float) $returnedByWallet->sum(), 2);
        if ($already >= $amount - 0.01) {
            return 0.0;
        }

        $toRefund = round($amount - $already, 2);
        if ($toRefund <= 0) {
            return 0.0;
        }

        Log::warning('[GameChallengeRefund] fallback refund from challenger_amount (no debit rows)', [
            'game_challenge_id' => $gameChallenge->id,
            'user_id' => $userId,
            'uid' => $gameChallenge->uid,
            'amount' => $toRefund,
        ]);

        return $this->creditWallet($user, $userId, (int) $gameChallenge->id, 'game', $toRefund, $creditRemark);
    }

    private function creditWallet(
        User $user,
        int $userId,
        int $gameChallengeId,
        string $walletType,
        float $toRefund,
        string $creditRemark
    ): float {
        if ($walletType === 'win') {
            $user->win_wallet_amount = round((float) $user->win_wallet_amount + $toRefund, 2);
            $balance = $user->win_wallet_amount;
        } else {
            $user->game_wallet_amount = round((float) $user->game_wallet_amount + $toRefund, 2);
            $balance = $user->game_wallet_amount;
        }

        Wallet::create([
            'user_id' => $userId,
            'game_challenge_id' => $gameChallengeId,
            'type' => 'credit',
            'wallet_type' => $walletType,
            'remark' => $creditRemark,
            'amount' => $toRefund,
            'total_balance' => $balance,
            'status' => 1,
        ]);

        return $toRefund;
    }
}
