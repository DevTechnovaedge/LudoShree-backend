<?php

namespace App\Services\King\Connection;

/**
 * Explicit WebSocket session phases for the King daemon.
 *
 * Flow: disconnected → connecting → authenticating → joining → ready
 * Reconnect only from disconnected (unexpected drop / dead socket).
 */
final class KingConnectionBloc
{
    public const DISCONNECTED = 'disconnected';

    public const CONNECTING = 'connecting';

    public const AUTHENTICATING = 'authenticating';

    public const JOINING = 'joining';

    public const READY = 'ready';

    private string $state = self::DISCONNECTED;

    private float $readySince = 0.0;

    private int $reconnectDelaySec = 5;

    private string $lastOutboundUri = '';

    private float $lastOutboundAt = 0.0;

    public function state(): string
    {
        return $this->state;
    }

    public function isReady(): bool
    {
        return $this->state === self::READY;
    }

    public function canSendGameEvents(): bool
    {
        return in_array($this->state, [self::JOINING, self::READY], true);
    }

    public function canPumpOutbox(): bool
    {
        return $this->state === self::READY;
    }

    public function canHeartbeat(): bool
    {
        // Heartbeat only after the session is fully ready — early pings have
        // been observed to correlate with remote 1000 Bye kicks.
        return $this->state === self::READY;
    }

    public function markConnecting(): void
    {
        $this->state = self::CONNECTING;
        $this->readySince = 0.0;
    }

    public function markAuthenticating(): void
    {
        $this->state = self::AUTHENTICATING;
        $this->readySince = 0.0;
    }

    public function markJoining(): void
    {
        $this->state = self::JOINING;
        $this->readySince = 0.0;
    }

    public function markReady(): void
    {
        $this->state = self::READY;
        $this->readySince = microtime(true);
    }

    public function markDisconnected(): void
    {
        $this->state = self::DISCONNECTED;
        $this->readySince = 0.0;
    }

    public function noteOutbound(string $uri): void
    {
        $this->lastOutboundUri = $uri;
        $this->lastOutboundAt = microtime(true);
    }

    public function lastOutboundSummary(): string
    {
        if ($this->lastOutboundUri === '') {
            return 'none';
        }

        $ago = round(microtime(true) - $this->lastOutboundAt, 2);

        return "{$this->lastOutboundUri} ({$ago}s ago)";
    }

    /**
     * Stable session long enough to treat the link as healthy (reset backoff).
     */
    public function isStable(int $stableAfterSec = 90): bool
    {
        return $this->isReady()
            && $this->readySince > 0
            && (microtime(true) - $this->readySince) >= $stableAfterSec;
    }

    public function nextReconnectDelay(): int
    {
        $delay = $this->reconnectDelaySec;
        $this->reconnectDelaySec = min(60, max(5, $this->reconnectDelaySec * 2));

        return $delay;
    }

    public function resetReconnectBackoff(): void
    {
        $this->reconnectDelaySec = 5;
    }

    public function softenBackoffAfterRemoteBye(): void
    {
        // After a remote Bye, never reconnect in under 5s — rapid re-login
        // storms make Daddy King keep kicking the new session.
        $this->reconnectDelaySec = max(5, $this->reconnectDelaySec);
    }
}
