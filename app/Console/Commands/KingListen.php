<?php

namespace App\Console\Commands;

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

    private KingSyncService $sync;

    public function handle(KingSyncService $sync): int
    {
        $this->sync = $sync;

        if (! config('king.enabled')) {
            $this->error('King integration is disabled (KING_WS_ENABLED=false).');

            return self::FAILURE;
        }

        if (trim((string) config('king.api_key')) === '' || trim((string) config('king.api_secret')) === '') {
            $this->error('KING_WS_API_KEY / KING_WS_API_SECRET are not configured.');

            return self::FAILURE;
        }

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
        $connector = new Connector(Loop::get());

        $connector((string) config('king.ws_url'))->then(
            function (WebSocket $conn) {
                $this->onConnected($conn);
            },
            function (\Throwable $e) {
                $this->warn('Connect failed: ' . $e->getMessage());
                $this->logSys('warning', 'Connect failed: ' . $e->getMessage());
                $this->scheduleReconnect();
            }
        );
    }

    private function onConnected(WebSocket $conn): void
    {
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

        $conn->on('close', function ($code = null, $reason = null) {
            $this->warn("Connection closed ($code - $reason)");
            $this->logSys('warning', "Connection closed ($code - $reason)");
            $this->conn = null;
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
            $this->send('_ping_pong', []);
            $this->touchAlive();
        });

        # Outbox pump
        $this->timers[] = $loop->addPeriodicTimer(max(0.2, (float) config('king.outbox_interval', 0.25)), function () {
            $this->pumpOutbox();
        });

        # Table list reconciliation
        $this->timers[] = $loop->addPeriodicTimer(max(5, (int) config('king.table_poll_interval', 10)), function () {
            if ($this->joinedRoom && ! $this->isPaused()) {
                $this->send('GetKingTableListReq', []);
            }
        });

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

    private function scheduleReconnect(): void
    {
        $this->cancelTimers();
        $this->conn = null;
        $this->loggedIn = false;
        $this->joinedRoom = false;
        $this->requeueInflight('Reconnecting');

        $delay = $this->reconnectDelay;
        $this->reconnectDelay = min(60, $this->reconnectDelay * 2);

        $this->info("Reconnecting in {$delay}s...");

        Loop::get()->addTimer($delay, function () {
            $this->connect();
        });
    }

    private function closeConnection(): void
    {
        $this->cancelTimers();

        if ($this->conn) {
            try {
                $this->conn->close();
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

            if ($this->conn) {
                $this->conn->close();
            }

            return;
        }

        # Dead connection (no pong / no traffic)
        if ($this->conn && (microtime(true) - $this->lastActivityAt) > (int) config('king.alive_ttl', 30)) {
            $this->warn('No traffic - forcing reconnect');
            $this->conn->close();
        }
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
                    $this->conn?->close();
                }

                return;

            case '_login_error':
                $this->error('Login failed: ' . ($param['message'] ?? ''));
                $this->logSys('error', 'Login failed (check KING_WS_API_KEY / SECRET): ' . ($param['message'] ?? ''));
                $this->reconnectDelay = 60;
                $this->conn?->close();

                return;

            case 'JoinKingRoomRequest':
                if (! $this->joinedRoom && ($param['status'] ?? false)) {
                    $this->joinedRoom = true;
                    $this->info('Room joined. Syncing table list...');
                    $this->send('GetKingTableListReq', []);

                    return;
                }
                break; // unsolicited join-room info falls through

            case 'GetKingTableListReq':
                $tables = is_array($param['data'] ?? null) ? $param['data'] : [];
                $this->db(fn () => $this->sync->reconcileList($tables));

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
        if (! $this->conn || ! $this->loggedIn || ! $this->joinedRoom || $this->inflight || $this->isPaused()) {
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
