@extends('glint::layout')

@section('page-title', $userId)

@section('breadcrumb')
    <span class="topbar-sep">/</span>
    <a href="{{ route('glint.users.index') }}" class="breadcrumb-link">Users</a>
    <span class="topbar-sep">/</span>
    <span class="breadcrumb-current">{{ $userId }}</span>
@endsection

@section('content')

    <div class="page-head">
        <div>
            <div class="page-title" style="font-family:var(--font-mono);font-size:19px">{{ $userId }}</div>
        </div>
        <form method="GET" action="{{ route('glint.users.show', $userId) }}" class="page-toolbar">
            @include('glint::partials.filters.date-range', [
                'activePeriod' => $period,
                'activeFrom'   => $fromDate,
                'activeTo'     => $toDate,
            ])
        </form>
    </div>

    <div class="metric-strip">
        <div class="metric">
            <div class="metric-label">Traces</div>
            <div class="metric-value">{{ number_format($stats['trace_count']) }}</div>
            <div class="metric-foot">@include('glint::partials.period-label', ['period' => $period, 'fromDate' => $fromDate, 'toDate' => $toDate])</div>
        </div>

        <div class="metric">
            <div class="metric-label">Total Cost</div>
            <div class="metric-value">${{ number_format($stats['total_cost'], 2) }}</div>
            <div class="metric-foot"><span class="t-mono" style="font-size:11px">${{ number_format($stats['total_cost'], 4) }}</span> USD</div>
        </div>

        <div class="metric">
            <div class="metric-label">Avg Duration</div>
            <div class="metric-value">{{ number_format($stats['avg_duration']) }}<span class="metric-unit">ms</span></div>
            <div class="metric-foot">Per trace</div>
        </div>

        <div class="metric">
            <div class="metric-label">Tokens</div>
            <div class="metric-value">{{ number_format($stats['total_tokens']) }}</div>
            <div class="metric-foot">Across all traces</div>
        </div>

        <div class="metric">
            <div class="metric-label">Errors</div>
            <div class="metric-value">
                <span class="{{ $stats['error_count'] > 0 ? 'is-bad' : '' }}">{{ number_format($stats['error_count']) }}</span>
            </div>
            <div class="metric-foot">Failed traces</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Traces</span>
        </div>

        @php $isEmpty = method_exists($traces, 'isEmpty') ? $traces->isEmpty() : count($traces) === 0; @endphp

        @if($isEmpty)
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
                <div class="empty-title">No traces found</div>
                <div class="empty-sub">
                    @if($period && $period !== '24h')
                        Try a different date range.
                    @else
                        This user has no recorded traces yet.
                    @endif
                </div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Trace</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th class="num">Duration</th>
                        <th class="num">Started</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($traces as $trace)
                        <tr onclick="if (!event.target.closest('a')) glintVisit('{{ route('glint.traces.show', $trace->id) }}')">
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="id-chip">
                                    {{ substr($trace->id, 0, 8) }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="row-link">
                                    {{ $trace->name ?: 'Unnamed' }}
                                </a>
                            </td>
                            <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                            <td class="t-muted t-mono num">
                                {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                            </td>
                            <td class="t-dim t-mono num" style="font-size:11.5px">
                                {{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($traces, 'links'))
                <div class="pagination">
                    {{ $traces->links('glint::pagination') }}
                </div>
            @endif
        @endif
    </div>

@endsection
