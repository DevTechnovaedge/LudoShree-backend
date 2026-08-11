<?php

namespace App\Support;

use App\Events\DemoEvent;
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

    /** DemoEvent refresh ping — never block API responses on websocket I/O. */
    public static function demoEventPing(): void
    {
        $fire = static fn () => self::event(new DemoEvent(''));

        if (app()->runningInConsole()) {
            $fire();
        } else {
            dispatch($fire)->afterResponse();
        }
    }
}
