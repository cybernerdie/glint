@extends('glint::layout')

@section('page-title', 'Dashboard')

@section('content')

    <div class="page-header">
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">LLM activity overview</div>
    </div>

    {{-- Stats cards --}}
    <div class="stats-grid">
        <div class="card">
            <div class="card-title">Total Traces</div>
            <div class="card-value">{{ number_format($stats['total_traces']) }}</div>
            <div class="card-sub">All time</div>
        </div>

        <div class="card">
            <div class="card-title">Total Generations</div>
            <div class="card-value">{{ number_format($stats['total_generations']) }}</div>
            <div class="card-sub">All time</div>
        </div>

        <div class="card">
            <div class="card-title">Total Cost</div>
            <div class="card-value card-value-sm">${{ number_format($stats['total_cost_usd'], 4) }}</div>
            <div class="card-sub">USD, all time</div>
        </div>

        <div class="card">
            <div class="card-title">Avg Duration</div>
            <div class="card-value card-value-sm">{{ number_format($stats['avg_duration_ms']) }}<span style="font-size:13px;font-weight:400;color:var(--muted)">ms</span></div>
            <div class="card-sub">Per generation</div>
        </div>

        <div class="card">
            <div class="card-title">Error Rate</div>
            <div class="card-value card-value-sm"
                 style="color: {{ $stats['error_rate'] > 10 ? 'var(--error)' : ($stats['error_rate'] > 5 ? 'var(--warning)' : 'var(--success)') }}">
                {{ $stats['error_rate'] }}<span style="font-size:13px;font-weight:400;color:var(--muted)">%</span>
            </div>
            <div class="card-sub">Of all generations</div>
        </div>
    </div>

    <div class="dashboard-layout">

        {{-- Recent traces --}}
        <div class="table-wrap">
            <div class="table-header">
                <span class="table-title">Recent Traces</span>
                <a href="{{ route('glint.traces.index') }}" class="btn btn-ghost" style="font-size:12px;padding:5px 10px">View all</a>
            </div>

            @if($recentTraces->isEmpty())
                <div class="empty-state">
                    <div class="empty-state-icon">&#128270;</div>
                    <div class="empty-state-text">No traces recorded yet.</div>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Started At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTraces as $trace)
                            <tr>
                                <td>
                                    <a href="{{ route('glint.traces.show', $trace->id) }}" class="link">
                                        {{ $trace->name ?: 'Unnamed' }}
                                    </a>
                                </td>
                                <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                                <td class="td-muted">
                                    {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                                </td>
                                <td class="td-muted">
                                    {{ $trace->started_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Cost by provider --}}
        <div class="card">
            <div class="card-title" style="margin-bottom:14px">Cost by Provider</div>

            @if($costByProvider->isEmpty())
                <div style="text-align:center;padding:24px 0;color:var(--muted);font-size:13px">
                    No cost data yet.
                </div>
            @else
                @php
                    $maxCost = $costByProvider->max('total_cost') ?: 1;
                @endphp
                <div class="bar-chart">
                    @foreach($costByProvider as $row)
                        <div class="bar-row">
                            <div class="bar-label" title="{{ $row->provider }}">{{ $row->provider }}</div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:{{ min(100, ($row->total_cost / $maxCost) * 100) }}%"></div>
                            </div>
                            <div class="bar-value">${{ number_format($row->total_cost, 4) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

@endsection
