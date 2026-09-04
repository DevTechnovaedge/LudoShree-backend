<?php

namespace App\Services\King;

use App\Models\GameChallenge\GameChallenge;
use App\Models\King\KingEventLog;
use App\Models\King\KingOutbox;
use App\Models\King\KingTable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Applies King network state (table snapshots, list reconciliation, outbox
 * responses) onto local GameChallenge rows. All operations are idempotent so
 * the same event can safely arrive twice (server push + list polling).
 */
class KingSyncService
{
    public function __construct(
        private readonly KingPlayerService $players,
        private readonly KingSettlementService $settlement,
        private readonly KingOutboxService $outbox,
    ) {}

    /* =====================================================================
     * Identity
     * ===================================================================== */

    public function ourClientId(): string
    {
        $cached = (string) Cache::get('king:client_id', '');

        return $cached !== '' ? $cached : (string) config('king.client_id', '');
    }

    public function rememberClientId(string $clientId): void
    {
        if (trim($clientId) !== '') {
            Cache::forever('king:client_id', trim($clientId));
        }
    }

    /**
     * King player ids look like "{clientId}-{userId}".
     */
    public function isOurPlayerId(?string $playerId): bool
    {
        $clientId = $this->ourClientId();
        if ($clientId === '' || ! $playerId) {
            return false;
        }

        return str_starts_with($playerId, $clientId . '-');
    }

    public function localUserIdFromPlayerId(string $playerId): ?int
    {
        if (! $this->isOurPlayerId($playerId)) {
            return null;
        }

        $suffix = substr($playerId, strlen($this->ourClientId()) + 1);

        return is_numeric($suffix) ? (int) $suffix : null;
    }

    /* =====================================================================
     * Table snapshots
     * ===================================================================== */

    /**
     * Upsert one King table snapshot (from list responses, request responses
     * or unsolicited server pushes) into the mirror + local challenge state.
     */
    public function applyTableSnapshot(array $t): void
    {
        $kingTableId = (string) ($t['id'] ?? '');
        if ($kingTableId === '') {
            return;
        }

        $createdById = (string) ($t['createdBy']['id'] ?? '');
        $joinedById = isset($t['joinedBy']['id']) ? (string) $t['joinedBy']['id'] : null;
        $origin = $this->isOurPlayerId($createdById) ? 'local' : 'remote';

        $roomCode = $t['cHistory']['kingRoom']['code'] ?? $t['jHistory']['kingRoom']['code'] ?? null;
        $creatorResult = $this->normalizeResult($t['cHistory']['status'] ?? null);
        $joinerResult = $this->normalizeResult($t['jHistory']['status'] ?? null);

        $mirror = KingTable::firstOrNew(['king_table_id' => $kingTableId]);
        $isNewMirror = ! $mirror->exists;

        $mirror->fill([
            'origin' => $origin,
            'amount' => (float) ($t['amount'] ?? 0),
            'status' => (string) ($t['status'] ?? 'Pending'),
            'created_by_id' => $createdById ?: null,
            'created_by_name' => $t['createdBy']['fullName'] ?? null,
            'joined_by_id' => $joinedById,
            'joined_by_name' => $t['joinedBy']['fullName'] ?? null,
            'room_code' => $roomCode ? (string) $roomCode : $mirror->room_code,
            'creator_result' => $creatorResult ?: $mirror->creator_result,
            'joiner_result' => $joinerResult ?: $mirror->joiner_result,
            'raw' => json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_seen_at' => now(),
        ]);
        $mirror->save();

        $challenge = $this->resolveChallenge($mirror, $t, $origin, $isNewMirror);
        if (! $challenge) {
            return;
        }

        if ($mirror->game_challenge_id !== $challenge->id) {
            $mirror->game_challenge_id = $challenge->id;
            $mirror->save();
        }

        if ($origin === 'local') {
            $this->applyToLocalTable($challenge, $t, $joinedById, $joinerResult);
        } else {
            $this->applyToRemoteTable($challenge, $t, $joinedById, $roomCode, $creatorResult);
        }

        $challenge->refresh();
        $this->applyResultMediaFromSnapshot($challenge, $t);
    }

    /**
     * Inbound ResultUpdateRequest from the King network — another platform
     * reported Win/Loss/Cancel (optional image/video URLs), or the success
     * response after our own outbox send.
     */
    public function handleResultUpdateRequest(array $param): void
    {
        $ok = array_key_exists('status', $param) ? (bool) $param['status'] : true;
        $message = (string) ($param['message'] ?? '');
        $data = is_array($param['data'] ?? null) ? $param['data'] : null;

        if (! $ok && ! $data) {
            KingEventLog::write('in', 'ResultUpdateRequest', 'warning', $message ?: 'Result update rejected', $param);

            return;
        }

        if ($data && ! empty($data['id'])) {
            $this->applyTableSnapshot($data);
            $this->logResultSyncFromSnapshot($data, $message);

            return;
        }

        $tableId = (string) ($param['tableId'] ?? '');
        $userId = (string) ($param['userId'] ?? '');
        $outcome = $this->normalizeResultParam($param['result'] ?? null);

        if ($tableId === '' || $userId === '' || ! $outcome) {
            KingEventLog::write('in', 'ResultUpdateRequest', 'warning', 'Incomplete result update payload', $param);

            return;
        }

        $this->applyCompactResultUpdate($tableId, $userId, $outcome, $param);
    }

    /**
     * Full-list reconciliation: apply every snapshot, then close waiting
     * tables that vanished from the network.
     */
    public function reconcileList(array $tables): void
    {
        $seenIds = [];

        foreach ($tables as $t) {
            if (! is_array($t)) {
                continue;
            }

            $seenIds[] = (string) ($t['id'] ?? '');

            try {
                $this->applyTableSnapshot($t);
            } catch (\Throwable $e) {
                Log::error('[King] applyTableSnapshot failed', ['table' => $t['id'] ?? null, 'error' => $e->getMessage()]);
                KingEventLog::write('sys', 'GetKingTableListReq', 'error', 'Snapshot apply failed for ' . ($t['id'] ?? '?') . ': ' . $e->getMessage());
            }
        }

        // Waiting tables that are no longer on the network.
        $missing = KingTable::query()
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->whereNotIn('king_table_id', array_filter($seenIds))
            ->get();

        foreach ($missing as $mirror) {
            try {
                $this->handleMissingPendingTable($mirror);
            } catch (\Throwable $e) {
                Log::error('[King] missing table handling failed', ['table' => $mirror->king_table_id, 'error' => $e->getMessage()]);
            }
        }

        try {
            $backfilled = $this->backfillUnlinkedRemoteMirrors();
            if ($backfilled > 0) {
                KingEventLog::write('sys', 'GetKingTableListReq', 'info',
                    "Backfilled {$backfilled} unlinked Daddy King waiting table(s) into game challenges.");
            }
        } catch (\Throwable $e) {
            Log::error('[King] backfillUnlinkedRemoteMirrors failed', ['error' => $e->getMessage()]);
        }
    }

    public function handleTableRemoved(string $kingTableId): void
    {
        $mirror = KingTable::query()->where('king_table_id', $kingTableId)->first();
        if (! $mirror) {
            return;
        }

        if ($this->isKingWaitingStatus($mirror->status)) {
            $this->handleMissingPendingTable($mirror);
        } else {
            $mirror->status = 'Deleted';
            $mirror->save();
        }
    }

    /* =====================================================================
     * Outbox wire building + response handling (called by the daemon)
     * ===================================================================== */

    /**
     * Build the PARAM payload to actually send for an outbox row.
     *
     * @return array{action: string, payload?: array, reason?: string}
     *         action: send | defer | skip
     */
    public function buildWirePayload(KingOutbox $row): array
    {
        $payload = $row->payloadArray();
        $challenge = $row->game_challenge_id ? GameChallenge::withTrashed()->find($row->game_challenge_id) : null;

        switch ($row->event) {
            case 'KingCreateTableRequest':
                // Table may have been accepted/cancelled while the daemon was offline.
                if (! $challenge || $challenge->trashed() || (int) $challenge->status !== 0 || $challenge->opponent_id || $challenge->king_table_id) {
                    return ['action' => 'skip', 'reason' => 'Challenge no longer waiting'];
                }

                return ['action' => 'send', 'payload' => $payload];

            case 'KingAcceptRequest':
                if (! $challenge || $challenge->trashed()) {
                    return ['action' => 'skip', 'reason' => 'Challenge missing'];
                }

                $payload['tableId'] = (string) ($challenge->king_table_id ?: ($row->king_table_id ?? ''));
                if ($payload['tableId'] === '') {
                    return ['action' => 'skip', 'reason' => 'No King table id'];
                }

                return ['action' => 'send', 'payload' => $payload];

            case 'KingTableDeleteRequest':
            case 'KingUpdateCodeRequest':
            case 'ResultUpdateRequest':
                $tableId = $row->king_table_id ?: ($challenge->king_table_id ?? null);

                if (! $tableId) {
                    // The create for this challenge may still be in flight.
                    $createPending = $challenge && KingOutbox::query()
                        ->where('game_challenge_id', $challenge->id)
                        ->where('event', 'KingCreateTableRequest')
                        ->whereIn('status', [KingOutbox::STATUS_PENDING, KingOutbox::STATUS_SENT])
                        ->exists();

                    return $createPending
                        ? ['action' => 'defer']
                        : ['action' => 'skip', 'reason' => 'No King table id'];
                }

                $payload['tableId'] = (string) $tableId;

                return ['action' => 'send', 'payload' => $payload];
        }

        return ['action' => 'skip', 'reason' => 'Unknown event'];
    }

    /**
     * King server answered our outbox message.
     *
     * @return array{status: string, error?: string}  status: success | failed
     */
    public function handleOutboxResponse(KingOutbox $row, array $param): array
    {
        $ok = (bool) ($param['status'] ?? false);
        $message = (string) ($param['message'] ?? '');
        $data = is_array($param['data'] ?? null) ? $param['data'] : null;

        if (! $ok) {
            return $this->handleOutboxFailure($row, $message);
        }

        switch ($row->event) {
            case 'KingCreateTableRequest':
                if ($data && ! empty($data['id'])) {
                    $challenge = GameChallenge::find($row->game_challenge_id);
                    if ($challenge) {
                        $challenge->king_table_id = (string) $data['id'];
                        $challenge->king_sync_status = 'synced';
                        $challenge->saveQuietly();
                    }

                    $this->applyTableSnapshot($data);
                }
                break;

            case 'KingAcceptRequest':
                $result = $this->settlement->finalizeLocalAccept($row);

                if ($data) {
                    $this->applyTableSnapshot($data);
                }

                // The HTTP accept endpoint locked the row - always release it.
                $this->unlockChallenge($row->game_challenge_id);

                if (! $result['ok']) {
                    return ['status' => 'failed', 'error' => $result['reason']];
                }
                break;

            case 'KingTableDeleteRequest':
                KingTable::query()
                    ->where('king_table_id', (string) ($data['tableId'] ?? $row->king_table_id))
                    ->update(['status' => 'Deleted']);
                break;

            case 'KingUpdateCodeRequest':
                if ($data) {
                    $this->applyTableSnapshot($data);
                }
                break;

            case 'ResultUpdateRequest':
                $this->handleResultUpdateRequest($param);
                break;
        }

        return ['status' => 'success'];
    }

    private function handleOutboxFailure(KingOutbox $row, string $message): array
    {
        switch ($row->event) {
            case 'KingAcceptRequest':
                // Release the lock taken by the HTTP accept endpoint.
                $this->unlockChallenge($row->game_challenge_id);
                break;

            case 'KingCreateTableRequest':
                $challenge = GameChallenge::find($row->game_challenge_id);
                if ($challenge) {
                    $challenge->king_sync_status = 'failed';
                    $challenge->saveQuietly();
                }

                KingEventLog::write('sys', $row->event, 'warning',
                    "King rejected table create for challenge #{$row->game_challenge_id}: $message (table stays local-only)");
                break;

            case 'KingTableDeleteRequest':
                // "Table not found" means it is already gone - that is fine.
                return ['status' => 'success'];

            case 'KingUpdateCodeRequest':
            case 'ResultUpdateRequest':
                KingEventLog::write('sys', $row->event, 'error',
                    "King rejected {$row->event} for table {$row->king_table_id}: $message. DATA MAY BE INCONSISTENT - please verify manually.");
                break;
        }

        return ['status' => 'failed', 'error' => $message];
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    private function unlockChallenge(?int $gameChallengeId): void
    {
        if (! $gameChallengeId) {
            return;
        }

        $challenge = GameChallenge::find($gameChallengeId);
        if ($challenge && $challenge->is_lock) {
            unlock_game_challenge($challenge);
        }
    }

    private function resolveChallenge(KingTable $mirror, array $t, string $origin, bool $isNewMirror): ?GameChallenge
    {
        if ($mirror->game_challenge_id) {
            return GameChallenge::find($mirror->game_challenge_id);
        }

        $challenge = GameChallenge::query()->where('king_table_id', $mirror->king_table_id)->first();
        if ($challenge) {
            return $challenge;
        }

        if ($origin === 'local') {
            // King ids are "DK-{clientId}-{ourTableId}" where ourTableId is the
            // challenge id we sent in KingCreateTableRequest.
            $parts = explode('-', $mirror->king_table_id);
            $localId = is_numeric(end($parts)) ? (int) end($parts) : 0;

            $challenge = $localId ? GameChallenge::find($localId) : null;

            if ($challenge && ! $challenge->king_table_id) {
                $challenge->king_table_id = $mirror->king_table_id;
                $challenge->king_sync_status = 'synced';
                $challenge->saveQuietly();

                return $challenge;
            }

            if ($isNewMirror) {
                KingEventLog::write('sys', null, 'warning',
                    "CONSISTENCY: King reports OUR table {$mirror->king_table_id} but no matching local challenge was found.");
            }

            return $challenge;
        }

        // Remote table still waiting for a player -> mirror it as a joinable
        // local challenge with a ghost challenger.
        if ($this->isKingWaitingStatus($t['status'] ?? null) && king_enabled()) {
            return $this->createChallengeFromRemoteTable($mirror, $t);
        }

        return null;
    }

    private function createChallengeFromRemoteTable(KingTable $mirror, array $t): ?GameChallenge
    {
        $amount = (float) ($t['amount'] ?? 0);
        if ($amount <= 0) {
            return null;
        }

        if (! $this->isAmountAllowedForImport($amount)) {
            $min = (float) (site_setting()->minimum_game_play_amount ?? 0);
            $max = (float) (site_setting()->maximum_game_play_amount ?? 0);
            KingEventLog::write('sys', null, 'info',
                "Skipped remote table {$mirror->king_table_id}: amount $amount outside platform limits ($min - $max)");

            return null;
        }

        $ghost = $this->players->resolveGhostUser(
            (string) ($t['createdBy']['id'] ?? ''),
            (string) ($t['createdBy']['fullName'] ?? '')
        );

        // Same commission maths as a locally created challenge.
        $commission = resolve_game_commission($amount);
        $gameCommission = $commission['percent'];
        $gameCommissionAmount = $commission['amount'];
        $paidAmount = $commission['paid_amount'];

        $challenge = GameChallenge::create([
            'uid' => generate_uid(),
            'game_type_id' => (int) config('king.game_type_id', 1),
            'challenger_id' => $ghost->id,
            'challenger_amount' => $amount,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'game_commission' => $gameCommission,
            'game_commission_amount' => $gameCommissionAmount,
            'status' => 0,
            'game_source' => 'daddy_king',
            'king_table_id' => $mirror->king_table_id,
            'king_sync_status' => 'synced',
        ]);

        KingEventLog::write('sys', null, 'info',
            "Imported Daddy King table {$mirror->king_table_id} (amount $amount) as challenge #{$challenge->id}");

        return $challenge;
    }

    /**
     * Table created by OUR user - apply remote joins / joiner results.
     */
    private function applyToLocalTable(GameChallenge $challenge, array $t, ?string $joinedById, ?string $joinerResult): void
    {
        // A player from another platform joined our table.
        if ($joinedById && ! $this->isOurPlayerId($joinedById) && ! $challenge->opponent_id) {
            $ghost = $this->players->resolveGhostUser($joinedById, (string) ($t['joinedBy']['fullName'] ?? ''));
            $this->settlement->applyRemoteAccept($challenge, $ghost);
            $challenge->refresh();
        }

        // Remote joiner reported a result (only applies when the opponent
        // really is a ghost - local-vs-local games manage their own results).
        if ($joinerResult && $challenge->opponent_id && is_king_ghost_user($challenge->opponent_id)) {
            $this->settlement->applyRemoteResult($challenge, $joinerResult);
        }
    }

    /**
     * Table created on another platform (ghost challenger).
     */
    private function applyToRemoteTable(GameChallenge $challenge, array $t, ?string $joinedById, ?string $roomCode, ?string $creatorResult): void
    {
        if ($joinedById && ! $challenge->opponent_id) {
            if ($this->isOurPlayerId($joinedById)) {
                // Our accept was confirmed on the network but the local
                // finalize has not landed yet - the outbox handler does it.
                // If it never lands the admin sees this log.
                if (! app(KingOutboxService::class)->hasPendingAccept($challenge->id)) {
                    KingEventLog::write('sys', null, 'error',
                        "CONSISTENCY: King shows OUR player $joinedById joined table {$challenge->king_table_id} but local challenge #{$challenge->id} has no opponent. Please verify.");
                }
            } else {
                // Taken by a player on some other platform - remove from our lobby.
                $this->settlement->closeRemoteWaitingChallenge($challenge, 'Table accepted on another platform');

                return;
            }
        }

        // Room code set by the remote creator.
        if ($roomCode && $challenge->opponent_id && ! $challenge->roomcode && ! is_king_ghost_user($challenge->opponent_id)) {
            $this->settlement->applyRemoteRoomcode($challenge, (string) $roomCode);
            $challenge->refresh();
        }

        // Remote creator reported a result.
        if ($creatorResult && $challenge->opponent_id && is_king_ghost_user($challenge->challenger_id) && ! is_king_ghost_user($challenge->opponent_id)) {
            $this->settlement->applyRemoteResult($challenge, $creatorResult);
        }
    }

    private function handleMissingPendingTable(KingTable $mirror): void
    {
        $mirror->status = $mirror->origin === 'remote' ? 'Deleted' : 'Missing';
        $mirror->save();

        $challenge = $mirror->game_challenge_id ? GameChallenge::find($mirror->game_challenge_id) : null;
        if (! $challenge) {
            return;
        }

        if ($mirror->origin === 'remote') {
            // The remote creator deleted their table (or it was taken elsewhere).
            $this->settlement->closeRemoteWaitingChallenge($challenge, 'Table removed on Daddy King network');

            return;
        }

        // OUR waiting table vanished from the network - it can still be played
        // locally, but remote players can no longer see it.
        if ((int) $challenge->status === 0 && ! $challenge->opponent_id) {
            $challenge->king_sync_status = 'failed';
            $challenge->saveQuietly();

            KingEventLog::write('sys', null, 'warning',
                "Our table {$mirror->king_table_id} (challenge #{$challenge->id}) disappeared from the King network while still waiting. It remains available locally only.");
        }
    }

    private function normalizeResult($status): ?string
    {
        $s = strtolower(trim((string) $status));
        if ($s === '' || $s === 'running') {
            return null;
        }

        if (str_contains($s, 'won') || str_contains($s, 'win')) {
            return 'win';
        }

        if (str_contains($s, 'los')) {
            return 'loss';
        }

        if (str_contains($s, 'cancel')) {
            return 'cancel';
        }

        return null;
    }

    private function normalizeResultParam($result): ?string
    {
        return $this->normalizeResult($result);
    }

    private function applyCompactResultUpdate(string $tableId, string $userId, string $outcome, array $param): void
    {
        $challenge = GameChallenge::query()->where('king_table_id', $tableId)->first();
        if (! $challenge) {
            KingEventLog::write('in', 'ResultUpdateRequest', 'warning',
                "No local challenge linked to table $tableId", $param);

            return;
        }

        foreach ([(int) $challenge->challenger_id, (int) $challenge->opponent_id] as $localId) {
            if ($localId > 0 && ! is_king_ghost_user($localId) && $this->networkPlayerIdsMatch($userId, (string) $localId)) {
                // Our user already updated locally via the app API.
                return;
            }
        }

        if ($this->isOurPlayerId($userId)) {
            return;
        }

        $mirror = KingTable::query()->where('king_table_id', $tableId)->first();
        $createdBy = (string) ($mirror?->created_by_id ?? '');
        $joinedBy = (string) ($mirror?->joined_by_id ?? '');

        $ghostSide = null;
        if ($this->networkPlayerIdsMatch($userId, $createdBy) && is_king_ghost_user($challenge->challenger_id)) {
            $ghostSide = 'challenger';
        } elseif ($joinedBy !== '' && $this->networkPlayerIdsMatch($userId, $joinedBy) && is_king_ghost_user($challenge->opponent_id)) {
            $ghostSide = 'opponent';
        } elseif (is_king_ghost_user($challenge->challenger_id) && ! is_king_ghost_user($challenge->opponent_id)) {
            $ghostSide = 'challenger';
        } elseif ($challenge->opponent_id && is_king_ghost_user($challenge->opponent_id) && ! is_king_ghost_user($challenge->challenger_id)) {
            $ghostSide = 'opponent';
        }

        if (! $ghostSide) {
            KingEventLog::write('in', 'ResultUpdateRequest', 'warning',
                "Could not map result user $userId to a ghost side on table $tableId", $param);

            return;
        }

        $this->storeRemoteProof($challenge, $ghostSide, [
            'image' => $param['image'] ?? null,
            'video' => $param['video'] ?? null,
        ]);

        $this->settlement->applyRemoteResult($challenge, $outcome);

        $label = $outcome === 'cancel' ? 'Cancel' : ucfirst($outcome);
        KingEventLog::write('in', 'ResultUpdateRequest', 'info',
            "Applied remote {$label} on table $tableId from player $userId (challenge #{$challenge->id})", $param);
    }

    private function logResultSyncFromSnapshot(array $data, string $message): void
    {
        $tableId = (string) ($data['id'] ?? '');
        $notes = [];

        foreach (['cHistory' => 'creator', 'jHistory' => 'joiner'] as $key => $label) {
            $outcome = $this->normalizeResult(is_array($data[$key] ?? null) ? ($data[$key]['status'] ?? null) : null);
            if ($outcome) {
                $notes[] = "{$label}={$outcome}";
            }
        }

        $summary = $message !== '' ? $message : 'Result snapshot applied';
        if ($notes !== []) {
            $summary .= ' (' . implode(', ', $notes) . ')';
        }

        KingEventLog::write('in', 'ResultUpdateRequest', 'info', $summary, ['tableId' => $tableId]);
    }

    private function applyResultMediaFromSnapshot(GameChallenge $challenge, array $t): void
    {
        if (is_king_ghost_user($challenge->challenger_id)) {
            $this->storeRemoteProof($challenge, 'challenger', is_array($t['cHistory'] ?? null) ? $t['cHistory'] : []);
        }

        if ($challenge->opponent_id && is_king_ghost_user($challenge->opponent_id)) {
            $this->storeRemoteProof($challenge, 'opponent', is_array($t['jHistory'] ?? null) ? $t['jHistory'] : []);
        }
    }

    /**
     * @param  array{image?: mixed, video?: mixed}  $history
     */
    private function storeRemoteProof(GameChallenge $challenge, string $side, array $history): void
    {
        $image = trim((string) ($history['image'] ?? ''));
        $video = trim((string) ($history['video'] ?? ''));

        if ($image === '' && $video === '') {
            return;
        }

        $screenshotField = $side === 'challenger' ? 'challenger_screenshot' : 'opponent_screenshot';
        $remarkField = $side === 'challenger' ? 'challenger_remark' : 'opponent_remark';
        $changed = false;

        if ($image !== '' && str_starts_with($image, 'http') && ! $challenge->{$screenshotField}) {
            $challenge->{$screenshotField} = $image;
            $changed = true;
        }

        if ($video !== '' && str_starts_with($video, 'http')) {
            $tag = 'DK video: ' . $video;
            $existing = trim((string) ($challenge->{$remarkField} ?? ''));
            if (! str_contains($existing, $video)) {
                $challenge->{$remarkField} = $existing === '' ? $tag : $existing . ' | ' . $tag;
                $changed = true;
            }
        }

        if ($changed) {
            $challenge->saveQuietly();
        }
    }

    private function networkPlayerIdsMatch(string $needle, string $networkId): bool
    {
        $needle = trim($needle);
        $networkId = trim($networkId);

        if ($needle === '' || $networkId === '') {
            return false;
        }

        if ($needle === $networkId) {
            return true;
        }

        if (str_ends_with($networkId, '-' . $needle)) {
            return true;
        }

        $client = $this->ourClientId();
        if ($client !== '' && $networkId === $client . '-' . $needle) {
            return true;
        }

        return false;
    }

    /**
     * Import remote King tables that were mirrored but never linked to a
     * joinable local challenge (e.g. after a bad min/max site setting).
     */
    public function backfillUnlinkedRemoteMirrors(): int
    {
        if (! king_enabled()) {
            return 0;
        }

        $count = 0;

        $mirrors = KingTable::query()
            ->where('origin', 'remote')
            ->whereNull('game_challenge_id')
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->whereNull('joined_by_id')
            ->get();

        foreach ($mirrors as $mirror) {
            try {
                $t = json_decode($mirror->raw ?? '{}', true);
                if (! is_array($t) || ! $this->isKingWaitingStatus($t['status'] ?? $mirror->status)) {
                    continue;
                }

                $challenge = $this->createChallengeFromRemoteTable($mirror, $t);
                if (! $challenge) {
                    continue;
                }

                $mirror->game_challenge_id = $challenge->id;
                $mirror->save();
                $count++;
            } catch (\Throwable $e) {
                Log::error('[King] backfill failed', ['table' => $mirror->king_table_id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    private function isKingWaitingStatus(?string $status): bool
    {
        return strtolower(trim((string) $status)) === 'pending';
    }

    private function isAmountAllowedForImport(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $min = (float) (site_setting()->minimum_game_play_amount ?? 0);
        $max = (float) (site_setting()->maximum_game_play_amount ?? 0);

        // Admin misconfiguration (min > max): enforce max only so imports are not blocked entirely.
        if ($min > 0 && $max > 0 && $min > $max) {
            static $loggedMisconfig = false;
            if (! $loggedMisconfig) {
                $loggedMisconfig = true;
                KingEventLog::write('sys', null, 'warning',
                    "Platform game amount limits misconfigured (min $min > max $max). Using max-only validation for King imports.");
            }

            return $max <= 0 || $amount <= $max;
        }

        if ($min > 0 && $amount < $min) {
            return false;
        }

        if ($max > 0 && $amount > $max) {
            return false;
        }

        return true;
    }
}
