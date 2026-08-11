<?php

/*
|--------------------------------------------------------------------------
| King WebSocket (Daddy King) cross-platform table sync
|--------------------------------------------------------------------------
|
| A single long-running daemon (php artisan king:listen) owns the WebSocket
| connection. HTTP requests NEVER touch the socket directly - they only
| write rows into the king_outbox table, which the daemon pumps.
|
*/

return [

    // Master switch. When false nothing is pushed/pulled.
    'enabled' => (bool) env('KING_WS_ENABLED', false),

    'ws_url' => env('KING_WS_URL', 'wss://kingws.daddyking.live/ws'),

    'lobby' => env('KING_WS_LOBBY', 'LUDO_KING_LOBBY'),

    // API credentials provided by Daddy King.
    'api_key' => env('KING_WS_API_KEY', ''),
    'api_secret' => env('KING_WS_API_SECRET', ''),

    // Our client id on the King network (learned from the _login response and
    // cached; this env value is only a fallback before first login).
    'client_id' => env('KING_CLIENT_ID', ''),

    // Only this game type is synced (1 = Ludo Classic).
    'game_type_id' => (int) env('KING_GAME_TYPE_ID', 1),

    // Heartbeat interval in seconds (doc recommends 8-10s).
    'ping_interval' => (int) env('KING_PING_INTERVAL', 8),

    // Optional full table list poll (seconds). 0 = disabled — we only fetch on
    // room join / reconnect and apply real-time King server pushes otherwise.
    'table_poll_interval' => (int) env('KING_TABLE_POLL_INTERVAL', 0),

    // Safety poll while a cross-platform game is running / awaiting result.
    // KEEP 0: Daddy King currently drops the socket (1000 Bye) on
    // GetKingTableListReq. They push snapshots on join + realtime updates.
    'active_poll_interval' => (int) env('KING_ACTIVE_POLL_INTERVAL', 0),

    // How often the daemon checks king_outbox for pending messages (seconds).
    // Keep >= 1 to avoid bursting the King socket after join.
    'outbox_interval' => (float) env('KING_OUTBOX_INTERVAL', 1.0),

    // How long the HTTP accept endpoint waits for the daemon to confirm the
    // join with the King server before telling the user to retry (seconds).
    'accept_timeout' => (int) env('KING_ACCEPT_TIMEOUT', 8),

    // If no pong / message activity for this many seconds the connection is
    // considered dead and the daemon reconnects. Also used by the HTTP side
    // to detect that the daemon is offline (falls back to local-only accept).
    'alive_ttl' => (int) env('KING_ALIVE_TTL', 30),

    // Max delivery attempts for retryable outbox messages.
    'max_attempts' => (int) env('KING_MAX_ATTEMPTS', 5),

    // Suffix appended to proxy player names so admins/users can tell the
    // game came from the Daddy King network.
    'player_name_suffix' => env('KING_PLAYER_NAME_SUFFIX', ' [DK]'),

    // Days to keep king_event_logs rows (secondary cleanup).
    'log_retention_days' => (int) env('KING_LOG_RETENTION_DAYS', 7),

    // Hard cap: keep only the newest N event log rows.
    'log_max_rows' => (int) env('KING_LOG_MAX_ROWS', 100),
];
