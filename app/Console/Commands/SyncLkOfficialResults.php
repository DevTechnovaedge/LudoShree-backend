<?php

namespace App\Console\Commands;

use App\Models\GameChallenge\GameChallenge;
use App\Services\GameChallengeLkApiSubmitResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLkOfficialResults extends Command
{
    protected $signature = 'game:sync-lk-results {uid?}';

    protected $description = 'Pull finished Ludo King results and auto-credit winners for open challenges';

    public function handle(GameChallengeLkApiSubmitResolver $resolver): int
    {
        $uid = trim((string) $this->argument('uid'));

        if ($uid !== '') {
            $row = GameChallenge::query()->where('uid', $uid)->first();
            if (! $row) {
                $this->error("Challenge {$uid} not found");

                return self::FAILURE;
            }

            $ok = $resolver->settleFromOfficialApi($row);
            $this->info($ok ? "Settled {$uid}" : "Could not auto-settle {$uid} yet");

            return self::SUCCESS;
        }

        $rows = GameChallenge::query()
            ->whereIn('status', [1, 2, 5, 8])
            ->whereNotNull('roomcode')
            ->where('roomcode', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('king_table_id')->orWhere('king_table_id', '');
            })
            ->where(function ($q): void {
                $q->whereNull('game_source')->orWhere('game_source', '!=', 'daddy_king');
            })
            ->where('created_at', '>=', now()->subHours(36))
            ->orderBy('id')
            ->limit(40)
            ->get();

        $closed = 0;
        foreach ($rows as $row) {
            try {
                if ($resolver->settleFromOfficialApi($row)) {
                    $closed++;
                }
            } catch (\Throwable $e) {
                Log::error('[LK auto-settle] scheduled row failed', [
                    'uid' => $row->uid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($closed > 0) {
            Log::info('[LK auto-settle] scheduled pass', [
                'closed' => $closed,
                'scanned' => $rows->count(),
            ]);
        }

        return self::SUCCESS;
    }
}
