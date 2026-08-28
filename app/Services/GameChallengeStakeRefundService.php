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
    public function __construct(
        private readonly WalletService $wallet,
    ) {}

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
        // No global scopes: an unverified mobile must still get back money that
        // was already debited from it.
        $user = User::query()->withoutGlobalScopes()->lockForUpdate()->find($userId);
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

        $totalRefunded = $this->applyRefundCredits($gameChallengeId, $userId, $creditRemark, $debitedByWallet, $returnedByWallet);

        if ($totalRefunded <= 0 && $debitedByWallet->isEmpty()) {
            $totalRefunded = $this->refundFallbackFromChallengeAmount($gameChallenge, $userId, $creditRemark, $returnedByWallet);
        }

        if ($totalRefunded > 0) {
            $balances = $this->wallet->balances($userId) ?? ['game' => 0.0, 'win' => 0.0];

            $logPayload = [
                'game_challenge_id' => $gameChallengeId,
                'user_id' => $userId,
                'uid' => $gameChallenge->uid,
                'amount' => $totalRefunded,
                'remark' => $creditRemark,
                'game_wallet' => $balances['game'],
                'win_wallet' => $balances['win'],
            ];

            DB::afterCommit(function () use ($logPayload) {
                Log::info('[GameChallengeRefund] stake returned', $logPayload);
            });
        }

        return $totalRefunded;
    }

    /**
     * Refund every real player who still has an open stake on this challenge.
     * Uses debit rows (not just challenger/opponent ids) so an acceptor who
     * paid before opponent_id was saved is still credited.
     */
    public function refundAllStakes(GameChallenge $gameChallenge): float
    {
        $userIds = Wallet::query()
            ->where('game_challenge_id', $gameChallenge->id)
            ->where('type', 'debit')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $userIds[] = (int) $gameChallenge->challenger_id;
        if ((int) $gameChallenge->opponent_id > 0) {
            $userIds[] = (int) $gameChallenge->opponent_id;
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $remark = "Challenge Refund Ref: {$gameChallenge->uid}";
        $total = 0.0;
        $perUser = [];

        foreach ($userIds as $userId) {
            $refunded = $this->refundUserStake((int) $gameChallenge->id, $userId, $remark);
            $perUser[$userId] = $refunded;
            $total += $refunded;
        }

        Log::info('[GameChallengeRefund] refundAllStakes', [
            'game_challenge_id' => $gameChallenge->id,
            'uid' => $gameChallenge->uid,
            'user_ids' => $userIds,
            'per_user' => $perUser,
            'total' => $total,
        ]);

        return $total;
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
            ->where(function ($q) {
                $q->where('remark', 'like', 'Challenge created%')
                    ->orWhere('remark', 'like', 'Challenge accepted%');
            })
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

            $totalRefunded += $this->creditWallet($userId, $gameChallengeId, (string) $walletType, $toRefund, $creditRemark);
        }

        return $totalRefunded;
    }

    /**
     * If create ran but wallet debits were never written, return challenger_amount once.
     */
    private function refundFallbackFromChallengeAmount(
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

        return $this->creditWallet($userId, (int) $gameChallenge->id, 'game', $toRefund, $creditRemark);
    }

    /**
     * If a started match later gets a winner, any cancel-time stake refund
     * on this challenge is extra money and must be taken back.
     */
    public function reverseCancelRefundsBecauseWinnerPaid(GameChallenge $gameChallenge): float
    {
        $credits = Wallet::query()
            ->where('game_challenge_id', $gameChallenge->id)
            ->where('type', 'credit')
            ->where('status', 1)
            ->where('remark', 'like', 'Challenge Refund Ref:%')
            ->get();

        $reversed = 0.0;

        foreach ($credits as $credit) {
            $user = User::query()->withoutGlobalScopes()->find($credit->user_id);
            if (! $user || (int) ($user->is_king_player ?? 0) === 1) {
                continue;
            }

            $amount = round((float) $credit->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            $already = Wallet::query()
                ->where('game_challenge_id', $gameChallenge->id)
                ->where('user_id', $credit->user_id)
                ->where('type', 'debit')
                ->where('remark', 'like', 'Refund reversed%')
                ->where('wallet_type', $credit->wallet_type)
                ->whereRaw('ABS(amount - ?) < 0.011', [$amount])
                ->exists();

            if ($already) {
                continue;
            }

            $walletType = $credit->wallet_type === 'win' ? 'win' : 'game';
            $ok = $this->wallet->debit((int) $credit->user_id, $walletType, $amount, [
                'game_challenge_id' => (int) $gameChallenge->id,
                'remark' => "Refund reversed - winner already paid Ref: {$gameChallenge->uid}",
                'status' => 1,
            ], quiet: true, requireFunds: false);

            if ($ok) {
                $reversed += $amount;
                Log::info('[GameChallengeRefund] reversed cancel refund after winner payout', [
                    'game_challenge_id' => $gameChallenge->id,
                    'uid' => $gameChallenge->uid,
                    'user_id' => $credit->user_id,
                    'amount' => $amount,
                    'wallet_type' => $walletType,
                ]);
            }
        }

        return $reversed;
    }

    private function creditWallet(
        int $userId,
        int $gameChallengeId,
        string $walletType,
        float $toRefund,
        string $creditRemark
    ): float {
        $balances = $this->wallet->credit($userId, $walletType, $toRefund, [
            'game_challenge_id' => $gameChallengeId,
            'remark' => $creditRemark,
            'status' => 1,
        ], quiet: true);

        return $balances ? $toRefund : 0.0;
    }
}
