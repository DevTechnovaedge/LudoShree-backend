<?php

namespace App\Services\King;

use App\Models\GameChallenge\GameChallenge;
use App\Models\King\KingOutbox;
use App\Models\User;

/**
 * Only writes rows into king_outbox (fast DB insert). The king:listen daemon
 * is the single process that talks to the WebSocket, so HTTP requests are
 * never slowed down by network calls to the King server.
 */
class KingOutboxService
{
    public function enqueueCreateTable(GameChallenge $challenge, User $creator): KingOutbox
    {
        $challenge->king_sync_status = 'pending';
        $challenge->saveQuietly();

        return KingOutbox::create([
            'event' => 'KingCreateTableRequest',
            'payload' => json_encode([
                'amount' => (int) round((float) $challenge->amount),
                'tableId' => (string) $challenge->id,
                'userName' => (string) $creator->name,
                'userId' => (string) $creator->id,
            ]),
            'game_challenge_id' => $challenge->id,
            'acting_user_id' => $creator->id,
            'status' => KingOutbox::STATUS_PENDING,
        ]);
    }

    public function enqueueDeleteTable(GameChallenge $challenge): ?KingOutbox
    {
        // Idempotent: one delete per challenge is enough.
        if ($this->existsFor($challenge->id, 'KingTableDeleteRequest')) {
            return null;
        }

        // tableId is resolved by the daemon at send time (create may still be in flight).
        return KingOutbox::create([
            'event' => 'KingTableDeleteRequest',
            'payload' => json_encode([]),
            'king_table_id' => $challenge->king_table_id,
            'game_challenge_id' => $challenge->id,
            'status' => KingOutbox::STATUS_PENDING,
        ]);
    }

    public function enqueueAccept(GameChallenge $challenge, User $joiner): KingOutbox
    {
        return KingOutbox::create([
            'event' => 'KingAcceptRequest',
            'payload' => json_encode([
                'tableId' => (string) $challenge->king_table_id,
                'userName' => (string) $joiner->name,
                'userId' => (string) $joiner->id,
            ]),
            'king_table_id' => $challenge->king_table_id,
            'game_challenge_id' => $challenge->id,
            'acting_user_id' => $joiner->id,
            'status' => KingOutbox::STATUS_PENDING,
        ]);
    }

    public function enqueueUpdateCode(GameChallenge $challenge, string $code): ?KingOutbox
    {
        if ($this->existsFor($challenge->id, 'KingUpdateCodeRequest')) {
            return null;
        }

        return KingOutbox::create([
            'event' => 'KingUpdateCodeRequest',
            'payload' => json_encode([
                'code' => $code,
            ]),
            'king_table_id' => $challenge->king_table_id,
            'game_challenge_id' => $challenge->id,
            'status' => KingOutbox::STATUS_PENDING,
        ]);
    }

    /**
     * @param  string  $result  Win | Loss | Cancel
     */
    public function enqueueResult(GameChallenge $challenge, int $localUserId, string $result, ?string $imageUrl = null): ?KingOutbox
    {
        // Idempotent per (challenge, user, result). A different result is
        // allowed (e.g. admin resolves a dispute) and supersedes the old one.
        $last = KingOutbox::query()
            ->where('game_challenge_id', $challenge->id)
            ->where('event', 'ResultUpdateRequest')
            ->where('acting_user_id', $localUserId)
            ->whereIn('status', [KingOutbox::STATUS_PENDING, KingOutbox::STATUS_SENT, KingOutbox::STATUS_SUCCESS])
            ->latest('id')
            ->first();

        if ($last && (($last->payloadArray()['result'] ?? null) === $result)) {
            return null;
        }

        $payload = [
            'userId' => (string) $localUserId,
            'result' => $result,
        ];

        if ($imageUrl) {
            $payload['image'] = $imageUrl;
        }

        return KingOutbox::create([
            'event' => 'ResultUpdateRequest',
            'payload' => json_encode($payload),
            'king_table_id' => $challenge->king_table_id,
            'game_challenge_id' => $challenge->id,
            'acting_user_id' => $localUserId,
            'status' => KingOutbox::STATUS_PENDING,
        ]);
    }

    public function hasPendingAccept(int $gameChallengeId): bool
    {
        return KingOutbox::query()
            ->where('game_challenge_id', $gameChallengeId)
            ->where('event', 'KingAcceptRequest')
            ->whereIn('status', [KingOutbox::STATUS_PENDING, KingOutbox::STATUS_SENT])
            ->exists();
    }

    private function existsFor(int $gameChallengeId, string $event): bool
    {
        return KingOutbox::query()
            ->where('game_challenge_id', $gameChallengeId)
            ->where('event', $event)
            ->whereIn('status', [KingOutbox::STATUS_PENDING, KingOutbox::STATUS_SENT, KingOutbox::STATUS_SUCCESS])
            ->exists();
    }
}
