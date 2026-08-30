<?php

namespace App\Services;

use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for moving money in and out of a user's wallet.
 *
 * The balance is changed with one atomic SQL statement (column = column +/- ?)
 * and the ledger row is written in the same transaction from the post-update
 * database values.
 *
 * Read-modify-write on a User model must never be used for balances: two
 * requests holding their own copy of the row would each write their own total,
 * silently dropping the other's amount. That is how wallet history could show a
 * refund credit that the balance never received.
 */
class WalletService
{
    /**
     * @param  array  $ledger  Extra wallet-row columns (remark, game_challenge_id, transaction_id, status).
     * @param  bool  $quiet  Skip model events (used by game paths that must not broadcast).
     * @return array{game: float, win: float, total: float}|null Post-credit balances, or null when nothing moved.
     */
    public function credit(int $userId, string $walletType, float $amount, array $ledger = [], bool $quiet = false): ?array
    {
        return $this->apply($userId, $walletType, $amount, 'credit', $ledger, $quiet);
    }

    /**
     * @param  bool  $requireFunds  Refuse the debit instead of going negative.
     * @return array{game: float, win: float, total: float}|null Null when the balance was too low.
     */
    public function debit(int $userId, string $walletType, float $amount, array $ledger = [], bool $quiet = false, bool $requireFunds = true): ?array
    {
        return $this->apply($userId, $walletType, $amount, 'debit', $ledger, $quiet, $requireFunds);
    }

    /**
     * Balances read straight from the database.
     *
     * Global scopes are dropped on purpose: an unverified mobile must still be
     * able to receive a refund for money that was already taken from it.
     *
     * @return array{game: float, win: float, total: float}|null
     */
    public function balances(int $userId): ?array
    {
        $row = User::query()
            ->withoutGlobalScopes()
            ->whereKey($userId)
            ->first(['game_wallet_amount', 'win_wallet_amount']);

        if (! $row) {
            return null;
        }

        return $this->shape((float) $row->game_wallet_amount, (float) $row->win_wallet_amount);
    }

    /**
     * Stake for create/accept: take game wallet first, then win wallet.
     * Uses atomic column updates so a refund that landed in this same
     * request cannot be overwritten by a stale User::save().
     */
    public function debitEntryStake(int $userId, float $amount, array $ledger = [], bool $quiet = true): bool
    {
        $amount = round(abs($amount), 2);
        if ($userId <= 0 || $amount <= 0) {
            return false;
        }

        $runner = function () use ($userId, $amount, $ledger, $quiet) {
            $balances = $this->balances($userId);
            if (! $balances || ($balances['total'] + 0.001) < $amount) {
                return false;
            }

            $fromGame = round(min($balances['game'], $amount), 2);
            $fromWin = round($amount - $fromGame, 2);

            if ($fromGame > 0 && ! $this->debit($userId, 'game', $fromGame, $ledger, $quiet, true)) {
                return false;
            }

            if ($fromWin > 0 && ! $this->debit($userId, 'win', $fromWin, $ledger, $quiet, true)) {
                return false;
            }

            return true;
        };

        return (bool) (DB::transactionLevel() > 0 ? $runner() : DB::transaction($runner));
    }

    /**
     * Refer commission payout: tracking column plus the spendable wallet.
     *
     * `refer_to == 'game_amount'` crediting the win wallet is pre-existing
     * behaviour and is kept as-is; the ledger row now names the wallet that
     * actually received the money.
     */
    public function creditReferCommission(int $userId, int $gameChallengeId, float $amount, string $remark): void
    {
        $amount = round($amount, 2);
        if ($userId <= 0 || $amount <= 0) {
            return;
        }

        $walletType = site_setting()->refer_to == 'game_amount' ? 'win' : 'game';

        $this->incrementTracking($userId, 'refer_wallet_amount', $amount);

        $this->credit($userId, $walletType, $amount, [
            'game_challenge_id' => $gameChallengeId,
            'remark' => $remark,
            'status' => 1,
        ]);
    }

    /**
     * Atomic column bump without a ledger row.
     *
     * For tracking columns (refer_wallet_amount) and for flows where the ledger
     * row already exists and is only being flipped from pending to applied, such
     * as a gateway deposit callback.
     */
    public function incrementColumn(int $userId, string $column, float $amount): bool
    {
        $amount = round(abs($amount), 2);
        if ($userId <= 0 || $amount <= 0) {
            return false;
        }

        $affected = User::query()->withoutGlobalScopes()->whereKey($userId)->increment($column, $amount);

        if (! $affected) {
            Log::warning('[Wallet] column bump matched no user row', [
                'user_id' => $userId,
                'column' => $column,
                'amount' => $amount,
            ]);
        }

        return (bool) $affected;
    }

    private function apply(int $userId, string $walletType, float $amount, string $type, array $ledger, bool $quiet, bool $requireFunds = false): ?array
    {
        $amount = round(abs($amount), 2);
        if ($userId <= 0 || $amount <= 0) {
            return null;
        }

        $walletType = $walletType === 'win' ? 'win' : 'game';
        $column = $walletType === 'win' ? 'win_wallet_amount' : 'game_wallet_amount';

        $runner = function () use ($userId, $walletType, $column, $amount, $type, $ledger, $quiet, $requireFunds) {
            $query = User::query()->withoutGlobalScopes()->whereKey($userId);

            if ($type === 'debit' && $requireFunds) {
                // Check and deduct in one statement, so a balance that dropped
                // after the caller's own check cannot go negative.
                $query->where($column, '>=', $amount);
            }

            $affected = $type === 'debit'
                ? $query->decrement($column, $amount)
                : $query->increment($column, $amount);

            if (! $affected) {
                Log::warning('[Wallet] balance update matched no user row', [
                    'user_id' => $userId,
                    'type' => $type,
                    'wallet_type' => $walletType,
                    'amount' => $amount,
                    'require_funds' => $requireFunds,
                ]);

                return null;
            }

            $balances = $this->balances($userId);
            if (! $balances) {
                return null;
            }

            // Snapshots are derived here, never taken from the caller, so the
            // ledger can always be trusted to mirror the balance.
            $row = array_merge([
                'user_id' => $userId,
                'type' => $type,
                'wallet_type' => $walletType,
                'amount' => $amount,
                'status' => 1,
            ], $ledger, [
                'total_balance' => $walletType === 'win' ? $balances['win'] : $balances['game'],
                'win_and_game_total_amount' => $balances['total'],
            ]);

            $quiet
                ? Wallet::withoutEvents(fn () => Wallet::create($row))
                : Wallet::create($row);

            return $balances;
        };

        return DB::transactionLevel() > 0 ? $runner() : DB::transaction($runner);
    }

    /**
     * @return array{game: float, win: float, total: float}
     */
    private function shape(float $game, float $win): array
    {
        return [
            'game' => round($game, 2),
            'win' => round($win, 2),
            'total' => round($game + $win, 2),
        ];
    }
}
