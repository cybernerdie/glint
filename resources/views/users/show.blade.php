@extends('glint::layout')

@section('page-title', $userId)

@section('content')

    <nav class="breadcrumb">
        <a href="{{ route('glint.dashboard') }}" class="breadcrumb-link">Glint</a>
        <span class="breadcrumb-sep">/</span>
        <a href="{{ route('glint.users.index') }}" class="breadcrumb-link">Users</a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current">{{ $userId }}</span>
    </nav>

    <div class="page-header">
        <div class="page-header-row">
            <div>
                <div class="page-title" style="font-family:var(--font-mono);font-size:17px;letter-spacing:-0.2px">
                    {{ $userId }}
                </div>
                <div class="page-desc">User activity and trace history</div>
            </div>
            <form method="GET" action="{{ route('glint.users.show', $userId) }}">
                @include('glint::partials.filters.date-range', [
                    'activePeriod' => $period,
                    'activeFrom'   => $fromDate,
                    'activeTo'     => $toDate,
                ])
            </form>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Traces</div>
            <div class="kpi-value">{{ number_format($stats['trace_count']) }}</div>
            <div class="kpi-footer">@include('glint::partials.period-label', ['period' => $period, 'fromDate' => $fromDate, 'toDate' => $toDate])</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Total Cost</div>
            <div class="kpi-value kpi-value-sm" style="font-family:var(--font-mono);color:var(--accent)">
                ${{ number_format($stats['total_cost'], 4) }}
            </div>
            <div class="kpi-footer">USD</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Avg Duration</div>
            <div class="kpi-value kpi-value-sm">
                {{ number_format($stats['avg_duration']) }}<span class="kpi-unit">ms</span>
            </div>
            <div class="kpi-footer">Per trace</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Total Tokens</div>
            <div class="kpi-value kpi-value-sm">
                {{ number_format($stats['total_tokens']) }}
            </div>
            <div class="kpi-footer">Across all traces</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Errors</div>
            <div class="kpi-value kpi-value-sm"
                 style="color: {{ $stats['error_count'] > 0 ? 'var(--error)' : 'var(--success)' }}">
                {{ number_format($stats['error_count']) }}
            </div>
            <div class="kpi-footer">Failed traces</div>
        </div>
    </div>

    {{-- Traces --}}
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
                    @if($period && $period !== 'today')
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
                        <th>Trace ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($traces as $trace)
                        <tr onclick="window.location.href='{{ route('glint.traces.show', $trace->id) }}'"
>
                            <td class="t-mono t-dim">
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="row-link-dim">
                                    {{ substr($trace->id, 0, 8) }}&hellip;
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="row-link">
                                    {{ $trace->name ?: 'Unnamed' }}
                                </a>
                            </td>
                            <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                            <td class="t-muted t-mono">
                                {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                            </td>
                            <td class="t-muted" style="font-size:12.5px">
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
