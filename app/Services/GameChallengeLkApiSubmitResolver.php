<?php

namespace App\Services;

use App\Events\DemoEvent;
use App\Http\Resources\GameChallengeResource;
use App\Http\Resources\UserResource;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * When LK Game API reports a finished game (creator vs player winner_id), settle from that payload.
 * Hooked into player actions: winner and loser only when the match is not in cancel/dispute.
 * Cancel and any win/lose after a cancel are handled by admin (no LK game-status call).
 *
 * Returns null when the API is unavailable / game not terminal / refunds already processed → unchanged manual & admin flows.
 */
class GameChallengeLkApiSubmitResolver
{
    public function __construct(
        private readonly LkGameApiService $lk,
        private readonly GameChallengeWinnerPayoutService $payout,
    ) {}

    public function maybeResolveAndRespond(GameChallenge $game_challenge, User $user): ?JsonResponse
    {
        if ($user->id != $game_challenge->challenger_id && $user->id != $game_challenge->opponent_id) {
            return null;
        }

        if (! $game_challenge->roomcode || ! $game_challenge->opponent_id) {
            return null;
        }

        if ($this->isCancelFlowForAdmin($game_challenge)) {
            return null;
        }

        // Let normal / admin flow handle fully completed, suspended, or challenge-cancelled rows
        if (in_array((int) $game_challenge->status, [4, 6, 7], true)) {
            return null;
        }

        if ($this->fundsAlreadyRefunded($game_challenge)) {
            return null;
        }

        if ($this->lk->apiKey() === '' || $this->lk->baseUrl() === '') {
            return null;
        }

        $stored = trim((string) ($game_challenge->ludo_king_game_id ?? ''));
        $fallbackRoom = trim((string) ($game_challenge->roomcode ?? ''));
        if ($stored === '' && $fallbackRoom === '') {
            return null;
        }

        $gameId = $this->lk->resolveGameId($stored);
        if ($gameId === null && $fallbackRoom !== '' && $fallbackRoom !== $stored) {
            $gameId = $this->lk->resolveGameId($fallbackRoom);
        }
        if ($gameId === null) {
            return null;
        }

        $raw = $this->lk->gameStatus($gameId);
        if ($raw === null) {
            return null;
        }

        if (! isset($raw->game_id) && isset($raw->msg)) {
            return null;
        }

        // HTTP error payloads with numeric status (still let success bodies that include game_id + status through)
        if (! isset($raw->game_id) && isset($raw->status) && is_numeric($raw->status ?? null)) {
            return null;
        }

        if (! $this->lk->isResolvedFinished($raw)) {
            return null;
        }

        $winnerSide = $this->lk->winnerSide($raw);
        if ($winnerSide === null) {
            return null;
        }

        $normalizedForStorage = json_encode($this->lk->normalizeForChallenge($raw));

        $applied = false;

        DB::transaction(function () use ($game_challenge, $normalizedForStorage, $winnerSide, &$applied): void {
            /** @var GameChallenge|null $gc */
            $gc = GameChallenge::with(['challenger', 'opponent'])->lockForUpdate()->find($game_challenge->id);
            if (! $gc) {
                return;
            }

            if (in_array((int) $gc->status, [4, 6, 7], true)) {
                return;
            }

            if ($this->isCancelFlowForAdmin($gc)) {
                return;
            }

            if ($this->fundsAlreadyRefunded($gc)) {
                return;
            }

            $gc->ludo_king_result_details = $normalizedForStorage;

            $alreadyPaid = $this->hasMainWinnerCredit($gc);

            if (! $alreadyPaid) {
                if ($winnerSide === 'challenger') {
                    $this->payout->awardChallengerWin($gc);
                } else {
                    $this->payout->awardOpponentWin($gc);
                }
            } else {
                $this->applyResultSidesFromWinner($gc, $winnerSide);
            }

            $gc->is_lock = 0;
            $gc->closed_at = date('Y-m-d H:i:s');

            $gc->save();
            $applied = true;
        });

        if (! $applied) {
            $fresh = GameChallenge::find($game_challenge->id);
            if ($fresh && (int) $fresh->status === 4) {
                return response()->json([
                    'status' => false,
                    'message' => 'Already completed',
                ]);
            }

            return null;
        }

        event(new DemoEvent(''));

        $freshChallenge = GameChallenge::with(['challenger', 'opponent'])->find($game_challenge->id);
        $freshUser = User::find($user->id);

        return response()->json([
            'status' => true,
            'message' => 'Result synced from official game server.',
            'rules' => site_setting()->rules,
            'data' => new GameChallengeResource($freshChallenge ?? $game_challenge),
            'user' => new UserResource($freshUser ?? $user),
        ]);
    }

    /**
     * @param 'challenger'|'opponent' $winnerSide
     */
    private function applyResultSidesFromWinner(GameChallenge $gc, string $winnerSide): void
    {
        if ($winnerSide === 'challenger') {
            $gc->challenger_status = 1;
            $gc->opponent_status = 2;
        } else {
            $gc->opponent_status = 1;
            $gc->challenger_status = 2;
        }
        $gc->status = 4;
    }

    /**
     * Cancel / dispute paths: never auto-settle from LK (admin decides).
     */
    private function isCancelFlowForAdmin(GameChallenge $gc): bool
    {
        if ((int) ($gc->challenger_status ?? 0) === 3 || (int) ($gc->opponent_status ?? 0) === 3) {
            return true;
        }

        return in_array((int) $gc->status, [2, 3, 5, 7], true);
    }

    private function hasMainWinnerCredit(GameChallenge $gc): bool
    {
        $uid = $gc->uid;

        return Wallet::query()
            ->where('game_challenge_id', $gc->id)
            ->where('type', 'credit')
            ->where(function ($q) use ($uid) {
                $q->where('remark', 'Winner Ref: '.$uid)
                    ->orWhere('remark', 'Winner amount Ref: '.$uid);
            })
            ->exists();
    }

    /**
     * If stakes were refunded (cancel flow), never auto-pay a winner from the API.
     */
    private function fundsAlreadyRefunded(GameChallenge $gc): bool
    {
        $uid = $gc->uid;

        return Wallet::query()
            ->where('game_challenge_id', $gc->id)
            ->where('type', 'credit')
            ->where('remark', 'like', '%Challenge Refund Ref:%'.$uid.'%')
            ->exists();
    }
}
