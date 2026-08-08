@extends('admin.app')

@section('style')
<style>
    .king-sync-page .info-box {
        min-height: 88px;
        margin-bottom: 1rem;
    }

    .king-sync-page .info-box-icon {
        width: 72px;
        font-size: 1.6rem;
    }

    .king-sync-page .info-box-content {
        padding: 8px 10px;
    }

    .king-sync-page .info-box-number {
        font-size: 1.35rem;
        font-weight: 700;
    }

    .king-sync-page .nav-tabs .nav-link {
        font-weight: 600;
        color: #495057;
    }

    .king-sync-page .nav-tabs .nav-link.active {
        color: #fff;
        background: var(--theme-color, #007bff);
        border-color: var(--theme-color, #007bff);
    }

    .king-sync-page .king-table thead th {
        background: #f4f6f9;
        white-space: nowrap;
        vertical-align: middle;
        font-size: 13px;
    }

    .king-sync-page .king-table tbody td {
        vertical-align: middle;
        font-size: 13px;
    }

    .king-sync-page .king-table .player-cell {
        max-width: 160px;
    }

    .king-sync-page .king-table .player-cell .name {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .king-sync-page .connection-detail {
        font-size: 12px;
        line-height: 1.45;
        margin-top: 6px;
    }

    .king-sync-page .empty-state {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #6c757d;
    }

    .king-sync-page .empty-state i {
        font-size: 2rem;
        margin-bottom: .75rem;
        opacity: .45;
    }

    .king-sync-page .filter-pills .btn {
        margin: 0 .25rem .5rem 0;
    }

    .king-sync-page .card-footer .pagination {
        margin-bottom: 0;
        justify-content: flex-end;
    }
</style>
@endsection

@section('content')
<section class="content king-sync-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card mt-5">
                    <div class="card-header bg-theme">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="m-0">
                                    <i class="fas fa-network-wired mr-2"></i>King Sync (Daddy King)
                                </h5>
                                <small class="d-block mt-1 opacity-75">{{ $status['ws_url'] }}</small>
                            </div>
                            <div class="col-md-4 text-md-right mt-2 mt-md-0">
                                <form method="POST" action="{{ url('admin/king-sync/toggle-pause') }}" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $status['paused'] ? 'btn-success' : 'btn-warning' }}">
                                        <i class="fas {{ $status['paused'] ? 'fa-play' : 'fa-pause' }} mr-1"></i>
                                        {{ $status['paused'] ? 'Resume Sync' : 'Pause Sync' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        @if(session('back_msg'))
                            {!! session('back_msg') !!}
                        @endif

                        {{-- Summary boxes --}}
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="info-box shadow-sm">
                                    <span class="info-box-icon {{ $status['alive'] && !$status['paused'] && $status['enabled'] ? 'bg-success' : 'bg-secondary' }}">
                                        <i class="fas fa-plug"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Connection</span>
                                        <span class="info-box-number" style="font-size:1rem;">
                                            @if(!$status['enabled'])
                                                Disabled
                                            @elseif($status['paused'])
                                                Paused
                                            @elseif($status['alive'])
                                                Online
                                            @elseif(!$status['credentials_ok'])
                                                Keys Missing
                                            @elseif($status['last_error'])
                                                Error
                                            @else
                                                Offline
                                            @endif
                                        </span>
                                        @if($status['alive'] && $status['client_id'])
                                            <small class="text-muted">Client ID: {{ $status['client_id'] }}</small>
                                        @endif
                                        @if($status['alive_at'])
                                            <small class="text-muted d-block">Heartbeat: {{ date('d M, h:i A', $status['alive_at']) }}</small>
                                        @endif
                                        @if($status['last_error'])
                                            <small class="text-danger d-block connection-detail">{{ \Illuminate\Support\Str::limit($status['last_error'], 80) }}</small>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="info-box shadow-sm">
                                    <span class="info-box-icon bg-info"><i class="fas fa-table"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Active Tables</span>
                                        <span class="info-box-number">{{ $status['active_tables'] }}</span>
                                        <small class="text-muted">Pending / Start / View</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="info-box shadow-sm">
                                    <span class="info-box-icon {{ $status['failed_outbox'] ? 'bg-danger' : 'bg-warning' }}">
                                        <i class="fas fa-paper-plane"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Outbox Queue</span>
                                        <span class="info-box-number">{{ $status['pending_outbox'] }} <small class="text-muted">/ {{ $status['failed_outbox'] }} fail</small></span>
                                        <small class="text-muted">Pending / Failed</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="info-box shadow-sm">
                                    <span class="info-box-icon {{ $status['issues_24h'] ? 'bg-danger' : 'bg-success' }}">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Issues (24h)</span>
                                        <span class="info-box-number">{{ $status['issues_24h'] }}</span>
                                        <small class="text-muted">Warnings &amp; errors</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabs --}}
                        <ul class="nav nav-tabs mb-3">
                            <li class="nav-item">
                                <a class="nav-link {{ $tab == 'tables' ? 'active' : '' }}" href="{{ url('admin/king-sync?tab=tables') }}">
                                    <i class="fas fa-table mr-1"></i> Network Tables
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $tab == 'outbox' ? 'active' : '' }}" href="{{ url('admin/king-sync?tab=outbox') }}">
                                    <i class="fas fa-inbox mr-1"></i> Outbox
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $tab == 'logs' ? 'active' : '' }}" href="{{ url('admin/king-sync?tab=logs&level=issues') }}">
                                    <i class="fas fa-clipboard-list mr-1"></i> Issues / Logs
                                </a>
                            </li>
                        </ul>

                        @php
                            $tableStatusClass = [
                                'Pending' => 'warning',
                                'Start' => 'primary',
                                'View' => 'info',
                                'Completed' => 'success',
                                'Deleted' => 'secondary',
                                'Missing' => 'dark',
                            ];
                        @endphp

                        @if($tab == 'tables' && $tables)
                            <div class="card card-outline card-primary mb-0">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Network Tables Mirror</h3>
                                    <div class="card-tools">
                                        <span class="badge badge-light">{{ $tables->total() }} total</span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover king-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>King Table ID</th>
                                                    <th>Origin</th>
                                                    <th>Local Challenge</th>
                                                    <th class="text-right">Amount</th>
                                                    <th>Status</th>
                                                    <th>Creator</th>
                                                    <th>Joiner</th>
                                                    <th>Room</th>
                                                    <th>Results</th>
                                                    <th>Last Seen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($tables as $i => $t)
                                                    <tr>
                                                        <td class="text-muted">{{ $tables->firstItem() + $i }}</td>
                                                        <td><code>{{ $t->king_table_id }}</code></td>
                                                        <td>
                                                            @if($t->origin == 'local')
                                                                <span class="badge badge-primary"><i class="fas fa-home mr-1"></i>Ours</span>
                                                            @else
                                                                <span class="badge badge-info"><i class="fas fa-globe mr-1"></i>Daddy King</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($t->game_challenge)
                                                                <a href="{{ url('admin/game-challenges') }}" target="_blank" class="font-weight-bold">
                                                                    {{ $t->game_challenge->uid }}
                                                                </a>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-right font-weight-bold">₹{{ number_format((float) $t->amount, 0) }}</td>
                                                        <td>
                                                            <span class="badge badge-{{ $tableStatusClass[$t->status] ?? 'secondary' }}">{{ $t->status }}</span>
                                                        </td>
                                                        <td class="player-cell">
                                                            <span class="name" title="{{ $t->created_by_name }}">{{ $t->created_by_name ?: '—' }}</span>
                                                            @if($t->created_by_id)
                                                                <small class="text-muted">{{ $t->created_by_id }}</small>
                                                            @endif
                                                        </td>
                                                        <td class="player-cell">
                                                            @if($t->joined_by_id)
                                                                <span class="name" title="{{ $t->joined_by_name }}">{{ $t->joined_by_name ?: '—' }}</span>
                                                                <small class="text-muted">{{ $t->joined_by_id }}</small>
                                                            @else
                                                                <span class="text-muted">Waiting…</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($t->room_code)
                                                                <span class="badge badge-light border">{{ $t->room_code }}</span>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <span class="text-muted">C:</span> {{ $t->creator_result ?: '—' }}
                                                                <span class="mx-1">|</span>
                                                                <span class="text-muted">J:</span> {{ $t->joiner_result ?: '—' }}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <small>{{ $t->last_seen_at ? $t->last_seen_at->format('d M, h:i A') : '—' }}</small>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="11">
                                                            <div class="empty-state">
                                                                <i class="fas fa-table d-block"></i>
                                                                <div>No network tables synced yet.</div>
                                                                <small>Tables will appear here once the daemon connects and receives data from Daddy King.</small>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @if($tables->hasPages())
                                    <div class="card-footer clearfix">
                                        {{ $tables->onEachSide(1)->links('pagination::bootstrap-4') }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($tab == 'outbox' && $outbox)
                            <div class="filter-pills mb-3">
                                @foreach(['pending', 'sent', 'success', 'failed', 'skipped'] as $s)
                                    <a class="btn btn-sm {{ request()->status == $s ? 'btn-dark' : 'btn-outline-secondary' }}" href="{{ url('admin/king-sync?tab=outbox&status=' . $s) }}">{{ ucfirst($s) }}</a>
                                @endforeach
                                <a class="btn btn-sm {{ !request()->status ? 'btn-dark' : 'btn-outline-secondary' }}" href="{{ url('admin/king-sync?tab=outbox') }}">All</a>
                            </div>
                            <div class="card card-outline card-secondary mb-0">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Outbound Message Queue</h3>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover king-table mb-0">
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
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($outbox as $o)
                                                    <tr>
                                                        <td>{{ $o->id }}</td>
                                                        <td><code>{{ $o->event }}</code></td>
                                                        <td>{{ $o->king_table_id ?: '—' }}</td>
                                                        <td>{{ $o->game_challenge_id ?: '—' }}</td>
                                                        <td>
                                                            <span class="badge badge-{{ ['pending' => 'warning', 'sent' => 'info', 'success' => 'success', 'failed' => 'danger', 'skipped' => 'secondary'][$o->status] ?? 'secondary' }}">{{ $o->status }}</span>
                                                        </td>
                                                        <td>{{ $o->attempts }}</td>
                                                        <td><small class="text-danger">{{ \Illuminate\Support\Str::limit($o->error, 60) }}</small></td>
                                                        <td><small>{{ $o->created_at?->format('d M, h:i A') }}</small></td>
                                                        <td class="text-center">
                                                            @if(in_array($o->status, ['failed', 'skipped']) && $o->event != 'KingAcceptRequest')
                                                                <form method="POST" action="{{ url('admin/king-sync/retry-outbox') }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="id" value="{{ $o->id }}">
                                                                    <button class="btn btn-xs btn-outline-primary"><i class="fas fa-redo"></i> Retry</button>
                                                                </form>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="9">
                                                            <div class="empty-state">
                                                                <i class="fas fa-inbox d-block"></i>
                                                                <div>No outbox messages.</div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @if($outbox->hasPages())
                                    <div class="card-footer clearfix">
                                        {{ $outbox->onEachSide(1)->links('pagination::bootstrap-4') }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($tab == 'logs' && $logs)
                            <div class="filter-pills mb-3">
                                <a class="btn btn-sm {{ request()->level == 'issues' ? 'btn-dark' : 'btn-outline-secondary' }}" href="{{ url('admin/king-sync?tab=logs&level=issues') }}">Warnings &amp; Errors</a>
                                <a class="btn btn-sm {{ request()->level != 'issues' ? 'btn-dark' : 'btn-outline-secondary' }}" href="{{ url('admin/king-sync?tab=logs') }}">All Logs</a>
                            </div>
                            <div class="card card-outline card-danger mb-0">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Sync Event Log</h3>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover king-table mb-0">
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
                                                        <td><small>{{ $l->created_at?->format('d M, h:i:s A') }}</small></td>
                                                        <td><span class="badge badge-light">{{ strtoupper($l->direction) }}</span></td>
                                                        <td><code>{{ $l->uri }}</code></td>
                                                        <td>
                                                            <span class="badge badge-{{ ['info' => 'secondary', 'warning' => 'warning', 'error' => 'danger'][$l->level] ?? 'secondary' }}">{{ $l->level }}</span>
                                                        </td>
                                                        <td>{{ $l->message }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5">
                                                            <div class="empty-state">
                                                                <i class="fas fa-clipboard-list d-block"></i>
                                                                <div>No log entries.</div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @if($logs->hasPages())
                                    <div class="card-footer clearfix">
                                        {{ $logs->onEachSide(1)->links('pagination::bootstrap-4') }}
                                    </div>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
