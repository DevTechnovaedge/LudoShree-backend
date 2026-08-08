<?php

namespace App\Events;

use App\Models\GameChallenge\GameChallenge;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes challenge snapshot to game-table / challenge clients.
 * Clients patch local state — no per-event REST refetch required.
 */
class GameChallengeChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameChallenge $challenge,
        public string $action,
    ) {}

    public static function fromModel(GameChallenge $challenge, string $action): self
    {
        $challenge->loadMissing(['challenger', 'opponent', 'game_type']);

        return new self($challenge, $action);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('game-table'),
            new Channel('challenge.'.$this->challenge->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'challenge.changed';
    }

    public function broadcastWith(): array
    {
        $c = $this->challenge;
        $status = $c->status !== null ? (int) $c->status : null;
        $roomcode = (string) ($c->roomcode ?? '');
        $onTable = $this->action !== 'deleted'
            && (
                $status === 0
                || ($status === 1 && $roomcode !== '')
            );

        return [
            'challenge_id' => (int) $c->id,
            'action' => $this->action,
            'status' => $status,
            'challenger_id' => $c->challenger_id !== null ? (int) $c->challenger_id : null,
            'opponent_id' => $c->opponent_id !== null ? (int) $c->opponent_id : null,
            'on_table' => $onTable,
            'challenge' => [
                'id' => $c->id,
                'uid' => $c->uid,
                'roomcode' => $c->roomcode,
                'amount' => $c->amount,
                'challenge_amount' => $c->challenger_amount,
                'paid_amount' => $c->paid_amount,
                'challenger_id' => $c->challenger_id,
                'opponent_id' => $c->opponent_id,
                'challenger_status' => $c->challenger_status,
                'opponent_status' => $c->opponent_status,
                'status' => $c->status,
                'game_source' => $c->game_source ?? 'local',
                'king_table_id' => $c->king_table_id,
                'is_king_linked' => $c->isKingLinked(),
                'game_type' => [
                    'id' => $c->game_type->id ?? null,
                    'name' => $c->game_type->name ?? null,
                ],
                'challenger' => $c->challenger ? [
                    'id' => $c->challenger->id,
                    'uid' => $c->challenger->uid,
                    'name' => $c->challenger->name,
                    'profile_url' => $c->challenger->profile_url ?? null,
                ] : null,
                'opponent' => $c->opponent ? [
                    'id' => $c->opponent->id,
                    'uid' => $c->opponent->uid,
                    'name' => $c->opponent->name,
                    'profile_url' => $c->opponent->profile_url ?? null,
                ] : null,
            ],
        ];
    }
}
