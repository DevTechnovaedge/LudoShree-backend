<?php

namespace App\Services\King;

use App\Http\Resources\GameChallengeResource;
use App\Http\Resources\UserResource;
use App\Models\GameChallenge\GameChallenge;
use App\Models\King\KingEventLog;
use App\Models\King\KingOutbox;
use App\Models\King\KingTable;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Controller-facing entry points for the King (Daddy King) integration.
 *
 * Everything here is designed to add near-zero latency to HTTP requests:
 * hooks only insert king_outbox rows. The single exception is accepting a
 * King-synced table, which must be confirmed by the King server first so two
 * players on different platforms can never take the same table.
 */
class KingChallengeGateway
{
    public function __construct(
        private readonly KingOutboxService $outbox,
        private readonly KingSettlementService $settlement,
    ) {}

    /* =====================================================================
     * Accept handshake
     * ===================================================================== */

    /**
     * Accept a King-linked challenge. Returns a response array for the API,
     * or null when the caller should fall back to the normal local accept
     * (used when the daemon is offline and both players are local).
     */
    public function acceptViaKing(GameChallenge $challenge, User $user): ?array
    {
        $challengerIsGhost = is_king_ghost_user($challenge->challenger_id);

        if (! king_enabled() || ! king_ws_alive()) {
            if ($challengerIsGhost) {
                // Remote-owned table: without the King server we must not touch it.
                unlock_game_challenge($challenge);

                return ['status' => false, 'message' => 'This table is temporarily unavailable. Please try another one.'];
            }

            // Local table (sync offline): play locally; the daemon reconciles
            // the network copy after reconnecting.
            KingEventLog::write('sys', 'KingAcceptRequest', 'warning',
                "King daemon offline - challenge #{$challenge->id} accepted locally without network confirmation.");

            return null;
        }

        # Validations (mirror the local accept rules)
        if ((int) $challenge->status !== 0 || $challenge->opponent_id) {
            unlock_game_challenge($challenge);

            return ['status' => false, 'message' => $challenge->opponent_id ? 'Game Challenge already accepted.' : 'Game Status updating please wait.....'];
        }

        if ((int) $challenge->challenger_id === (int) $user->id) {
            unlock_game_challenge($challenge);

            return ['status' => false, 'message' => 'You are not allowed to accept. Challenge created by you.'];
        }

        if (! $challengerIsGhost && GameChallenge::runningForUser($challenge->challenger_id)->exists()) {
            unlock_game_challenge($challenge);

            return ['status' => false, 'message' => 'Challenge creator is already in an active game.'];
        }

        if ((float) $challenge->amount > (float) $user->total_wallet_amount) {
            unlock_game_challenge($challenge);

            return ['status' => false, 'message' => 'Insufficient Balance'];
        }

        if ($this->outbox->hasPendingAccept($challenge->id)) {
            unlock_game_challenge($challenge);

            return ['status' => false, 'message' => 'Game Status updating please wait.....'];
        }

        $row = $this->outbox->enqueueAccept($challenge, $user);

        # Wait for the daemon to confirm with the King server (bounded).
        $deadline = microtime(true) + max(2, (int) config('king.accept_timeout', 8));
        $outboxId = (int) $row->id;
        $dbPollAt = 0.0;

        while (microtime(true) < $deadline) {
            usleep(50000); // 50ms

            $signal = KingOutbox::readStatusSignal($outboxId);
            if ($signal) {
                $row = (object) array_merge(['id' => $outboxId], $signal);
            } elseif (microtime(true) >= $dbPollAt) {
                $dbPollAt = microtime(true) + 0.25;
                $row = KingOutbox::find($outboxId);
            }

            if (! $row) {
                break;
            }

            $status = is_object($row) ? $row->status : ($row['status'] ?? null);

            if ($status === KingOutbox::STATUS_SUCCESS) {
                $fresh = GameChallenge::with(['challenger', 'opponent'])->find($challenge->id);

                return [
                    'status' => true,
                    'message' => 'Game Challenge accepted successfully.',
                    'rules' => site_setting()->rules,
                    'data' => new GameChallengeResource($fresh),
                    'user' => new UserResource(User::find($user->id)),
                ];
            }

            if (in_array($status, [KingOutbox::STATUS_FAILED, KingOutbox::STATUS_SKIPPED], true)) {
                unlock_game_challenge($challenge);

                $message = (is_object($row) ? ($row->error ?? null) : ($row['error'] ?? null)) ?: 'This table is no longer available.';

                return ['status' => false, 'message' => $message];
            }
        }

        # Timed out: the daemon may still complete it - the app receives the
        # final state through the realtime challenge.changed broadcast.
        unlock_game_challenge($challenge);

        return ['status' => false, 'message' => 'Join request is being processed. Please wait a moment and refresh.'];
    }

    /* =====================================================================
     * Post-action hooks (fire-and-forget outbox inserts)
     * ===================================================================== */

    /**
     * Called after any successful challenge API action. Never throws.
     */
    public function afterLocalChallengeAction(string $type, ?GameChallenge $challenge, ?User $user): void
    {
        if (! $challenge || ! config('king.enabled')) {
            return;
        }

        try {
            $challenge->refresh();

            switch ($type) {
                case 'create':
                    if (
                        king_enabled()
                        && (int) $challenge->status === 0
                        && ! $challenge->opponent_id
                        && $challenge->game_source === 'local'
                        && ! $challenge->king_table_id
                        && (int) $challenge->game_type_id === (int) config('king.game_type_id', 1)
                        && $user
                    ) {
                        $this->outbox->enqueueCreateTable($challenge, $user);
                    }
                    break;

                case 'roomcode':
                    if ($challenge->isKingLinked() && $challenge->roomcode) {
                        $this->outbox->enqueueUpdateCode($challenge, (string) $challenge->roomcode);
                    }
                    break;

                case 'cancel':
                    $this->handleKingCancel($challenge, $user);
                    break;

                case 'winner':
                    if ($challenge->isKingLinked()) {
                        // Remote side already admitted loss -> pay the local
                        // winner now (idempotent).
                        if (
                            (int) $challenge->status === 4
                            && $user
                            && $this->userSideStatus($challenge, $user->id) === 1
                            && $this->otherSideIsGhost($challenge, $user->id)
                        ) {
                            $this->settlement->creditWinnerPayoutIfMissing($challenge, $user);
                        }

                        $this->pushSideResults($challenge);
                    }
                    break;

                case 'loser':
                    if ($challenge->isKingLinked()) {
                        $this->pushSideResults($challenge);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('[King] afterLocalChallengeAction failed', [
                'type' => $type,
                'challenge_id' => $challenge->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Called after an admin result / cancel / suspend / delete action.
     * Same ResultUpdateRequest path as the app (no separate admin DK API).
     */
    public function afterAdminAction(?GameChallenge $challenge): void
    {
        if (! $challenge || ! config('king.enabled')) {
            return;
        }

        try {
            $challenge->refresh();

            if (! $this->shouldSyncChallengeToKing($challenge)) {
                return;
            }

            // Waiting table removed / cancelled with no joiner → delete on network.
            if (! $challenge->opponent_id && in_array((int) $challenge->status, [2, 3, 6, 7], true)) {
                $tableId = $this->kingTableIdForChallenge($challenge);
                if ($tableId && ! $challenge->king_table_id) {
                    $challenge->king_table_id = $tableId;
                    $challenge->saveQuietly();
                }
                $this->outbox->enqueueDeleteTable($challenge);

                return;
            }

            // Joined match: admin Win / Loss / Cancel / Suspend → ResultUpdateRequest.
            $this->pushSideResults($challenge);
        } catch (\Throwable $e) {
            Log::error('[King] afterAdminAction failed', ['challenge_id' => $challenge->id ?? null, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Waiting challenge deleted / auto-closed - remove its King copy too.
     */
    public function afterWaitingChallengeClosed(?GameChallenge $challenge): void
    {
        if (! $challenge || ! config('king.enabled') || ! $challenge->isKingLinked()) {
            return;
        }

        try {
            if (! $challenge->opponent_id && $challenge->game_source === 'local') {
                $this->outbox->enqueueDeleteTable($challenge);
            }
        } catch (\Throwable $e) {
            Log::error('[King] afterWaitingChallengeClosed failed', ['challenge_id' => $challenge->id, 'error' => $e->getMessage()]);
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    /**
     * Push each LOCAL player's known outcome to the King network.
     */
    private function pushSideResults(GameChallenge $challenge): void
    {
        $sides = [
            ['user_id' => (int) $challenge->challenger_id, 'status' => (int) $challenge->challenger_status, 'image' => $this->screenshotUrl($challenge, 'challenger')],
            ['user_id' => (int) $challenge->opponent_id, 'status' => (int) $challenge->opponent_status, 'image' => $this->screenshotUrl($challenge, 'opponent')],
        ];

        foreach ($sides as $side) {
            if (! $side['user_id'] || is_king_ghost_user($side['user_id'])) {
                continue;
            }

            $result = match ($side['status']) {
                1 => 'Win',
                2 => 'Loss',
                3 => 'Cancel',
                // Admin suspend / force-cancel may leave side status at 0.
                default => in_array((int) $challenge->status, [6, 7], true) ? 'Cancel' : null,
            };

            if ($result === null) {
                continue;
            }

            $row = $this->outbox->enqueueResult($challenge, $side['user_id'], $result, $side['image']);
            if ($row) {
                KingEventLog::write('sys', 'ResultUpdateRequest', 'info',
                    "Queued {$result} for challenge #{$challenge->id} user {$side['user_id']}",
                    $row->payloadArray());
            }
        }
    }

    private function screenshotUrl(GameChallenge $challenge, string $side): ?string
    {
        $file = $side === 'challenger' ? $challenge->challenger_screenshot : $challenge->opponent_screenshot;
        if (! $file) {
            return null;
        }

        $url = $side === 'challenger' ? $challenge->challenger_screenshot_url : $challenge->opponent_screenshot_url;

        return ($url && $url !== '#') ? $url : null;
    }

    private function userSideStatus(GameChallenge $challenge, int $userId): int
    {
        if ((int) $challenge->challenger_id === $userId) {
            return (int) $challenge->challenger_status;
        }

        if ((int) $challenge->opponent_id === $userId) {
            return (int) $challenge->opponent_status;
        }

        return 0;
    }

    private function otherSideIsGhost(GameChallenge $challenge, int $userId): bool
    {
        $otherId = (int) $challenge->challenger_id === $userId
            ? (int) $challenge->opponent_id
            : (int) $challenge->challenger_id;

        return $otherId > 0 && is_king_ghost_user($otherId);
    }

    /**
     * Queue Cancel on the King network when a match has started there, or
     * delete the table when it is still genuinely waiting.
     */
    private function handleKingCancel(GameChallenge $challenge, ?User $user): void
    {
        if (! $this->shouldSyncChallengeToKing($challenge)) {
            return;
        }

        $mirror = $this->resolveKingMirror($challenge);
        $startedOnNetwork = $this->kingGameStartedOnNetwork($challenge, $mirror);

        if (! $startedOnNetwork) {
            $tableId = $this->kingTableIdForChallenge($challenge, $mirror);
            if ($tableId && ! $challenge->king_table_id) {
                $challenge->king_table_id = $tableId;
                $challenge->saveQuietly();
            }

            $this->outbox->enqueueDeleteTable($challenge);
            KingEventLog::write('sys', 'KingTableDeleteRequest', 'info',
                "Cancel waiting challenge #{$challenge->id} (no King join yet) -> network delete");

            return;
        }

        if (! $user || is_king_ghost_user($user->id)) {
            return;
        }

        $tableId = $this->kingTableIdForChallenge($challenge, $mirror);
        if ($tableId && ! $challenge->king_table_id) {
            $challenge->king_table_id = $tableId;
            $challenge->saveQuietly();
        }

        $side = (int) $user->id === (int) $challenge->challenger_id ? 'challenger' : 'opponent';
        $row = $this->outbox->enqueueResult(
            $challenge,
            (int) $user->id,
            'Cancel',
            $this->screenshotUrl($challenge, $side),
            request()->input('proof_video') ?: request()->input('video')
        );

        if ($row) {
            KingEventLog::write('sys', 'ResultUpdateRequest', 'info',
                "Cancel queued for challenge #{$challenge->id} on {$tableId}", $row->payloadArray());
        }
    }

    private function shouldSyncChallengeToKing(GameChallenge $challenge): bool
    {
        if ($challenge->isKingLinked()) {
            return true;
        }

        if ($challenge->game_source !== 'local') {
            return false;
        }

        return KingOutbox::query()
            ->where('game_challenge_id', $challenge->id)
            ->where('event', 'KingCreateTableRequest')
            ->whereIn('status', [
                KingOutbox::STATUS_PENDING,
                KingOutbox::STATUS_SENT,
                KingOutbox::STATUS_SUCCESS,
            ])
            ->exists();
    }

    private function resolveKingMirror(GameChallenge $challenge): ?KingTable
    {
        $tableId = $this->kingTableIdForChallenge($challenge);

        return KingTable::query()
            ->where(function ($q) use ($challenge, $tableId) {
                $q->where('game_challenge_id', $challenge->id);
                if ($tableId) {
                    $q->orWhere('king_table_id', $tableId);
                }
            })
            ->first();
    }

    private function kingTableIdForChallenge(GameChallenge $challenge, ?KingTable $mirror = null): ?string
    {
        if ($challenge->king_table_id) {
            return (string) $challenge->king_table_id;
        }

        if ($mirror?->king_table_id) {
            return (string) $mirror->king_table_id;
        }

        if ($challenge->game_source !== 'local') {
            return null;
        }

        $clientId = (string) (app(KingSyncService::class)->ourClientId() ?: config('king.client_id', ''));

        return $clientId !== '' ? "DK-{$clientId}-{$challenge->id}" : null;
    }

    private function kingGameStartedOnNetwork(GameChallenge $challenge, ?KingTable $mirror): bool
    {
        if ($challenge->opponent_id) {
            return true;
        }

        if (! $mirror) {
            return false;
        }

        if (! empty($mirror->joined_by_id)) {
            return true;
        }

        return in_array(strtolower((string) $mirror->status), ['start', 'view', 'completed'], true);
    }
}
