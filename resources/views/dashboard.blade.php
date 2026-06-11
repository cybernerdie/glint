@extends('glint::layout')

@section('page-title', 'Dashboard')
@section('refresh-interval', 30)

@section('content')

    <div class="page-head">
        <div>
            <div class="page-title">Dashboard</div>
        </div>
        <form method="GET" action="{{ route('glint.dashboard') }}" class="page-toolbar">
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
            <div class="metric-value">{{ number_format($stats['total_traces']) }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Generations</div>
            <div class="metric-value">{{ number_format($stats['total_generations']) }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Total Cost</div>
            <div class="metric-value">${{ number_format($stats['total_cost_usd'], 2) }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Avg Duration</div>
            <div class="metric-value">{{ number_format($stats['avg_duration_ms']) }}<span class="metric-unit">ms</span></div>
        </div>

        <div class="metric">
            <div class="metric-label">Error Rate</div>
            <div class="metric-value">
                <span class="{{ $stats['error_rate'] > 10 ? 'is-bad' : ($stats['error_rate'] > 5 ? 'is-warn' : '') }}">{{ $stats['error_rate'] }}<span class="metric-unit">%</span></span>
            </div>
        </div>
    </div>

    <div class="main-side">

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Request Volume</span>
                <span class="panel-meta">@include('glint::partials.period-label', ['period' => $period, 'fromDate' => $fromDate, 'toDate' => $toDate])</span>
            </div>
            <div class="panel-body" style="display:flex;flex-direction:column">
                @if($volumeBuckets->isEmpty() || $volumeBuckets->every(fn($d) => $d['total'] === 0))
                    <div style="text-align:center;padding:48px 0;color:var(--text-3);font-size:13px">
                        No requests recorded {{ strtolower(trim(view('glint::partials.period-label', ['period' => $period, 'fromDate' => $fromDate, 'toDate' => $toDate])->render())) }}.
                    </div>
                @else
                    <div style="position:relative;flex:1;min-height:220px">
                        <canvas id="requestVolumeChart"></canvas>
                    </div>
                    <script>
                    (function () {
                        const ctx = document.getElementById('requestVolumeChart');
                        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
                        gradient.addColorStop(0, 'rgba(237, 94, 43, 0.95)');
                        gradient.addColorStop(1, 'rgba(237, 94, 43, 0.55)');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: @json($volumeBuckets->pluck('label')),
                                datasets: [{
                                    data: @json($volumeBuckets->pluck('total')),
                                    backgroundColor: gradient,
                                    hoverBackgroundColor: '#FF8A57',
                                    borderWidth: 0,
                                    borderRadius: 3,
                                    borderSkipped: false,
                                    maxBarThickness: 22,
                                    categoryPercentage: 0.72,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: ctx => ctx.parsed.y.toLocaleString() + ' requests'
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { display: false },
                                        border: { display: false },
                                        ticks: { maxRotation: 0 }
                                    },
                                    y: {
                                        grid: { color: 'rgba(255,255,255,0.04)' },
                                        border: { display: false, dash: [3, 3] },
                                        ticks: {
                                            maxTicksLimit: 5,
                                            padding: 8,
                                            callback: val => val >= 1000 ? (val/1000).toFixed(1) + 'k' : val
                                        }
                                    }
                                }
                            }
                        });
                    })();
                    </script>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Model Costs</span>
                <a href="{{ route('glint.costs.index') }}" class="panel-link">Costs <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg></a>
            </div>
            @if($topModelCosts->isEmpty())
                <div class="empty" style="padding:32px 24px">
                    <div class="empty-title">No cost data yet</div>
                    <div class="empty-sub">Cost data will appear once LLM calls with pricing are recorded.</div>
                </div>
            @else
                @php $maxModelCost = max(0.000001, (float) $topModelCosts->max('total_cost')); @endphp
                <div class="listbar" style="flex:1">
                    @foreach($topModelCosts as $i => $row)
                        <div class="listbar-row">
                            <div class="listbar-main">
                                <div class="listbar-name">
                                    {{ $row->model }}
                                    <span class="listbar-sub">{{ $row->provider }}</span>
                                </div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((float) $row->total_cost / $maxModelCost) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="listbar-val">
                                <strong>${{ number_format((float) $row->total_cost, 4) }}</strong><br>
                                <span style="font-size:10.5px;color:var(--text-3)">{{ $row->total_tokens !== null ? number_format((float) $row->total_tokens).' tok' : '—' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 20px;border-top:1px solid var(--border);background:var(--surface-2)">
                    <span style="font-size:10.5px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:0.08em">Total</span>
                    <span class="t-mono" style="font-size:12.5px;font-weight:500;color:var(--text-1)">${{ number_format($stats['total_cost_usd'], 4) }}</span>
                </div>
            @endif
        </div>

    </div>

    <div class="two-col">

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Traces by Use Case</span>
                <a href="{{ route('glint.traces.index') }}" class="panel-link">Traces <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg></a>
            </div>
            @if($topTraceNames->isEmpty())
                <div class="empty" style="padding:32px 24px">
                    <div class="empty-title">No named traces yet</div>
                    <div class="empty-sub">Name your traces to see them grouped here.</div>
                </div>
            @else
                @php $maxTraceCount = max(1, (int) $topTraceNames->max('trace_count')); @endphp
                <div class="listbar">
                    @foreach($topTraceNames as $i => $row)
                        <a class="listbar-row" href="{{ route('glint.traces.index', ['search' => $row->name]) }}">
                            <span class="listbar-rank">{{ $i + 1 }}</span>
                            <div class="listbar-main">
                                <div class="listbar-name">{{ $row->name }}</div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((int) $row->trace_count / $maxTraceCount) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="listbar-val"><strong>{{ number_format($row->trace_count) }}</strong></div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">User Consumption</span>
                <a href="{{ route('glint.users.index') }}" class="panel-link">Users <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg></a>
            </div>
            @if($topUserCosts->isEmpty())
                <div class="empty" style="padding:32px 24px">
                    <div class="empty-title">No user data yet</div>
                    <div class="empty-sub">Pass a <code style="font-family:var(--font-mono);font-size:11px">user_id</code> when recording traces to track per-user consumption.</div>
                </div>
            @else
                @php $maxUserCost = max(0.000001, (float) $topUserCosts->max('total_cost')); @endphp
                <div class="listbar">
                    @foreach($topUserCosts as $i => $row)
                        <a class="listbar-row" href="{{ route('glint.users.show', $row->user_id) }}">
                            <span class="listbar-rank">{{ $i + 1 }}</span>
                            <div class="listbar-main">
                                <div class="listbar-name">{{ $row->user_id }}</div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((float) $row->total_cost / $maxUserCost) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="listbar-val"><strong>${{ number_format((float) $row->total_cost, 4) }}</strong></div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Recent Traces</span>
            <a href="{{ route('glint.traces.index') }}" class="panel-link">Traces <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg></a>
        </div>

        @if($recentTraces->isEmpty())
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
                <div class="empty-title">No traces yet</div>
                <div class="empty-sub">Start making LLM calls to see trace data here.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>User</th>
                        <th class="num">Duration</th>
                        <th class="num">Started</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTraces as $trace)
                        <tr onclick="if (!event.target.closest('a')) glintVisit('{{ route('glint.traces.show', $trace->id) }}')">
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="row-link">
                                    {{ $trace->name ?: 'Unnamed' }}
                                </a>
                            </td>
                            <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                            <td class="t-muted t-mono">{{ $trace->user_id ?? '—' }}</td>
                            <td class="t-muted t-mono num">
                                {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                            </td>
                            <td class="t-dim num" style="font-size:12px">
                                {{ $trace->started_at?->diffForHumans(short: true) ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

@endsection
