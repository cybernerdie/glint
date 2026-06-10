@extends('glint::layout')

@section('page-title', 'Dashboard')
@section('refresh-interval', 30)

@section('content')

    <div class="page-header">
        <div class="page-header-row">
            <div>
                <div class="page-title">Dashboard</div>
                <div class="page-desc">Overview of your LLM activity</div>
            </div>
            <form method="GET" action="{{ route('glint.dashboard') }}">
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
            <div class="kpi-value">{{ number_format($stats['total_traces']) }}</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Total Generations</div>
            <div class="kpi-value">{{ number_format($stats['total_generations']) }}</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Total Cost</div>
            <div class="kpi-value kpi-value-sm" style="font-family:var(--font-mono);color:var(--accent)">
                ${{ number_format($stats['total_cost_usd'], 4) }}
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Avg Duration</div>
            <div class="kpi-value kpi-value-sm">
                {{ number_format($stats['avg_duration_ms']) }}<span class="kpi-unit">ms</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Error Rate</div>
            <div class="kpi-value kpi-value-sm"
                 style="color: {{ $stats['error_rate'] > 10 ? 'var(--error)' : ($stats['error_rate'] > 5 ? 'var(--warning)' : 'var(--success)') }}">
                {{ $stats['error_rate'] }}<span class="kpi-unit">%</span>
            </div>
        </div>
    </div>

    {{-- Request volume + Model costs side by side --}}
    <div class="two-col">

        {{-- Activity chart --}}
        <div class="panel" style="margin-bottom:0">
            <div class="panel-header">
                <span class="panel-title">Request Volume</span>
            </div>
            <div class="panel-body" style="display:flex;flex-direction:column">
                @php
                    $chartDays = collect();
                    if ($period === 'today' && $dailyVolume->isNotEmpty() && $dailyVolume->first()->period_at !== null) {
                        // Hourly buckets — group by hour in PHP (DB-agnostic)
                        $hourlyTotals = $dailyVolume
                            ->groupBy(fn ($r) => (int) $r->period_at->format('G'))
                            ->map(fn ($rows) => $rows->sum('total_requests'));
                        $currentHour = now()->hour;
                        for ($h = 0; $h <= $currentHour; $h++) {
                            $chartDays->push([
                                'label' => $h % 4 === 0 ? now()->startOfDay()->addHours($h)->format('ga') : '',
                                'total' => (int) ($hourlyTotals->get($h) ?? 0),
                            ]);
                        }
                    } else {
                        $dayCount = match($period) {
                            'week'    => 7,
                            'month'   => 30,
                            '3months' => 90,
                            default   => 14,
                        };
                        for ($i = $dayCount - 1; $i >= 0; $i--) {
                            $d = now()->subDays($i)->format('Y-m-d');
                            $row = $dailyVolume->first(fn ($r) => (isset($r->date) ? $r->date : data_get($r, 'date')) === $d);
                            $chartDays->push([
                                'label' => $dayCount <= 14
                                    ? now()->subDays($i)->format('M j')
                                    : ($i % 7 === 0 ? now()->subDays($i)->format('M j') : ''),
                                'total' => $row ? (int) $row->total : 0,
                            ]);
                        }
                    }
                    $maxTotal = max(1, $chartDays->max('total'));
                @endphp

                @if($chartDays->every(fn($d) => $d['total'] === 0))
                    <div style="text-align:center;padding:40px 0;color:var(--text-3);font-size:13px">
                        No activity in this period.
                    </div>
                @else
                    <div style="position:relative;flex:1;min-height:180px">
                        <canvas id="requestVolumeChart"></canvas>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        new Chart(document.getElementById('requestVolumeChart'), {
                            type: 'bar',
                            data: {
                                labels: @json($chartDays->pluck('label')),
                                datasets: [{
                                    data: @json($chartDays->pluck('total')),
                                    backgroundColor: 'rgba(232, 81, 10, 0.75)',
                                    borderWidth: 0,
                                    borderRadius: 4,
                                    borderSkipped: false,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        displayColors: false,
                                        callbacks: {
                                            label: ctx => ctx.parsed.y.toLocaleString() + ' requests'
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { display: false },
                                        border: { display: false },
                                        ticks: { color: '#9898AA', font: { size: 11, family: 'Inter' } }
                                    },
                                    y: {
                                        grid: { color: 'rgba(0,0,0,0.05)' },
                                        border: { display: false },
                                        ticks: {
                                            color: '#9898AA',
                                            font: { size: 11, family: 'Inter' },
                                            maxTicksLimit: 5,
                                            callback: val => val >= 1000 ? (val/1000).toFixed(1) + 'k' : val
                                        }
                                    }
                                }
                            }
                        });
                    });
                    </script>
                @endif
            </div>
        </div>

        {{-- Model costs mini-table --}}
        <div class="panel" style="margin-bottom:0">
            <div class="panel-header">
                <span class="panel-title">Model Costs</span>
                <a href="{{ route('glint.costs.index') }}" class="text-link">View all &rarr;</a>
            </div>
            @if($topModelCosts->isEmpty())
                <div class="empty" style="padding:32px 24px">
                    <div class="empty-title">No cost data yet</div>
                    <div class="empty-sub">Cost data will appear once LLM calls with pricing are recorded.</div>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Tokens</th>
                            <th>Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topModelCosts as $row)
                            <tr>
                                <td>
                                    <div class="t-mono" style="font-size:12px;font-weight:500">{{ $row->model }}</div>
                                    <div style="font-size:11px;color:var(--text-3);margin-top:1px">{{ $row->provider }}</div>
                                </td>
                                <td class="t-muted t-mono">
                                    {{ $row->total_tokens !== null ? number_format((float) $row->total_tokens) : '—' }}
                                </td>
                                <td class="t-mono" style="font-weight:600;color:var(--accent)">
                                    ${{ number_format((float) $row->total_cost, 4) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align:right;color:var(--text-3);font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase">Total</td>
                            <td class="t-mono" style="font-weight:700;color:var(--accent)">${{ number_format($stats['total_cost_usd'], 4) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>

    </div>

    {{-- Traces by use case + Top users side by side --}}
    <div class="two-col">

        {{-- Top trace names --}}
        <div class="panel" style="margin-bottom:0">
            <div class="panel-header">
                <span class="panel-title">Traces by Use Case</span>
                <a href="{{ route('glint.traces.index') }}" class="text-link">View all &rarr;</a>
            </div>
            @if($topTraceNames->isEmpty())
                <div class="empty" style="padding:32px 24px">
                    <div class="empty-title">No named traces yet</div>
                    <div class="empty-sub">Name your traces to see them grouped here.</div>
                </div>
            @else
                <div class="panel-body" style="display:flex;flex-direction:column">
                    <div style="position:relative;flex:1;min-height:220px">
                        <canvas id="traceUseCaseChart"></canvas>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        new Chart(document.getElementById('traceUseCaseChart'), {
                            type: 'bar',
                            data: {
                                labels: @json($topTraceNames->pluck('name')),
                                datasets: [{
                                    data: @json($topTraceNames->pluck('trace_count')),
                                    backgroundColor: 'rgba(232, 81, 10, 0.75)',
                                    borderWidth: 0,
                                    borderRadius: 3,
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        displayColors: false,
                                        callbacks: { label: ctx => ctx.parsed.x.toLocaleString() + ' traces' }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { color: 'rgba(0,0,0,0.05)' },
                                        border: { display: false },
                                        ticks: { color: '#9898AA', font: { size: 11, family: 'Inter' }, maxTicksLimit: 5 }
                                    },
                                    y: {
                                        grid: { display: false },
                                        border: { display: false },
                                        ticks: { color: '#3C3C4A', font: { size: 11, family: 'Inter' } }
                                    }
                                }
                            }
                        });
                    });
                    </script>
                </div>
            @endif
        </div>

        {{-- Top users by cost --}}
        <div class="panel" style="margin-bottom:0">
            <div class="panel-header">
                <span class="panel-title">User Consumption</span>
                <a href="{{ route('glint.users.index') }}" class="text-link">View all &rarr;</a>
            </div>
            @if($topUserCosts->isEmpty())
                <div class="empty" style="padding:32px 24px">
                    <div class="empty-title">No user data yet</div>
                    <div class="empty-sub">Pass a <code style="font-family:var(--font-mono);font-size:11px">user_id</code> when recording traces to track per-user consumption.</div>
                </div>
            @else
                <div class="panel-body" style="display:flex;flex-direction:column">
                    <div style="position:relative;flex:1;min-height:220px">
                        <canvas id="userConsumptionChart"></canvas>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        new Chart(document.getElementById('userConsumptionChart'), {
                            type: 'bar',
                            data: {
                                labels: @json($topUserCosts->pluck('user_id')),
                                datasets: [{
                                    data: @json($topUserCosts->pluck('total_cost')->map(fn($v) => round((float) $v, 4))),
                                    backgroundColor: 'rgba(232, 81, 10, 0.75)',
                                    borderWidth: 0,
                                    borderRadius: 3,
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        displayColors: false,
                                        callbacks: { label: ctx => '$' + ctx.parsed.x.toFixed(4) }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { color: 'rgba(0,0,0,0.05)' },
                                        border: { display: false },
                                        ticks: {
                                            color: '#9898AA',
                                            font: { size: 11, family: 'Inter' },
                                            maxTicksLimit: 5,
                                            callback: val => '$' + val.toFixed(2)
                                        }
                                    },
                                    y: {
                                        grid: { display: false },
                                        border: { display: false },
                                        ticks: { color: '#3C3C4A', font: { size: 11, family: 'Inter' } }
                                    }
                                }
                            }
                        });
                    });
                    </script>
                </div>
            @endif
        </div>

    </div>

    {{-- Recent traces --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Recent Traces</span>
            <a href="{{ route('glint.traces.index') }}" class="text-link">View all &rarr;</a>
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
                        <th>Duration</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTraces as $trace)
                        <tr onclick="window.location.href='{{ route('glint.traces.show', $trace->id) }}'"
                            style="cursor:pointer">
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="row-link">
                                    {{ $trace->name ?: 'Unnamed' }}
                                </a>
                            </td>
                            <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                            <td class="t-muted t-mono">{{ $trace->user_id ?? '—' }}</td>
                            <td class="t-muted t-mono">
                                {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                            </td>
                            <td class="t-muted" style="font-size:12.5px">
                                {{ $trace->started_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

@endsection
