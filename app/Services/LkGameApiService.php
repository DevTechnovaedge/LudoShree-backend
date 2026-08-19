<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LK Game service: check room code and fetch game status/result.
 *
 * @see GET /game-checkroom/{roomCode}
 * @see GET /game-status/{gameId}
 */
class LkGameApiService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.lk_game.base_url', ''), '/');
    }

    public function apiKey(): string
    {
        return trim((string) config('services.lk_game.api_key', ''));
    }

    /**
     * Raw check-room response or null on transport/parse failure.
     */
    public function checkRoom(string $roomCode): ?object
    {
        $roomCode = trim($roomCode);
        if ($roomCode === '' || $this->apiKey() === '') {
            return null;
        }

        $url = $this->baseUrl().'/game-checkroom/'.rawurlencode($roomCode);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey(),
                'Accept' => 'application/json',
            ])->timeout(25)->get($url);
        } catch (\Throwable $e) {
            Log::warning('[LK Game] checkRoom request failed', ['message' => $e->getMessage(), 'url' => $url]);

            return null;
        }

        $data = $response->object();
        if (! is_object($data)) {
            Log::warning('[LK Game] checkRoom invalid JSON', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('[LK Game] checkRoom HTTP error', ['status' => $response->status()]);
        }

        return $data;
    }

    public function isCheckRoomValid(?object $check): bool
    {
        if (! $check) {
            return false;
        }

        if ($this->lkBoolMeansTrue($check->valid ?? null)) {
            return true;
        }

        if (isset($check->data) && is_object($check->data) && $this->lkBoolMeansTrue($check->data->valid ?? null)) {
            return true;
        }

        return false;
    }

    /**
     * Parsed game-status body (including API error payloads with status/msg on HTTP 400), or null on transport/useless body.
     */
    public function gameStatus(string $gameId): ?object
    {
        $gameId = trim($gameId);
        if ($gameId === '' || $this->apiKey() === '') {
            return null;
        }

        $url = $this->baseUrl().'/game-status/'.rawurlencode($gameId);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey(),
                'Accept' => 'application/json',
            ])->timeout(25)->get($url);
        } catch (\Throwable $e) {
            Log::warning('[LK Game] gameStatus request failed', ['message' => $e->getMessage(), 'url' => $url]);

            return null;
        }

        $data = $response->object();
        if (! is_object($data)) {
            Log::warning('[LK Game] gameStatus invalid JSON', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return $this->unwrapGamePayload($data);
    }

    /**
     * Some LK gateways wrap the match payload in data/result.
     */
    public function unwrapGamePayload(object $data): object
    {
        foreach (['data', 'result', 'game', 'payload'] as $key) {
            if (isset($data->{$key}) && is_object($data->{$key})
                && (isset($data->{$key}->game_id) || isset($data->{$key}->game_status) || isset($data->{$key}->winner_id))) {
                return $data->{$key};
            }
        }

        return $data;
    }

    /**
     * Stored value can be an internal game_id (24-hex) or a room code from the app.
     * Uses game-checkroom whenever stored is not already a hex id; prefers game_id from response
     * so finished games still resolve after checkroom stops reporting valid:true.
     */
    public function resolveGameId(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        if (preg_match('/^[a-f\d]{24}$/i', $stored)) {
            return strtolower($stored);
        }

        $room = $this->checkRoom($stored);
        if (! is_object($room)) {
            return null;
        }

        return $this->extractMongoGameId($room);
    }

    /**
     * Find a 24-hex LK game id anywhere in a check-room / status payload.
     */
    public function extractMongoGameId(?object $payload): ?string
    {
        if (! $payload) {
            return null;
        }

        $queue = [$payload];
        $seen = 0;
        while ($queue !== [] && $seen < 10) {
            $node = array_shift($queue);
            $seen++;
            if (! is_object($node)) {
                continue;
            }

            foreach (['game_id', 'gameId', '_id', 'id'] as $k) {
                if (! isset($node->{$k})) {
                    continue;
                }
                $id = self::lkComparableId($node->{$k});
                if ($id !== null && preg_match('/^[a-f\d]{24}$/i', $id)) {
                    return strtolower($id);
                }
            }

            foreach (['data', 'result', 'game', 'payload'] as $k) {
                if (isset($node->{$k}) && is_object($node->{$k})) {
                    $queue[] = $node->{$k};
                }
            }
        }

        return null;
    }

    /**
     * Flatten Ludo King ids from JSON ($oid nests, ints, casing) — no link to app users.
     */
    private static function lkComparableId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = (object) $value;
        }

        if (is_object($value)) {
            foreach (['$oid', '$id', 'oid', 'id', '_id', 'hex'] as $k) {
                if (isset($value->{$k}) && ($value->{$k} !== '')) {
                    $value = $value->{$k};

                    break;
                }
            }
            if (is_object($value)) {
                return null;
            }
        }

        $s = strtolower(trim((string) $value));

        return $s !== '' ? $s : null;
    }

    /**
     * True when game_status is terminal and payload tells who won (creator vs player in LK terms).
     */
    public function isResolvedFinished(object $payload): bool
    {
        return $this->winnerSide($payload) !== null;
    }

    /**
     * Who won in *our* model: challenger = LK creator, opponent = LK player/joiner.
     * Derived only from result API fields (ids compared within payload, optional role booleans/strings).
     *
     * @return 'challenger'|'opponent'|null
     */
    public function winnerSide(object $resolvedPayload): ?string
    {
        // Error envelopes often look like {"status":400,"msg":"..."} with no game_id
        if (! isset($resolvedPayload->game_id) && isset($resolvedPayload->status) && is_numeric($resolvedPayload->status ?? null)) {
            return null;
        }

        $gsNorm = strtolower(trim((string) ($resolvedPayload->game_status ?? $resolvedPayload->status ?? '')));
        $inProgress = ['waiting', 'started', 'playing', 'ongoing', 'running', 'inprogress', 'in_progress', 'pending'];
        if (in_array($gsNorm, $inProgress, true)) {
            return null;
        }

        return $this->creatorOrPlayerWonSideFromPayload($resolvedPayload);
    }

    /**
     * Decide winner side from API only: winner_id vs creator/player ids, then explicit role/win flags.
     *
     * @return 'challenger'|'opponent'|null
     */
    private function creatorOrPlayerWonSideFromPayload(object $p): ?string
    {
        $w = self::lkComparableId($p->winner_id ?? $p->winnerId ?? null);
        $c = self::lkComparableId($p->creator_id ?? $p->creatorId ?? $p->owner_id ?? $p->ownerId ?? null);
        $pl = self::lkComparableId($p->player_id ?? $p->playerId ?? $p->player1_id ?? $p->joiner_id ?? null);

        // LK semantics: creator wins match -> our challenger; player (joiner) wins -> our opponent
        if ($w !== null && $c !== null && $w === $c) {
            return 'challenger';
        }

        if ($w !== null && $pl !== null && $w === $pl) {
            return 'opponent';
        }

        // Some payloads use 1/2 instead of mongo ids
        if ($w === '1' || $w === 'player1' || $w === 'p1') {
            return 'challenger';
        }
        if ($w === '2' || $w === 'player2' || $w === 'p2') {
            return 'opponent';
        }

        $ownerStatus = strtolower(trim((string) ($p->ownerstatus ?? $p->owner_status ?? $p->creator_status ?? '')));
        $playerStatus = strtolower(trim((string) ($p->player1status ?? $p->player_status ?? $p->player1_status ?? '')));
        if (in_array($ownerStatus, ['won', 'win', 'winner'], true)) {
            return 'challenger';
        }
        if (in_array($playerStatus, ['won', 'win', 'winner'], true)) {
            return 'opponent';
        }

        $side = $this->winnerFromPlayersList($p);
        if ($side !== null) {
            return $side;
        }

        // Explicit role hints (still no backend user coupling)
        $roleFields = [$p->winner_side ?? null, $p->winnerSide ?? null, $p->winner_role ?? null, $p->winning_side ?? null, $p->winner_type ?? null];
        foreach ($roleFields as $raw) {
            $mapped = $this->lkRoleHintToChallengeSide($raw);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if (isset($p->winner) && ! is_object($p->winner)) {
            $mapped = $this->lkRoleHintToChallengeSide($p->winner);
            if ($mapped !== null) {
                return $mapped;
            }
            $mapped = $this->lkRoleHintToChallengeSide((string) $p->winner);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if ($this->lkBoolMeansTrue($p->creator_win ?? null) || $this->lkBoolMeansTrue($p->owner_win ?? null)) {
            return 'challenger';
        }

        if ($this->lkBoolMeansTrue($p->player_win ?? null) || $this->lkBoolMeansTrue($p->player1_win ?? null)) {
            return 'opponent';
        }

        return null;
    }

    /**
     * @return 'challenger'|'opponent'|null
     */
    private function winnerFromPlayersList(object $p): ?string
    {
        $players = $p->players ?? $p->player_list ?? null;
        if (! is_array($players) && ! is_object($players)) {
            return null;
        }

        foreach ($players as $player) {
            if (is_array($player)) {
                $player = (object) $player;
            }
            if (! is_object($player)) {
                continue;
            }
            $won = $this->lkBoolMeansTrue($player->is_winner ?? $player->winner ?? $player->win ?? null)
                || in_array(strtolower(trim((string) ($player->status ?? $player->result ?? ''))), ['won', 'win', 'winner'], true);
            if (! $won) {
                continue;
            }

            $role = $this->lkRoleHintToChallengeSide($player->role ?? $player->type ?? $player->side ?? null);
            if ($role !== null) {
                return $role;
            }

            $pid = self::lkComparableId($player->id ?? $player->_id ?? $player->player_id ?? null);
            $c = self::lkComparableId($p->creator_id ?? $p->creatorId ?? null);
            $pl = self::lkComparableId($p->player_id ?? $p->playerId ?? null);
            if ($pid !== null && $c !== null && $pid === $c) {
                return 'challenger';
            }
            if ($pid !== null && $pl !== null && $pid === $pl) {
                return 'opponent';
            }
        }

        return null;
    }

    /**
     * @param  mixed  $raw
     * @return 'challenger'|'opponent'|null
     */
    private function lkRoleHintToChallengeSide($raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_object($raw) && method_exists($raw, '__toString')) {
            $raw = (string) $raw;
        }

        if (! is_scalar($raw)) {
            return null;
        }

        $s = strtolower(trim((string) $raw));
        if ($s === '') {
            return null;
        }

        if (in_array($s, ['creator', 'challenger', 'owner', 'host', 'room_creator', 'creator_id', 'player1', 'p1', '1'], true)) {
            return 'challenger';
        }

        if (in_array($s, ['player', 'joiner', 'opponent', 'guest', 'participant', 'player_id', 'room_player', 'player2', 'p2', '2'], true)) {
            return 'opponent';
        }

        return null;
    }

    /**
     * @param  mixed  $v
     */
    private function lkBoolMeansTrue($v): bool
    {
        if ($v === true || $v === 1) {
            return true;
        }
        $s = strtolower(trim((string) $v));

        return $s === '1' || $s === 'true' || $s === 'yes';
    }

    /**
     * Map LK game-status JSON to legacy-style fields used by admin UI hints.
     */
    public function normalizeForChallenge(object $api): object
    {
        $out = json_decode(json_encode($api), false);

        $gs = $out->game_status ?? null;
        $out->status = $out->status ?? $gs;
        $out->game_status = $gs;

        $side = $this->creatorOrPlayerWonSideFromPayload($out);
        if ($side === 'challenger') {
            $out->ownerstatus = 'Won';
            $out->player1status = 'Lost';
            $out->lk_winner_mapped_to = 'creator';
        }
        if ($side === 'opponent') {
            $out->ownerstatus = 'Lost';
            $out->player1status = 'Won';
            $out->lk_winner_mapped_to = 'player';
        }

        return $out;
    }
}
