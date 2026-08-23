<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only audit of wallet balances against the ledger.
 *
 * This command deliberately cannot write. An earlier version credited every
 * apparent shortfall automatically and that was wrong: two ledger columns are
 * not trustworthy enough to pay out from.
 *
 *  - `wallet_type` does not always name the column the money landed in. Refer
 *    commission maps `refer_to == 'game_amount'` to the *win* wallet, so a row
 *    labelled "win" can correspond to a game-wallet credit.
 *  - `win_and_game_total_amount` was inflated by WalletObserver for a period:
 *    the observer predicted the post-change total by adding the amount, but
 *    WalletService had already applied it, so the amount was counted twice.
 *
 * `total_balance` is the one dependable field, because WalletService reads it
 * back from the database after the atomic update. So a user is only reported
 * when that snapshot matches neither wallet nor the combined total - anything
 * else is a labelling artefact, not missing money.
 *
 * Rows that were never applied to a balance (pending or rejected deposits) are
 * skipped, since a balance of zero against them is the correct outcome.
 */
class ReconcileWalletBalances extends Command
{
    protected $signature = 'wallet:reconcile
        {--user= : Limit to a single user id}
        {--min=0.01 : Ignore differences smaller than this}';

    protected $description = 'Audit wallet balances against the ledger snapshots (read-only, never writes)';

    public function handle(): int
    {
        $min = max(0.01, (float) $this->option('min'));

        $bindings = [];
        $userFilter = '';
        if ($userId = $this->option('user')) {
            $userFilter = ' AND u.id = ?';
            $bindings[] = (int) $userId;
        }

        // One row per user: their balances plus their newest ledger entry.
        $rows = DB::select("
            SELECT u.id, u.uid, u.mobile,
                   u.game_wallet_amount AS game,
                   u.win_wallet_amount  AS win,
                   w.type, w.status, w.wallet_type, w.total_balance AS snap,
                   w.remark, w.created_at
            FROM users u
            JOIN (SELECT user_id, MAX(id) AS mid FROM wallet GROUP BY user_id) m
              ON m.user_id = u.id
            JOIN wallet w ON w.id = m.mid
            WHERE COALESCE(u.is_king_player, 0) = 0
              AND w.total_balance IS NOT NULL
              {$userFilter}
            ORDER BY u.id
        ", $bindings);

        $suspect = [];
        $negative = [];

        foreach ($rows as $r) {
            $game = round((float) $r->game, 2);
            $win = round((float) $r->win, 2);

            if ($game < 0 || $win < 0) {
                $negative[] = [$r->id, $r->uid, $r->mobile, $game, $win];
            }

            // A credit that never settled (pending or rejected deposit) was never
            // added to the balance, so its snapshot says nothing about a shortfall.
            if ($r->type === 'credit' && (int) $r->status !== 1) {
                continue;
            }

            $snap = round((float) $r->snap, 2);

            foreach ([$game, $win, round($game + $win, 2)] as $candidate) {
                if (abs($snap - $candidate) < $min) {
                    continue 2;
                }
            }

            $suspect[] = [
                $r->id, $r->uid, $r->mobile, $game, $win, $snap,
                substr((string) $r->remark, 0, 34), $r->created_at,
            ];
        }

        if ($negative) {
            $this->newLine();
            $this->line('Negative balances (overdrafts predating atomic writes)');
            $this->table(['User', 'UID', 'Mobile', 'Game', 'Win'], $negative);
        }

        if ($suspect) {
            $this->newLine();
            $this->line('Snapshot matches neither wallet nor the combined total');
            $this->table(
                ['User', 'UID', 'Mobile', 'Game', 'Win', 'Snapshot', 'Remark', 'When'],
                $suspect
            );
        }

        $this->newLine();
        $this->line('Users audited: '.count($rows));
        $this->line('Negative balances: '.count($negative));
        $this->line('Unexplained snapshots: '.count($suspect));

        if (! $suspect && ! $negative) {
            $this->info('Ledger and balances agree.');
        } else {
            $this->warn('Review each row by hand. This command never credits automatically.');
        }

        return self::SUCCESS;
    }
}
