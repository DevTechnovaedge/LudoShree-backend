<?php

namespace App\Models\King;

use App\Models\GameChallenge\GameChallenge;
use Illuminate\Database\Eloquent\Model;

class KingTable extends Model
{
    protected $table = 'king_tables';

    protected $fillable = [
        'king_table_id',
        'origin',
        'game_challenge_id',
        'amount',
        'status',
        'created_by_id',
        'created_by_name',
        'joined_by_id',
        'joined_by_name',
        'room_code',
        'creator_result',
        'joiner_result',
        'raw',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function game_challenge()
    {
        return $this->belongsTo(GameChallenge::class, 'game_challenge_id', 'id');
    }
}
