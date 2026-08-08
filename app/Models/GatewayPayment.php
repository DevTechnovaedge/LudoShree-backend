<?php

namespace App\Models;

use App\Models\GameChallenge\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayPayment extends Model
{
    protected $fillable = [
        'transaction_id',
        'provider',
        'client_txn_id',
        'gateway_order_id',
        'amount',
        'currency',
        'status',
        'gateway_raw_status',
        'utr',
        'last_payload_excerpt',
        'webhook_received_at',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'webhook_received_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
