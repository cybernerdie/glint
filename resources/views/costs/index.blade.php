@extends('glint::layout')

@section('page-title', 'Costs')

@section('content')

    <div class="page-header">
        <div class="page-title">Costs</div>
        <div class="page-desc">LLM spend breakdown by provider and model</div>
    </div>

    <form method="GET" action="{{ route('glint.costs.index') }}" class="filter-bar">
        <div class="filter-row">
            <label for="cost-provider" class="sr-only">Filter by provider</label>
            <select id="cost-provider" name="provider" class="input" onchange="this.form.submit()">
                <option value="">All providers</option>
                @foreach($providers as $p)
                    <option value="{{ $p }}" {{ $provider === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>

            <div class="filter-row-end">
                @include('glint::partials.filters.date-range', [
                    'activePeriod' => $period,
                    'activeFrom'   => $fromDate,
                    'activeTo'     => $toDate,
                ])
            </div>
        </div>

        @if($provider || $period !== 'today')
            <div class="filter-chips">
                @if($provider)
                    <a href="{{ route('glint.costs.index', array_merge(request()->query(), ['provider' => ''])) }}" class="filter-chip">
                        {{ $provider }} <span class="filter-chip-x">&times;</span>
                    </a>
                @endif
                <a href="{{ route('glint.costs.index') }}" class="filter-chip filter-chip-muted">
                    Clear all <span class="filter-chip-x">&times;</span>
                </a>
            </div>
        @endif
    </form>

    {{-- KPI summary --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Spend</div>
            <div class="kpi-value kpi-value-sm kpi-value-cost">
                ${{ number_format($totalCost, 4) }}
            </div>
            <div class="kpi-footer">
                @include('glint::partials.period-label', ['period' => $period, 'fromDate' => $fromDate, 'toDate' => $toDate])
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Providers</div>
            <div class="kpi-value">{{ $costByProviderModel->pluck('provider')->unique()->count() }}</div>
            <div class="kpi-footer">Distinct providers</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Models</div>
            <div class="kpi-value">{{ $costByProviderModel->pluck('model')->unique()->count() }}</div>
            <div class="kpi-footer">Distinct models used</div>
        </div>
    </div>

    {{-- Cost by model bar chart --}}
    @if($costByProviderModel->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Cost by Model</span>
            </div>
            <div class="panel-body">
                @php $chartHeight = max(180, $costByProviderModel->count() * 32); @endphp
                <div style="position:relative;height:{{ $chartHeight }}px">
                    <canvas id="costByModelChart"></canvas>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    new Chart(document.getElementById('costByModelChart'), {
                        type: 'bar',
                        data: {
                            labels: @json($costByProviderModel->pluck('model')),
                            datasets: [{
                                data: @json($costByProviderModel->pluck('total_cost')->map(fn($v) => round((float) $v, 4))),
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
                                    ticks: { color: '#3C3C4A', font: { size: 12, family: 'Inter' } }
                                }
                            }
                        }
                    });
                });
                </script>
            </div>
        </div>
    @endif

    {{-- Daily cost trend --}}
    @if($dailyAggregates->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Cost Trend</span>
            </div>
            <div class="panel-body">
                <div style="position:relative;height:200px">
                    <canvas id="costTrendChart"></canvas>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    new Chart(document.getElementById('costTrendChart'), {
                        type: 'line',
                        data: {
                            labels: @json($dailyAggregates->map(fn($a) => $a->period_at?->format('M j') ?? '')),
                            datasets: [{
                                data: @json($dailyAggregates->pluck('total_cost_usd')->map(fn($v) => round((float) $v, 6))),
                                borderColor: '#E8510A',
                                backgroundColor: 'rgba(232, 81, 10, 0.08)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#E8510A',
                                fill: true,
                                tension: 0.3,
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
                                        label: ctx => '$' + ctx.parsed.y.toFixed(4)
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    border: { display: false },
                                    ticks: { color: '#9898AA', font: { size: 11, family: 'Inter' }, maxTicksLimit: 10 }
                                },
                                y: {
                                    grid: { color: 'rgba(0,0,0,0.05)' },
                                    border: { display: false },
                                    ticks: {
                                        color: '#9898AA',
                                        font: { size: 11, family: 'Inter' },
                                        maxTicksLimit: 5,
                                        callback: val => '$' + val.toFixed(2)
                                    }
                                }
                            }
                        }
                    });
                });
                </script>
            </div>
        </div>
    @endif

    {{-- Breakdown by provider & model --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Breakdown by Provider &amp; Model</span>
            @if($costByProviderModel->isNotEmpty())
                <span class="panel-meta">{{ $costByProviderModel->count() }} {{ Str::plural('model', $costByProviderModel->count()) }}</span>
            @endif
        </div>

        @if($costByProviderModel->isEmpty())
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33"/>
                </svg>
                <div class="empty-title">No cost data yet</div>
                <div class="empty-sub">
                    @if($provider || $period)
                        Try adjusting your filters, or <a href="{{ route('glint.costs.index') }}" class="text-link">clear them</a>.
                    @else
                        Cost data will appear once your app records LLM generations with pricing information.
                    @endif
                </div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Requests</th>
                        <th>Total Tokens</th>
                        <th>Cost (USD)</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($costByProviderModel as $row)
                        @php
                            $pct = $totalCost > 0 ? round(($row->total_cost / $totalCost) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td class="t-muted">{{ $row->provider }}</td>
                            <td class="t-mono">{{ $row->model }}</td>
                            <td class="t-muted t-mono">{{ number_format($row->total_requests) }}</td>
                            <td class="t-muted t-mono">{{ $row->total_tokens !== null ? number_format($row->total_tokens) : '—' }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--accent)">
                                ${{ number_format($row->total_cost, 6) }}
                            </td>
                            <td>
                                <div class="share-cell">
                                    <div class="share-bar-track">
                                        <div class="share-bar-fill" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="share-pct">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;color:var(--text-3);font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase">Total</td>
                        <td class="t-mono" style="font-weight:700;color:var(--accent)">${{ number_format($totalCost, 6) }}</td>
                        <td class="t-dim" style="font-family:var(--font-mono);font-size:11px">100%</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    {{-- Top trace use cases by cost --}}
    @if($topTraceUseCases->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Top Use Cases by Cost</span>
            </div>
            @php $maxTraceCost = max(0.000001, $topTraceUseCases->max('total_cost')); @endphp
            <table>
                <thead>
                    <tr>
                        <th>Use Case</th>
                        <th>Traces</th>
                        <th>Tokens</th>
                        <th>Cost (USD)</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topTraceUseCases as $row)
                        @php $pct = $totalCost > 0 ? round(((float) $row->total_cost / $totalCost) * 100, 1) : 0; @endphp
                        <tr>
                            <td class="t-name">{{ $row->name }}</td>
                            <td class="t-muted t-mono">{{ number_format($row->trace_count) }}</td>
                            <td class="t-muted t-mono">{{ $row->total_tokens !== null ? number_format($row->total_tokens) : '—' }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--accent)">${{ number_format((float) $row->total_cost, 6) }}</td>
                            <td>
                                <div class="share-cell">
                                    <div class="share-bar-track">
                                        <div class="share-bar-fill" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="share-pct">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Top generation names by cost --}}
    @if($topGenerationUseCases->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Top Generation Names by Cost</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Requests</th>
                        <th>Tokens</th>
                        <th>Cost (USD)</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topGenerationUseCases as $row)
                        @php $pct = $totalCost > 0 ? round(((float) $row->total_cost / $totalCost) * 100, 1) : 0; @endphp
                        <tr>
                            <td class="t-name">{{ $row->name }}</td>
                            <td class="t-muted t-mono">{{ number_format($row->request_count) }}</td>
                            <td class="t-muted t-mono">{{ $row->total_tokens !== null ? number_format($row->total_tokens) : '—' }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--accent)">${{ number_format((float) $row->total_cost, 6) }}</td>
                            <td>
                                <div class="share-cell">
                                    <div class="share-bar-track">
                                        <div class="share-bar-fill" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="share-pct">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Daily aggregates --}}
    @if($dailyAggregates->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Daily Aggregates</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Requests</th>
                        <th>Tokens</th>
                        <th>Cost (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailyAggregates as $agg)
                        <tr>
                            <td class="t-mono t-dim">{{ $agg->period_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="t-muted">{{ $agg->provider }}</td>
                            <td class="t-mono">{{ $agg->model }}</td>
                            <td class="t-muted t-mono">{{ number_format($agg->total_requests) }}</td>
                            <td class="t-muted t-mono">{{ number_format($agg->total_tokens) }}</td>
                            <td class="t-mono" style="color:var(--accent)">${{ number_format($agg->total_cost_usd, 6) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
