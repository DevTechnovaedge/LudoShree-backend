<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\King\KingEventLog;
use App\Models\King\KingOutbox;
use App\Models\King\KingTable;
use Illuminate\Support\Facades\Cache;

/**
 * Monitor + control panel for the King (Daddy King) WebSocket sync.
 */
class KingController extends Controller
{
    public function index()
    {
        $this->authorize('admin');

        $tab = in_array(request()->tab, ['tables', 'outbox', 'logs'], true) ? request()->tab : 'tables';

        $status = [
            'enabled' => (bool) config('king.enabled'),
            'paused' => (bool) Cache::get('king:paused', false),
            'alive' => king_ws_alive(),
            'alive_at' => (int) Cache::get('king:alive_at', 0),
            'last_error' => (string) Cache::get('king:daemon_last_error', ''),
            'credentials_ok' => trim((string) config('king.api_key')) !== ''
                && trim((string) config('king.api_secret')) !== '',
            'client_id' => (string) Cache::get('king:client_id', config('king.client_id', '')),
            'ws_url' => (string) config('king.ws_url'),
            'active_tables' => KingTable::whereIn('status', ['Pending', 'Start', 'View'])->count(),
            'pending_outbox' => KingOutbox::whereIn('status', ['pending', 'sent'])->count(),
            'failed_outbox' => KingOutbox::where('status', 'failed')->count(),
            'issues_24h' => KingEventLog::whereIn('level', ['warning', 'error'])->where('created_at', '>=', now()->subDay())->count(),
        ];

        $tables = null;
        $outbox = null;
        $logs = null;

        if ($tab === 'tables') {
            $tables = KingTable::query()->with('game_challenge:id,uid,status')->latest('id')->paginate(30)->withQueryString();
        } elseif ($tab === 'outbox') {
            $query = KingOutbox::query()->latest('id');
            if (in_array(request()->status, ['pending', 'sent', 'success', 'failed', 'skipped'], true)) {
                $query->where('status', request()->status);
            }
            $outbox = $query->paginate(30)->withQueryString();
        } else {
            $query = KingEventLog::query()->latest('id');
            if (request()->level === 'issues') {
                $query->whereIn('level', ['warning', 'error']);
            }
            $logs = $query->paginate(50)->withQueryString();
        }

        return view('admin.pages.king.index', compact('tab', 'status', 'tables', 'outbox', 'logs'));
    }

    public function retryOutbox()
    {
        $this->authorize('admin');

        $row = KingOutbox::find((int) request()->id);

        if (! $row || ! in_array($row->status, [KingOutbox::STATUS_FAILED, KingOutbox::STATUS_SKIPPED], true)) {
            return back()->with('back_msg', "<div class='alert alert-danger'>Message not found or not retryable.</div>");
        }

        // Accept retries are unsafe (the original app request is long gone).
        if ($row->event === 'KingAcceptRequest') {
            return back()->with('back_msg', "<div class='alert alert-danger'>Accept requests cannot be retried. The user must join again from the app.</div>");
        }

        $row->status = KingOutbox::STATUS_PENDING;
        $row->error = null;
        $row->available_at = null;
        $row->save();

        return back()->with('back_msg', "<div class='alert alert-success'>Message queued for retry.</div>");
    }

    public function togglePause()
    {
        $this->authorize('admin');

        $paused = (bool) Cache::get('king:paused', false);
        Cache::forever('king:paused', ! $paused);

        KingEventLog::write('sys', null, 'warning', 'King sync ' . ($paused ? 'RESUMED' : 'PAUSED') . ' by admin ' . (auth('admin')->user()->name ?? ''));

        return back()->with('back_msg', "<div class='alert alert-success'>King sync " . ($paused ? 'resumed' : 'paused') . '.</div>');
    }
}
