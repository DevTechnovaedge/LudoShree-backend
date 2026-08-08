<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function event(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast skipped: '.$e->getMessage(), [
                'event' => $event::class,
            ]);
        }
    }
}
