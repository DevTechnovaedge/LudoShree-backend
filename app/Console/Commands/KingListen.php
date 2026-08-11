<?php

namespace App\Console\Commands;

use App\Models\GameChallenge\GameChallenge;
use App\Models\King\KingEventLog;
use App\Models\King\KingOutbox;
use App\Services\King\KingSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\Loop;

/**
 * Single long-running daemon that owns the King (Daddy King) WebSocket
 * connection. Run under supervisor:
 *
 *   php artisan king:listen
 *
 * Responsibilities:
 *  - connect + login + join room, heartbeat every 8s
 *  - pump king_outbox (messages queued by the HTTP API / admin panel)
 *  - poll the table list and reconcile it into local state
 *  - reconnect automatically with backoff
 */
class KingListen extends Command
{
    protected $signature = 'king:listen';

    protected $description = 'Connect to the King (Daddy King) WebSocket server and sync game tables';

    private ?WebSocket $conn = null;

    /** @var array<int, \React\EventLoop\TimerInterface> */
    private array $timers = [];

    private bool $loggedIn = false;

    private bool $joinedRoom = false;

    private ?KingOutbox $inflight = null;

    private float $inflightSentAt = 0.0;

    private float $lastActivityAt = 0.0;

    private int $reconnectDelay = 2;

    private bool $reconnectScheduled = false;

    private bool $intentionalClose = false;

    private bool $connectInFlight = false;

    private bool $initialTableListRequested = false;

    private bool $sessionReady = false;

    /** @var \React\EventLoop\TimerInterface|null */
    private $reconnectTimer = null;

    private KingSyncService $sync;

    public function handle(KingSyncService $sync): int
    {
        $this->sync = $sync;

        if (! config('king.enabled')) {
            $this->rememberDaemonError('KING_WS_ENABLED=false in config. Set KING_WS_ENABLED=true in .env then php artisan config:cache');
            $this->error('King integration is disabled (KING_WS_ENABLED=false).');

            return self::FAILURE;
        }

        if (trim((string) config('king.api_key')) === '' || trim((string) config('king.api_secret')) === '') {
            $this->rememberDaemonError('KING_WS_API_KEY / KING_WS_API_SECRET missing. Set them in .env then run: php artisan config:cache');
            $this->error('KING_WS_API_KEY / KING_WS_API_SECRET are not configured.');

            return self::FAILURE;
        }

        $this->touchAlive();
        $this->clearDaemonError();

        $loop = Loop::get();

        if (extension_loaded('pcntl')) {
            $loop->addSignal(SIGTERM, function () use ($loop) {
                $this->info('SIGTERM received, shutting down.');
                $this->closeConnection();
                $loop->stop();
            });
            $loop->addSignal(SIGINT, function () use ($loop) {
                $this->closeConnection();
                $loop->stop();
            });
        }

        $this->info('Starting King WebSocket daemon -> ' . config('king.ws_url'));
        $this->logSys('info', 'Daemon started');

        $this->connect();

        $loop->run();

        return self::SUCCESS;
    }

    /* =====================================================================
     * Connection lifecycle
     * ===================================================================== */

    private function connect(): void
    {
        // Never open a second socket while one is alive/connecting — King kicks
        // the previous session with 1000 Bye and we flap forever.
        if ($this->conn || $this->connectInFlight) {
            $this->logSys('info', 'connect() skipped — already connected or connecting');

            return;
        }

        $this->connectInFlight = true;
        $connector = new Connector(Loop::get());

        $connector((string) config('king.ws_url'))->then(
            function (WebSocket $conn) {
                $this->connectInFlight = false;
                $this->onConnected($conn);
            },
            function (\Throwable $e) {
                $this->connectInFlight = false;
                $this->warn('Connect failed: ' . $e->getMessage());
                $this->logSys('warning', 'Connect failed: ' . $e->getMessage());
                $this->scheduleReconnect();
            }
        );
    }

    private function onConnected(WebSocket $conn): void
    {
        $this->cancelReconnectTimer();
        $this->reconnectScheduled = false;
        $this->intentionalClose = false;
        $this->initialTableListRequested = false;
        $this->sessionReady = false;

        // Replace any stale handle without scheduling another reconnect.
        if ($this->conn && $this->conn !== $conn) {
            $old = $this->conn;
            $this->conn = null;
            $this->intentionalClose = true;
            try {
                $old->close(1000, 'replaced');
            } catch (\Throwable $e) {
            }
            $this->intentionalClose = false;
        }

        $this->info('Connected. Logging in...');

        $this->conn = $conn;
        $this->loggedIn = false;
        $this->joinedRoom = false;
        $this->lastActivityAt = microtime(true);
        $this->reconnectDelay = 2;
        $this->requeueInflight('Connection restarted');

        $conn->on('message', function ($msg) {
            $this->lastActivityAt = microtime(true);
            $this->touchAlive();

            try {
                $this->handleMessage((string) $msg);
            } catch (\Throwable $e) {
                Log::error('[King] message handling failed', ['error' => $e->getMessage()]);
                $this->logSys('error', 'Message handling failed: ' . $e->getMessage());
            }
        });

        $conn->on('close', function ($code = null, $reason = null) use ($conn) {
            // Ignore close callbacks from a socket we already replaced.
            if ($this->conn !== null && $this->conn !== $conn) {
                return;
            }

            $who = $this->intentionalClose ? 'local' : 'remote';
            $this->warn("Connection closed by {$who} ($code - $reason)");
            $this->logSys('warning', "Connection closed by {$who} ($code - $reason)");

            $this->conn = null;
            $this->loggedIn = false;
            $this->joinedRoom = false;
            $this->sessionReady = false;
            $this->connectInFlight = false;
            $this->intentionalClose = false;
            $this->cancelTimers();

            $this->scheduleReconnect();
        });

        $this->startTimers();

        $this->send('_login', [
            'LOBBY_NAME' => (string) config('king.lobby'),
            'USERNAME' => (string) config('king.api_key'),
            'PASSWORD' => (string) config('king.api_secret'),
        ]);
    }

    private function startTimers(): void
    {
        $this->cancelTimers();

        $loop = Loop::get();

        # Heartbeat
        $this->timers[] = $loop->addPeriodicTimer(max(3, (int) config('king.ping_interval', 8)), function () {
            if (! $this->conn) {
                return;
            }
            $this->send('_ping_pong', []);
            $this->touchAlive();
        });

        # Outbox pump — keep spacing gentle; King drops us if we burst.
        $this->timers[] = $loop->addPeriodicTimer(max(1.0, (float) config('king.outbox_interval', 1.0)), function () {
            $this->pumpOutbox();
        });

        # Optional table list reconciliation (off by default — King pushes updates).
        $pollInterval = (int) config('king.table_poll_interval', 0);
        if ($pollInterval > 0) {
            $this->timers[] = $loop->addPeriodicTimer(max(5, $pollInterval), function () {
                if ($this->joinedRoom && ! $this->isPaused()) {
                    $this->send('GetKingTableListReq', []);
                }
            });
        }

        # Result-safety poll DISABLED by default: Daddy King currently closes
        # the socket with 1000 Bye whenever we send GetKingTableListReq.
        # They already push a list snapshot on JoinKingRoomRequest, and we
        # apply realtime table pushes — so outbound list polling is unsafe.
        $activePoll = (int) config('king.active_poll_interval', 0);
        if ($activePoll > 0 && $pollInterval <= 0) {
            $this->timers[] = $loop->addPeriodicTimer(max(30, $activePoll), function () {
                if (! $this->joinedRoom || ! $this->sessionReady || $this->isPaused()) {
                    return;
                }

                $hasActiveKingGame = $this->db(function () {
                    return GameChallenge::query()
                        ->whereNotNull('king_table_id')
                        ->whereIn('status', [1, 8])
                        ->exists();
                }, false);

                if ($hasActiveKingGame) {
                    $this->logSys('info', 'Active-game list poll skipped (GetKingTableListReq causes remote Bye)');
                }
            });
        }

        # Watchdog: in-flight timeouts + dead connection detection
        $this->timers[] = $loop->addPeriodicTimer(1, function () {
            $this->watchdog();
        });

        # Housekeeping (hourly)
        $this->timers[] = $loop->addPeriodicTimer(3600, function () {
            $this->housekeeping();
        });
    }

    private function cancelTimers(): void
    {
        foreach ($this->timers as $timer) {
            Loop::get()->cancelTimer($timer);
        }

        $this->timers = [];
    }

    private function cancelReconnectTimer(): void
    {
        if ($this->reconnectTimer) {
            try {
                Loop::get()->cancelTimer($this->reconnectTimer);
            } catch (\Throwable $e) {
            }
            $this->reconnectTimer = null;
        }
        $this->reconnectScheduled = false;
    }

    private function scheduleReconnect(): void
    {
        if ($this->reconnectScheduled || $this->conn || $this->connectInFlight) {
            return;
        }

        $this->cancelTimers();
        $this->loggedIn = false;
        $this->joinedRoom = false;
        $this->requeueInflight('Reconnecting');

        $delay = $this->reconnectDelay;
        $this->reconnectDelay = min(60, max(2, $this->reconnectDelay * 2));
        $this->reconnectScheduled = true;

        $this->info("Reconnecting in {$delay}s...");

        $this->reconnectTimer = Loop::get()->addTimer($delay, function () {
            $this->reconnectTimer = null;
            $this->reconnectScheduled = false;
            $this->connect();
        });
    }

    private function closeConnection(string $reason = 'shutdown'): void
    {
        $this->cancelReconnectTimer();
        $this->cancelTimers();

        if ($this->conn) {
            $this->intentionalClose = true;
            try {
                $this->conn->close(1000, $reason);
            } catch (\Throwable $e) {
            }
            $this->conn = null;
        }
    }

    private function watchdog(): void
    {
        # In-flight request timed out
        if ($this->inflight && (microtime(true) - $this->inflightSentAt) > 12) {
            $row = $this->inflight;
            $this->inflight = null;

            $this->db(function () use ($row) {
                $fresh = KingOutbox::find($row->id);
                if (! $fresh || $fresh->status !== KingOutbox::STATUS_SENT) {
                    return;
                }

                if ($fresh->event === 'KingAcceptRequest' || $fresh->attempts >= (int) config('king.max_attempts', 5)) {
                    $fresh->status = KingOutbox::STATUS_FAILED;
                    $fresh->error = 'No response from King server';
                } else {
                    $fresh->status = KingOutbox::STATUS_PENDING;
                    $fresh->available_at = now()->addSeconds(5 * max(1, $fresh->attempts));
                }

                $fresh->save();
            });

            $this->logSys('warning', "No response for {$row->event} #{$row->id} - reconnecting");
            $this->forceClose('inflight-timeout');

            return;
        }

        # Dead connection (no pong / no traffic)
        if ($this->conn && (microtime(true) - $this->lastActivityAt) > (int) config('king.alive_ttl', 30)) {
            $this->warn('No traffic - forcing reconnect');
            $this->forceClose('alive-ttl');
        }
    }

    private function forceClose(string $reason): void
    {
        if (! $this->conn) {
            $this->scheduleReconnect();

            return;
        }

        $this->intentionalClose = true;
        try {
            $this->conn->close(1000, $reason);
        } catch (\Throwable $e) {
            $this->conn = null;
            $this->intentionalClose = false;
            $this->scheduleReconnect();
        }
    }

    private function scheduleInitialTableListSync(): void
    {
        // Daddy King currently closes the socket (1000 Bye) when we request the
        // full table list right after join. Rely on realtime pushes + the
        // active-game poll instead of a join-time dump.
        if ($this->initialTableListRequested) {
            return;
        }

        $this->initialTableListRequested = true;
        $this->info('Skipping join-time GetKingTableListReq (avoids remote Bye kick)');
        $this->logSys('info', 'Skipped join-time GetKingTableListReq to keep session alive');
    }

    private function scheduleSessionReady(): void
    {
        // Expire the reconnect-storm backlog so we never flood King on join.
        $this->db(function () {
            $cutoff = now()->subHour();
            $n = KingOutbox::query()
                ->where('status', KingOutbox::STATUS_PENDING)
                ->where('created_at', '<', $cutoff)
                ->update([
                    'status' => KingOutbox::STATUS_SKIPPED,
                    'error' => 'Stale outbox skipped after reconnect storm',
                ]);

            if ($n > 0) {
                KingEventLog::write('sys', '', 'warning', "Skipped {$n} stale pending outbox row(s) older than 1h");
                $this->warn("Skipped {$n} stale pending outbox row(s)");
            }
        });

        Loop::get()->addTimer(8, function () {
            if (! $this->conn || ! $this->joinedRoom) {
                return;
            }

            $this->sessionReady = true;
            $this->info('Session ready — outbox unlocked');
            $this->logSys('info', 'Session ready — outbox unlocked');
            $this->scheduleInitialTableListSync();
        });
    }

    /* =====================================================================
     * Inbound messages
     * ===================================================================== */

    private function handleMessage(string $raw): void
    {
        $msg = json_decode($raw, true);
        if (! is_array($msg)) {
            return;
        }

        $uri = (string) ($msg['_URI'] ?? $msg['_uri'] ?? '');
        $param = $msg['param'] ?? $msg['PARAM'] ?? [];
        $param = is_array($param) ? $param : [];

        if ($uri === '_ping_pong') {
            return;
        }

        if ($uri !== 'GetKingTableListReq') {
            $this->db(fn () => KingEventLog::write('in', $uri, 'info', (string) ($param['message'] ?? ''), $param));
        }

        switch ($uri) {
            case '_login':
                if ($param['status'] ?? false) {
                    $clientId = (string) ($param['ID'] ?? $param['USERNAME'] ?? '');
                    $this->db(fn () => $this->sync->rememberClientId($clientId));
                    $this->loggedIn = true;
                    $this->info("Logged in (client id: $clientId). Joining room...");
                    $this->send('JoinKingRoomRequest', []);
                } else {
                    $this->error('Login rejected: ' . ($param['message'] ?? ''));
                    $this->logSys('error', 'Login rejected: ' . ($param['message'] ?? ''));
                    $this->reconnectDelay = 60;
                    $this->forceClose('login-rejected');
                }

                return;

            case '_login_error':
                $this->error('Login failed: ' . ($param['message'] ?? ''));
                $this->logSys('error', 'Login failed (check KING_WS_API_KEY / SECRET): ' . ($param['message'] ?? ''));
                $this->reconnectDelay = 60;
                $this->forceClose('login-error');

                return;

            case 'JoinKingRoomRequest':
                if (! $this->joinedRoom && ($param['status'] ?? false)) {
                    $this->joinedRoom = true;
                    $this->info('Room joined. Stabilizing session...');
                    $this->logSys('info', 'Room joined — stabilizing before outbox/table sync');
                    $this->scheduleSessionReady();

                    return;
                }
                break; // unsolicited join-room info falls through

            case 'GetKingTableListReq':
                $tables = is_array($param['data'] ?? null) ? $param['data'] : [];
                $this->db(fn () => $this->sync->reconcileList($tables));
                $this->logSys('info', 'Table list reconciled ('.count($tables).' rows)');

                return;
        }

        # Remote (or our) result updates — Win / Loss / Cancel + proof URLs.
        if ($uri === 'ResultUpdateRequest') {
            if ($this->inflight && $this->inflight->event === 'ResultUpdateRequest' && $this->matchesInflight($param)) {
                $this->resolveInflight($param);
            } else {
                $this->db(fn () => $this->sync->handleResultUpdateRequest($param));
            }

            return;
        }

        # Response to our in-flight outbox message?
        if ($this->inflight && $uri === $this->inflight->event && $this->matchesInflight($param)) {
            $this->resolveInflight($param);

            return;
        }

        # Unsolicited server push (another platform acted on a table)
        $this->db(fn () => $this->handleUnsolicited($uri, $param));
    }

    private function handleUnsolicited(string $uri, array $param): void
    {
        $data = is_array($param['data'] ?? null) ? $param['data'] : null;

        if ($uri === 'KingTableDeleteRequest') {
            $tableId = (string) ($data['tableId'] ?? $data['id'] ?? '');
            if ($tableId !== '') {
                $this->sync->handleTableRemoved($tableId);
            }

            return;
        }

        if ($data && isset($data['id'])) {
            $this->sync->applyTableSnapshot($data);
        }
    }

    /* =====================================================================
     * Outbox pump
     * ===================================================================== */

    private function pumpOutbox(): void
    {
        // Wait until the King session is stable — flooding stale outbox right
        // after JoinKingRoomRequest makes their server drop us with 1000 Bye.
        if (! $this->conn || ! $this->loggedIn || ! $this->joinedRoom || ! $this->sessionReady || $this->inflight || $this->isPaused()) {
            return;
        }

        $row = $this->db(function () {
            return KingOutbox::query()
                ->where('status', KingOutbox::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->orderByRaw("CASE WHEN event = 'KingAcceptRequest' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->first();
        });

        if (! $row) {
            return;
        }

        $wire = $this->db(fn () => $this->sync->buildWirePayload($row), ['action' => 'defer']);

        if ($wire['action'] === 'skip') {
            $this->db(function () use ($row, $wire) {
                $row->status = KingOutbox::STATUS_SKIPPED;
                $row->error = $wire['reason'] ?? null;
                $row->save();
            });

            return;
        }

        if ($wire['action'] !== 'send') {
            $this->db(function () use ($row) {
                $row->available_at = now()->addSeconds(3);
                $row->save();
            });

            return;
        }

        $payload = $wire['payload'];

        $this->db(function () use ($row, $payload) {
            $row->status = KingOutbox::STATUS_SENT;
            $row->attempts = $row->attempts + 1;
            $row->sent_at = now();
            if (! empty($payload['tableId'])) {
                $row->king_table_id = (string) $payload['tableId'];
            }
            $row->payload = json_encode($payload);
            $row->save();

            KingEventLog::write('out', $row->event, 'info', 'Sent outbox #' . $row->id, $payload);
        });

        $this->inflight = $row;
        $this->inflightSentAt = microtime(true);

        $this->send($row->event, $payload);
    }

    private function matchesInflight(array $param): bool
    {
        // Error responses carry no table data - with one message in flight we
        // attribute them to it.
        if (! ($param['status'] ?? false)) {
            return true;
        }

        $data = is_array($param['data'] ?? null) ? $param['data'] : [];
        $payload = $this->inflight->payloadArray();

        switch ($this->inflight->event) {
            case 'KingCreateTableRequest':
                $id = (string) ($data['id'] ?? '');

                return $id === '' || str_ends_with($id, '-' . ($payload['tableId'] ?? ''));

            case 'KingTableDeleteRequest':
                $id = (string) ($data['tableId'] ?? $data['id'] ?? '');

                return $id === '' || $id === (string) $this->inflight->king_table_id;

            default:
                $id = (string) ($data['id'] ?? '');

                return $id === '' || $id === (string) $this->inflight->king_table_id;
        }
    }

    private function resolveInflight(array $param): void
    {
        $row = $this->inflight;
        $this->inflight = null;

        $this->db(function () use ($row, $param) {
            $fresh = KingOutbox::find($row->id) ?: $row;

            $result = $this->sync->handleOutboxResponse($fresh, $param);

            $fresh->response = json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($result['status'] === 'success') {
                $fresh->status = KingOutbox::STATUS_SUCCESS;
                $fresh->error = null;
            } else {
                $fresh->status = KingOutbox::STATUS_FAILED;
                $fresh->error = mb_substr((string) ($result['error'] ?? 'Rejected'), 0, 490);
            }

            $fresh->save();
        });
    }

    private function requeueInflight(string $reason): void
    {
        if (! $this->inflight) {
            return;
        }

        $row = $this->inflight;
        $this->inflight = null;

        $this->db(function () use ($row, $reason) {
            $fresh = KingOutbox::find($row->id);
            if (! $fresh || $fresh->status !== KingOutbox::STATUS_SENT) {
                return;
            }

            if ($fresh->event === 'KingAcceptRequest') {
                // Never blind-retry accepts: the HTTP request is waiting and a
                // duplicate join could double-debit.
                $fresh->status = KingOutbox::STATUS_FAILED;
                $fresh->error = $reason;
            } else {
                $fresh->status = KingOutbox::STATUS_PENDING;
                $fresh->available_at = now()->addSeconds(3);
            }

            $fresh->save();
        });
    }

    /* =====================================================================
     * Helpers
     * ===================================================================== */

    private function send(string $uri, array $param): void
    {
        if (! $this->conn) {
            return;
        }

        // Hard block — King closes with 1000 Bye on outbound list requests.
        if ($uri === 'GetKingTableListReq') {
            $this->logSys('warning', 'Blocked outbound GetKingTableListReq (remote Bye kick)');

            return;
        }

        try {
            $this->conn->send(json_encode([
                '_URI' => $uri,
                'PARAM' => $param ?: new \stdClass(),
            ]));
        } catch (\Throwable $e) {
            Log::error('[King] send failed', ['uri' => $uri, 'error' => $e->getMessage()]);
        }
    }

    private function touchAlive(): void
    {
        try {
            Cache::put('king:alive_at', time(), 120);
        } catch (\Throwable $e) {
        }
    }

    private function rememberDaemonError(string $message): void
    {
        try {
            Cache::put('king:daemon_last_error', $message, 3600);
        } catch (\Throwable $e) {
        }
    }

    private function clearDaemonError(): void
    {
        try {
            Cache::forget('king:daemon_last_error');
        } catch (\Throwable $e) {
        }
    }

    private function isPaused(): bool
    {
        try {
            return (bool) Cache::get('king:paused', false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function housekeeping(): void
    {
        $this->db(function () {
            $days = max(1, (int) config('king.log_retention_days', 7));
            KingEventLog::query()->where('created_at', '<', now()->subDays($days))->delete();

            KingOutbox::query()
                ->whereIn('status', [KingOutbox::STATUS_SUCCESS, KingOutbox::STATUS_SKIPPED])
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
        });
    }

    private function logSys(string $level, string $message): void
    {
        $this->db(fn () => KingEventLog::write('sys', null, $level, $message));
    }

    /**
     * Run a DB closure with automatic reconnect - the daemon lives for days
     * and MySQL closes idle connections.
     */
    private function db(callable $fn, $default = null)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning('[King] DB operation failed, reconnecting', ['error' => $e->getMessage()]);

            try {
                DB::reconnect();

                return $fn();
            } catch (\Throwable $e2) {
                Log::error('[King] DB operation failed after reconnect', ['error' => $e2->getMessage()]);

                return $default;
            }
        }
    }
}
