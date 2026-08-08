@extends('admin.app')

@section('content')
<div class="content-wrapper">
    <section class="content pt-3">
        <div class="container-fluid">

            {!! session('back_msg') !!}

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">King Sync (Daddy King)</h4>
                <form method="POST" action="{{ url('admin/king-sync/toggle-pause') }}" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $status['paused'] ? 'btn-success' : 'btn-warning' }}">
                        {{ $status['paused'] ? 'Resume Sync' : 'Pause Sync' }}
                    </button>
                </form>
            </div>

            {{-- Status cards --}}
            <div class="row">
                <div class="col-md-3 col-6 mb-2">
                    <div class="card card-body py-2">
                        <small class="text-muted">Connection</small>
                        @if(!$status['enabled'])
                            <span class="badge badge-secondary">Disabled (env)</span>
                        @elseif($status['paused'])
                            <span class="badge badge-warning">Paused by admin</span>
                        @elseif($status['alive'])
                            <span class="badge badge-success">Online (client id: {{ $status['client_id'] ?: '?' }})</span>
                        @elseif(!$status['credentials_ok'])
                            <span class="badge badge-danger">API keys missing</span>
                            <div class="small text-danger mt-1">Set KING_WS_API_KEY &amp; KING_WS_API_SECRET in .env, then run <code>php artisan config:cache</code></div>
                        @elseif($status['last_error'])
                            <span class="badge badge-danger">Daemon error</span>
                            <div class="small text-danger mt-1">{{ $status['last_error'] }}</div>
                        @else
                            <span class="badge badge-danger">Daemon offline</span>
                            <div class="small text-muted mt-1">
                                Run on server once:<br>
                                <code>sudo bash /var/www/html/ludo-shree/scripts/setup-king-supervisor.sh</code><br>
                                Then check: <code>supervisorctl status king-listen</code>
                            </div>
                        @endif
                        @if($status['alive_at'])
                            <div class="small text-muted mt-1">Last heartbeat: {{ date('Y-m-d H:i:s', $status['alive_at']) }}</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card card-body py-2">
                        <small class="text-muted">Active network tables</small>
                        <b>{{ $status['active_tables'] }}</b>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card card-body py-2">
                        <small class="text-muted">Outbox pending / failed</small>
                        <b>{{ $status['pending_outbox'] }} / <span class="{{ $status['failed_outbox'] ? 'text-danger' : '' }}">{{ $status['failed_outbox'] }}</span></b>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card card-body py-2">
                        <small class="text-muted">Warnings / errors (24h)</small>
                        <b class="{{ $status['issues_24h'] ? 'text-danger' : 'text-success' }}">{{ $status['issues_24h'] }}</b>
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item"><a class="nav-link {{ $tab == 'tables' ? 'active' : '' }}" href="{{ url('admin/king-sync?tab=tables') }}">Network Tables</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab == 'outbox' ? 'active' : '' }}" href="{{ url('admin/king-sync?tab=outbox') }}">Outbox</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab == 'logs' ? 'active' : '' }}" href="{{ url('admin/king-sync?tab=logs&level=issues') }}">Issues / Logs</a></li>
            </ul>

            @if($tab == 'tables' && $tables)
            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>King Table</th>
                                <th>Origin</th>
                                <th>Challenge</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Creator</th>
                                <th>Joiner</th>
                                <th>Room</th>
                                <th>Results (C / J)</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tables as $t)
                            <tr>
                                <td>{{ $t->king_table_id }}</td>
                                <td>
                                    <span class="badge {{ $t->origin == 'local' ? 'badge-primary' : 'badge-info' }}">{{ $t->origin == 'local' ? 'Ours' : 'Daddy King' }}</span>
                                </td>
                                <td>
                                    @if($t->game_challenge)
                                        <a href="{{ url('admin/game-challenges') }}" target="_blank">{{ $t->game_challenge->uid }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>₹ {{ number_format((float) $t->amount, 0) }}</td>
                                <td>{{ $t->status }}</td>
                                <td>{{ $t->created_by_name }} <small class="text-muted">({{ $t->created_by_id }})</small></td>
                                <td>
                                    @if($t->joined_by_id)
                                        {{ $t->joined_by_name }} <small class="text-muted">({{ $t->joined_by_id }})</small>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $t->room_code ?: '-' }}</td>
                                <td>{{ $t->creator_result ?: '-' }} / {{ $t->joiner_result ?: '-' }}</td>
                                <td><small>{{ $t->last_seen_at ? $t->last_seen_at->format('d M h:i:s a') : '-' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center py-3">No tables synced yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $tables->links() }}</div>
            </div>
            @endif

            @if($tab == 'outbox' && $outbox)
            <div class="mb-2">
                @foreach(['pending', 'sent', 'success', 'failed', 'skipped'] as $s)
                    <a class="btn btn-sm {{ request()->status == $s ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ url('admin/king-sync?tab=outbox&status=' . $s) }}">{{ ucfirst($s) }}</a>
                @endforeach
                <a class="btn btn-sm {{ !request()->status ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ url('admin/king-sync?tab=outbox') }}">All</a>
            </div>
            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Event</th>
                                <th>King Table</th>
                                <th>Challenge</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Error</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($outbox as $o)
                            <tr>
                                <td>{{ $o->id }}</td>
                                <td><small>{{ $o->event }}</small></td>
                                <td>{{ $o->king_table_id ?: '-' }}</td>
                                <td>{{ $o->game_challenge_id ?: '-' }}</td>
                                <td>
                                    <span class="badge badge-{{ ['pending' => 'warning', 'sent' => 'info', 'success' => 'success', 'failed' => 'danger', 'skipped' => 'secondary'][$o->status] ?? 'secondary' }}">{{ $o->status }}</span>
                                </td>
                                <td>{{ $o->attempts }}</td>
                                <td><small class="text-danger">{{ $o->error }}</small></td>
                                <td><small>{{ $o->created_at }}</small></td>
                                <td>
                                    @if(in_array($o->status, ['failed', 'skipped']) && $o->event != 'KingAcceptRequest')
                                    <form method="POST" action="{{ url('admin/king-sync/retry-outbox') }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $o->id }}">
                                        <button class="btn btn-xs btn-outline-primary">Retry</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center py-3">No outbox messages.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $outbox->links() }}</div>
            </div>
            @endif

            @if($tab == 'logs' && $logs)
            <div class="mb-2">
                <a class="btn btn-sm {{ request()->level == 'issues' ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ url('admin/king-sync?tab=logs&level=issues') }}">Warnings &amp; Errors</a>
                <a class="btn btn-sm {{ request()->level != 'issues' ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ url('admin/king-sync?tab=logs') }}">All</a>
            </div>
            <div class="card">
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Dir</th>
                                <th>URI</th>
                                <th>Level</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $l)
                            <tr>
                                <td><small>{{ $l->created_at }}</small></td>
                                <td><small>{{ $l->direction }}</small></td>
                                <td><small>{{ $l->uri }}</small></td>
                                <td>
                                    <span class="badge badge-{{ ['info' => 'secondary', 'warning' => 'warning', 'error' => 'danger'][$l->level] ?? 'secondary' }}">{{ $l->level }}</span>
                                </td>
                                <td><small>{{ $l->message }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center py-3">No log entries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $logs->links() }}</div>
            </div>
            @endif

        </div>
    </section>
</div>
@endsection
