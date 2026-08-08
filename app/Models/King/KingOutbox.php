<?php

namespace App\Models\King;

use App\Models\GameChallenge\GameChallenge;
use Illuminate\Database\Eloquent\Model;

class KingOutbox extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'king_outbox';

    protected $fillable = [
        'event',
        'payload',
        'king_table_id',
        'game_challenge_id',
        'acting_user_id',
        'status',
        'attempts',
        'response',
        'error',
        'available_at',
        'sent_at',
    ];

    protected $casts = [
        'available_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function game_challenge()
    {
        return $this->belongsTo(GameChallenge::class, 'game_challenge_id', 'id');
    }

    public function payloadArray(): array
    {
        $decoded = json_decode((string) $this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function responseArray(): array
    {
        $decoded = json_decode((string) $this->response, true);

        return is_array($decoded) ? $decoded : [];
    }
}
