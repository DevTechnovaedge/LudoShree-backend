<?php

namespace App\Models\King;

use App\Models\GameChallenge\GameChallenge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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

    /** Fast status signal for HTTP accept polling (avoids repeated DB reads). */
    public static function signalStatus(int $outboxId, string $status, ?string $error = null): void
    {
        try {
            Cache::put("king:outbox:{$outboxId}", [
                'status' => $status,
                'error' => $error,
            ], now()->addMinutes(5));
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    public static function readStatusSignal(int $outboxId): ?array
    {
        try {
            $value = Cache::get("king:outbox:{$outboxId}");

            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
