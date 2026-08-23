<?php

namespace App\Console\Commands;

use App\Models\GameChallenge\Wallet;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Repairs balances that drifted away from the wallet ledger.
 *
 * Every ledger row stores the balance snapshot taken right after it was
 * written. If a user's newest row says the total was 250 but the users table
 * says 200, then 50 was credited in history and later overwritten - which is
 * the "refund shows in history but not in the wallet" report.
 *
 * Only shortfalls are credited back; a surplus is reported and left alone so
 * the command can never take money away from a player.
 */
class ReconcileWalletBalances extends Command
{
    protected $signature = 'wallet:reconcile
        {--apply : Write the correcting entries (default is a dry run)}
        {--user= : Limit to a single user id}
        {--min=0.01 : Ignore differences smaller than this}
        {--chunk=500 : Users loaded per batch}';

    protected $description = 'Compare wallet balances against the ledger snapshots and credit back any shortfall';

    public function handle(WalletService $wallet): int
    {
        $apply = (bool) $this->option('apply');
        $min = max(0.01, (float) $this->option('min'));

        $query = User::query()->withoutGlobalScopes()->select([
            'id', 'uid', 'mobile', 'game_wallet_amount', 'win_wallet_amount', 'is_king_player',
        ]);

        if ($userId = $this->option('user')) {
            $query->whereKey((int) $userId);
        }

        $checked = 0;
        $shortfalls = [];
        $surpluses = [];

        $query->orderBy('id')->chunk((int) $this->option('chunk'), function ($users) use (&$checked, &$shortfalls, &$surpluses, $min) {
            foreach ($users as $user) {
                if ((int) ($user->is_king_player ?? 0) === 1) {
                    continue;
                }

                $checked++;

                $expected = $this->expectedBalances((int) $user->id);
                if (! $expected) {
                    continue;
                }

                $current = [
                    'game' => round((float) $user->game_wallet_amount, 2),
                    'win' => round((float) $user->win_wallet_amount, 2),
                ];

                foreach (['game', 'win'] as $walletType) {
                    $diff = round($expected[$walletType] - $current[$walletType], 2);

                    if (abs($diff) < $min) {
                        continue;
                    }

                    $entry = [
                        'user_id' => (int) $user->id,
                        'uid' => $user->uid,
                        'mobile' => $user->mobile,
                        'wallet' => $walletType,
                        'current' => $current[$walletType],
                        'expected' => $expected[$walletType],
                        'diff' => $diff,
                    ];

                    $diff > 0 ? $shortfalls[] = $entry : $surpluses[] = $entry;
                }
            }
        });

        $this->renderTable('Shortfall (money owed to the player)', $shortfalls);
        $this->renderTable('Surplus (reported only, never deducted)', $surpluses);

        $owed = round(array_sum(array_column($shortfalls, 'diff')), 2);
        $this->line("Users checked: {$checked}");
        $this->line('Shortfall rows: '.count($shortfalls)." (total ₹{$owed})");
        $this->line('Surplus rows: '.count($surpluses));

        if (! $shortfalls) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn('Dry run. Re-run with --apply to credit the shortfalls.');

            return self::SUCCESS;
        }

        $repaired = 0;
        foreach ($shortfalls as $entry) {
            $credited = $wallet->credit($entry['user_id'], $entry['wallet'], $entry['diff'], [
                'remark' => 'Wallet correction - ledger sync',
                'status' => 1,
            ]);

            if (! $credited) {
                $this->error("Failed for user {$entry['user_id']} ({$entry['wallet']})");

                continue;
            }

            $repaired++;
            Log::info('[Wallet] reconciled shortfall', $entry + ['balances' => $credited]);
        }

        $this->info("Repaired {$repaired} of ".count($shortfalls).' entries.');

        return self::SUCCESS;
    }

    /**
     * Balances implied by the newest ledger row.
     *
     * That row carries the wallet it touched (total_balance) and the combined
     * total, so the other wallet is the remainder. Rows written before snapshots
     * existed are skipped rather than guessed at.
     *
     * @return array{game: float, win: float}|null
     */
    private function expectedBalances(int $userId): ?array
    {
        $latest = Wallet::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first(['wallet_type', 'total_balance', 'win_and_game_total_amount']);

        if (! $latest) {
            return null;
        }

        $total = round((float) $latest->win_and_game_total_amount, 2);
        $touched = round((float) $latest->total_balance, 2);

        if ($total <= 0 || $touched < 0 || $touched > $total) {
            return null;
        }

        $other = round($total - $touched, 2);

        return $latest->wallet_type === 'win'
            ? ['win' => $touched, 'game' => $other]
            : ['game' => $touched, 'win' => $other];
    }

    private function renderTable(string $title, array $rows): void
    {
        if (! $rows) {
            return;
        }

        $this->newLine();
        $this->line($title);
        $this->table(
            ['User', 'UID', 'Mobile', 'Wallet', 'Current', 'Expected', 'Diff'],
            array_map(fn ($r) => [
                $r['user_id'], $r['uid'], $r['mobile'], $r['wallet'],
                $r['current'], $r['expected'], $r['diff'],
            ], $rows)
        );
    }
}
