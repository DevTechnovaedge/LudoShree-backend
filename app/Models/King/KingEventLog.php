<?php

namespace App\Models\King;

use Illuminate\Database\Eloquent\Model;

class KingEventLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'king_event_logs';

    protected $fillable = [
        'direction',
        'uri',
        'level',
        'message',
        'payload',
        'created_at',
    ];

    public static function write(string $direction, ?string $uri, string $level, string $message, $payload = null): void
    {
        try {
            static::create([
                'direction' => $direction,
                'uri' => $uri,
                'level' => $level,
                'message' => mb_substr($message, 0, 490),
                'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the sync flow.
        }
    }
}
