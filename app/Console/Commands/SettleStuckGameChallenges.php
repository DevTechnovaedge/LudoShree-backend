<?php

namespace App\Console\Commands;

use App\Models\GameChallenge\GameChallenge;
use App\Services\GameChallengeAutoSettleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SettleStuckGameChallenges extends Command
{
    protected $signature = 'game:settle-stuck-challenges';

    protected $description = 'Close games that already have both-cancel or matching win/lose results';

    public function handle(GameChallengeAutoSettleService $autoSettle): int
    {
        $rows = GameChallenge::query()
            ->where(function ($q): void {
                $q->where(function ($stuckCancel): void {
                    $stuckCancel->where('challenger_status', 3)
                        ->where('opponent_status', 3)
                        ->whereNotIn('status', [4, 6, 7]);
                })->orWhere(function ($stuckResult): void {
                    $stuckResult->whereIn('status', [1, 2, 5, 8])
                        ->where(function ($sides): void {
                            $sides->where(function ($winLose): void {
                                $winLose->where('challenger_status', 1)->where('opponent_status', 2);
                            })->orWhere(function ($loseWin): void {
                                $loseWin->where('challenger_status', 2)->where('opponent_status', 1);
                            });
                        });
                })->orWhere(function ($staleLock): void {
                    $staleLock->where('is_lock', 1)
                        ->where('updated_at', '<', now()->subMinutes(2));
                });
            })
            ->orderBy('id')
            ->limit(80)
            ->get();

        $settled = 0;
        foreach ($rows as $row) {
            try {
                $autoSettle->clearStaleLock($row, 90);
                $autoSettle->settleIfDecided($row);
                $settled++;
            } catch (\Throwable $e) {
                Log::error('[challenge.auto-settle] scheduled row failed', [
                    'game_challenge_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($settled > 0) {
            Log::info('[challenge.auto-settle] scheduled pass', [
                'attempted' => $settled,
                'queued' => $rows->count(),
            ]);
        }

        return self::SUCCESS;
    }
}
