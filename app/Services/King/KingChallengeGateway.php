<?php

namespace App\Services\King;

use App\Http\Resources\GameChallengeResource;
use App\Http\Resources\UserResource;
use App\Models\GameChallenge\GameChallenge;
use App\Models\King\KingEventLog;
use App\Models\King\KingOutbox;
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

        while (microtime(true) < $deadline) {
            usleep(200000); // 200ms

            $row = KingOutbox::find($row->id);
            if (! $row) {
                break;
            }

            if ($row->status === KingOutbox::STATUS_SUCCESS) {
                $fresh = GameChallenge::with(['challenger', 'opponent'])->find($challenge->id);

                return [
                    'status' => true,
                    'message' => 'Game Challenge accepted successfully.',
                    'rules' => site_setting()->rules,
                    'data' => new GameChallengeResource($fresh),
                    'user' => new UserResource(User::find($user->id)),
                ];
            }

            if (in_array($row->status, [KingOutbox::STATUS_FAILED, KingOutbox::STATUS_SKIPPED], true)) {
                unlock_game_challenge($challenge);

                $message = $row->error ?: 'This table is no longer available.';

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
                    if ($challenge->isKingLinked()) {
                        if (! $challenge->opponent_id) {
                            $this->outbox->enqueueDeleteTable($challenge);
                        } else {
                            $this->pushSideResults($challenge);
                        }
                    }
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
     */
    public function afterAdminAction(?GameChallenge $challenge): void
    {
        if (! $challenge || ! config('king.enabled')) {
            return;
        }

        try {
            $challenge->refresh();

            if (! $challenge->isKingLinked()) {
                return;
            }

            if (! $challenge->opponent_id && in_array((int) $challenge->status, [2, 3, 7], true)) {
                $this->outbox->enqueueDeleteTable($challenge);

                return;
            }

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
                default => in_array((int) $challenge->status, [6, 7], true) ? 'Cancel' : null,
            };

            if ($result === null) {
                continue;
            }

            $this->outbox->enqueueResult($challenge, $side['user_id'], $result, $side['image']);
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
}
