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
use Illuminate\Support\Facades\Log;

/**
 * When LK Game API reports a finished game (creator vs player winner_id), settle from that payload.
 * Used from player win/lose, admin View Result, and the scheduled sync command.
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

        $settled = $this->settleFromOfficialApi($game_challenge);

        if (! $settled) {
            $fresh = GameChallenge::with(['challenger', 'opponent'])->find($game_challenge->id);
            if ($fresh && (int) $fresh->status === 4) {
                event(new DemoEvent(''));
                $freshUser = User::query()->withoutGlobalScopes()->find($user->id);

                return response()->json([
                    'status' => true,
                    'message' => 'Result synced from official game server.',
                    'rules' => site_setting()->rules,
                    'data' => new GameChallengeResource($fresh),
                    'user' => new UserResource($freshUser ?? $user),
                ]);
            }

            return null;
        }

        event(new DemoEvent(''));

        $freshChallenge = GameChallenge::with(['challenger', 'opponent'])->find($game_challenge->id);
        $freshUser = User::query()->withoutGlobalScopes()->find($user->id);

        return response()->json([
            'status' => true,
            'message' => 'Result synced from official game server.',
            'rules' => site_setting()->rules,
            'data' => new GameChallengeResource($freshChallenge ?? $game_challenge),
            'user' => new UserResource($freshUser ?? $user),
        ]);
    }

    /**
     * Fetch official LK result and close the challenge when a winner is known.
     * Safe to call from cron / admin (idempotent).
     */
    public function settleFromOfficialApi(GameChallenge $game_challenge): bool
    {
        if ($game_challenge->isCrossPlatformKingGame()) {
            return false;
        }

        if (! $game_challenge->roomcode || ! $game_challenge->opponent_id) {
            return false;
        }

        if ($this->isCancelFlowForAdmin($game_challenge)) {
            return false;
        }

        if (in_array((int) $game_challenge->status, [4, 6, 7], true)) {
            return false;
        }

        if ($this->fundsAlreadyRefunded($game_challenge)) {
            return false;
        }

        if ($this->lk->apiKey() === '' || $this->lk->baseUrl() === '') {
            Log::info('[LK auto-settle] skipped: API not configured', [
                'uid' => $game_challenge->uid,
            ]);

            return false;
        }

        $fetched = $this->fetchOfficialSnapshot($game_challenge);
        if ($fetched === null
            || $fetched['state'] !== 'finished'
            || $fetched['side'] === null
            || ! $this->lk->isPayableOfficialResult($fetched['raw'])) {
            return false;
        }

        $raw = $fetched['raw'];
        $winnerSide = $fetched['side'];
        $gameId = $fetched['game_id'];

        $normalizedForStorage = json_encode($this->lk->normalizeForChallenge($raw));
        $applied = false;

        DB::transaction(function () use ($game_challenge, $normalizedForStorage, $winnerSide, $gameId, &$applied): void {
            /** @var GameChallenge|null $gc */
            $gc = GameChallenge::query()->lockForUpdate()->find($game_challenge->id);
            if (! $gc) {
                return;
            }

            if (in_array((int) $gc->status, [4, 6, 7], true)) {
                return;
            }

            if ($this->isCancelFlowForAdmin($gc) || $this->fundsAlreadyRefunded($gc)) {
                return;
            }

            $gc->ludo_king_result_details = $normalizedForStorage;
            if (preg_match('/^[a-f\d]{24}$/i', $gameId)) {
                $gc->ludo_king_game_id = strtolower($gameId);
            }

            $alreadyPaid = $this->hasMainWinnerCredit($gc);

            if (! $alreadyPaid) {
                if ($winnerSide === 'challenger') {
                    $this->payout->awardChallengerWin($gc);
                } else {
                    $this->payout->awardOpponentWin($gc);
                }
            }

            $this->applyResultSidesFromWinner($gc, $winnerSide);

            $winnerId = $winnerSide === 'challenger' ? $gc->challenger_id : $gc->opponent_id;
            $winner = User::query()->withoutGlobalScopes()->find($winnerId);
            if ($winner) {
                $this->payout->creditWinnerIfMissing($gc, $winner);
            }

            $gc->is_lock = 0;
            $gc->closed_at = now();
            $gc->save();
            $applied = (int) $gc->status === 4;
        });

        if ($applied) {
            Log::info('[LK auto-settle] closed from official result', [
                'uid' => $game_challenge->uid,
                'winner_side' => $winnerSide,
                'game_id' => $gameId,
            ]);
        }

        return $applied;
    }

    /**
     * Win/Lose tap: pay only when LK says Finished + winner. If the official
     * game is still Running (or LK cannot be read), store the claim for admin
     * and do not move wallet money.
     */
    public function applyPlayerResultClaim(
        GameChallenge $game_challenge,
        User $user,
        string $claim,
        ?string $screenshot
    ): ?JsonResponse {
        if ($game_challenge->isCrossPlatformKingGame()) {
            return null;
        }

        if (! $game_challenge->roomcode || ! $game_challenge->opponent_id) {
            return null;
        }

        if ($user->id != $game_challenge->challenger_id && $user->id != $game_challenge->opponent_id) {
            return null;
        }

        if (in_array((int) $game_challenge->status, [4, 6, 7], true)) {
            return response()->json([
                'status' => false,
                'message' => (int) $game_challenge->status === 4 ? 'Already completed' : 'Game already closed',
            ]);
        }

        $claim = $claim === 'loser' ? 'loser' : 'winner';
        $snapshot = $this->fetchOfficialSnapshot($game_challenge);

        if ($snapshot
            && $snapshot['state'] === 'finished'
            && $snapshot['side'] !== null
            && $this->lk->isPayableOfficialResult($snapshot['raw'])
            && $this->settleFromOfficialApi($game_challenge)) {
            $this->attachClaimScreenshot($game_challenge->id, $user->id, $screenshot);

            event(new DemoEvent(''));

            $freshChallenge = GameChallenge::with(['challenger', 'opponent'])->find($game_challenge->id);
            $freshUser = User::query()->withoutGlobalScopes()->find($user->id);

            Log::info('[LK player-submit] auto-settled from Finished result', [
                'uid' => $game_challenge->uid,
                'user_id' => $user->id,
                'claim' => $claim,
                'winner_side' => $snapshot['side'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Result synced from official game server.',
                'rules' => site_setting()->rules,
                'data' => new GameChallengeResource($freshChallenge ?? $game_challenge),
                'user' => new UserResource($freshUser ?? $user),
            ]);
        }

        $this->sendClaimToAdminReview($game_challenge, $user, $claim, $screenshot, $snapshot);

        event(new DemoEvent(''));

        $freshChallenge = GameChallenge::with(['challenger', 'opponent'])->find($game_challenge->id);
        $freshUser = User::query()->withoutGlobalScopes()->find($user->id);
        $lkState = $snapshot['state'] ?? 'unknown';
        $leftEarly = $snapshot && $this->lk->isLeaveWithoutCompletedGame($snapshot['raw']);

        if ($lkState === 'abandoned' || $leftEarly) {
            $message = 'Player left the Ludo King game. Result sent to admin for review.';
        } elseif ($lkState === 'running') {
            $message = 'Ludo King game is still running. Result sent to admin for review.';
        } else {
            $message = 'Official result is not confirmed yet. Result sent to admin for review.';
        }

        Log::info('[LK player-submit] held for admin review', [
            'uid' => $game_challenge->uid,
            'user_id' => $user->id,
            'claim' => $claim,
            'lk_state' => $lkState,
            'lk_side' => $snapshot['side'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => $message,
            'rules' => site_setting()->rules,
            'data' => new GameChallengeResource($freshChallenge ?? $game_challenge),
            'user' => new UserResource($freshUser ?? $user),
        ]);
    }

    /**
     * Try stored mongo id AND room-code lookup. A stale hex id is the usual
     * reason some games skip auto-settle while others work.
     *
     * @return array{raw: object, side: ?string, state: string, game_id: string}|null
     */
    private function fetchOfficialSnapshot(GameChallenge $game_challenge): ?array
    {
        $ids = $this->candidateOfficialGameIds($game_challenge);
        if ($ids === []) {
            Log::info('[LK auto-settle] skipped: could not resolve game id', [
                'uid' => $game_challenge->uid,
                'stored' => $game_challenge->ludo_king_game_id,
                'roomcode' => $game_challenge->roomcode,
            ]);

            return null;
        }

        $lastRaw = null;
        $lastId = null;

        $attempts = app()->runningInConsole() ? 2 : 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            foreach ($ids as $gameId) {
                $raw = $this->lk->gameStatus($gameId);
                if ($raw === null) {
                    continue;
                }

                $lastRaw = $raw;
                $lastId = $gameId;

                if (! isset($raw->game_id) && (isset($raw->msg) || (isset($raw->status) && is_numeric($raw->status ?? null)))) {
                    continue;
                }

                $state = $this->lk->officialMatchState($raw);
                $side = $this->lk->winnerSide($raw);

                if ($state === 'finished'
                    && $side !== null
                    && $this->lk->isPayableOfficialResult($raw)) {
                    return ['raw' => $raw, 'side' => $side, 'state' => $state, 'game_id' => $gameId];
                }

                if ($lastRaw !== null) {
                    // Keep looking at other ids, but remember this payload.
                }
            }

            if ($attempt < $attempts) {
                usleep(2_000_000);
            }
        }

        if ($lastRaw === null || $lastId === null) {
            Log::info('[LK auto-settle] skipped: no official payload', [
                'uid' => $game_challenge->uid,
                'tried_ids' => $ids,
            ]);

            return null;
        }

        $state = $this->lk->officialMatchState($lastRaw);
        $side = $this->lk->winnerSide($lastRaw);

        Log::info('[LK auto-settle] official snapshot not payable yet', [
            'uid' => $game_challenge->uid,
            'tried_ids' => $ids,
            'last_game_id' => $lastId,
            'lk_state' => $state,
            'game_status' => $lastRaw->game_status ?? $lastRaw->status ?? null,
            'has_winner_side' => $side !== null,
            'left_without_finish' => $this->lk->isLeaveWithoutCompletedGame($lastRaw),
            'body_excerpt' => substr((string) json_encode($lastRaw), 0, 1200),
        ]);

        return ['raw' => $lastRaw, 'side' => $side, 'state' => $state, 'game_id' => $lastId];
    }

    /**
     * @param  array{raw: object, side: ?string, state: string, game_id: string}|null  $snapshot
     */
    private function sendClaimToAdminReview(
        GameChallenge $game_challenge,
        User $user,
        string $claim,
        ?string $screenshot,
        ?array $snapshot
    ): void {
        DB::transaction(function () use ($game_challenge, $user, $claim, $screenshot, $snapshot): void {
            $gc = GameChallenge::query()->lockForUpdate()->find($game_challenge->id);
            if (! $gc || in_array((int) $gc->status, [4, 6, 7], true)) {
                return;
            }

            if ($this->hasMainWinnerCredit($gc) || $this->fundsAlreadyRefunded($gc)) {
                return;
            }

            $isChallenger = (int) $user->id === (int) $gc->challenger_id;
            $sideStatus = $claim === 'loser' ? 2 : 1;

            if ($isChallenger) {
                $gc->challenger_status = $sideStatus;
                if ($screenshot) {
                    $gc->challenger_screenshot = $screenshot;
                }
                $gc->challenger_result_date = now();
            } else {
                $gc->opponent_status = $sideStatus;
                if ($screenshot) {
                    $gc->opponent_screenshot = $screenshot;
                }
                $gc->opponent_result_date = now();
                $gc->opponent_date = now();
            }

            if ($snapshot && isset($snapshot['raw'])) {
                $gc->ludo_king_result_details = json_encode($this->lk->normalizeForChallenge($snapshot['raw']));
                if (isset($snapshot['game_id']) && preg_match('/^[a-f\d]{24}$/i', (string) $snapshot['game_id'])) {
                    $gc->ludo_king_game_id = strtolower((string) $snapshot['game_id']);
                }
            }

            $gc->status = 5;
            $gc->is_lock = 0;
            $gc->save();
        });
    }

    private function attachClaimScreenshot(int $challengeId, int $userId, ?string $screenshot): void
    {
        if (! $screenshot) {
            return;
        }

        $gc = GameChallenge::query()->find($challengeId);
        if (! $gc) {
            return;
        }

        if ((int) $gc->challenger_id === $userId) {
            $gc->challenger_screenshot = $screenshot;
        } elseif ((int) $gc->opponent_id === $userId) {
            $gc->opponent_screenshot = $screenshot;
        } else {
            return;
        }

        $gc->save();
    }

    /**
     * @return list<string>
     */
    private function candidateOfficialGameIds(GameChallenge $game_challenge): array
    {
        $stored = trim((string) ($game_challenge->ludo_king_game_id ?? ''));
        $room = trim((string) ($game_challenge->roomcode ?? ''));
        $out = [];

        $push = static function (?string $id) use (&$out): void {
            $id = $id !== null ? strtolower(trim($id)) : '';
            if ($id !== '' && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        };

        if (preg_match('/^[a-f\d]{24}$/i', $stored)) {
            $push($stored);
        }

        if ($room !== '') {
            $push($this->lk->resolveGameId($room));
        }

        if ($stored !== '' && $stored !== $room && ! preg_match('/^[a-f\d]{24}$/i', $stored)) {
            $push($this->lk->resolveGameId($stored));
        }

        return $out;
    }

    /**
     * @param  'challenger'|'opponent'  $winnerSide
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
     * Fully cancelled / refunded games stay with admin. Dispute and one-sided
     * cancel can still be closed from the official LK result.
     */
    private function isCancelFlowForAdmin(GameChallenge $gc): bool
    {
        if (in_array((int) $gc->status, [3, 6, 7], true)) {
            return true;
        }

        return (int) ($gc->challenger_status ?? 0) === 3
            && (int) ($gc->opponent_status ?? 0) === 3;
    }

    private function hasMainWinnerCredit(GameChallenge $gc): bool
    {
        $uid = $gc->uid;

        return Wallet::query()
            ->where('game_challenge_id', $gc->id)
            ->where('type', 'credit')
            ->where(function ($q) use ($uid) {
                $q->where('remark', 'Winner Ref: '.$uid)
                    ->orWhere('remark', 'Winner amount Ref: '.$uid)
                    ->orWhere('remark', 'like', 'Winner amount Ref:%'.$uid.'%')
                    ->orWhere('remark', 'like', 'Winner Ref:%'.$uid.'%');
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
