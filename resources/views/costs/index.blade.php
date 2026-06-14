@extends('glint::layout')

@section('page-title', 'Costs')
@section('refresh-interval', 30)

@section('content')

    <div class="page-head">
        <div>
            <div class="page-title">Costs</div>
        </div>
        <form method="GET" action="{{ route('glint.costs.index') }}" class="page-toolbar">
            <label for="cost-provider" class="sr-only">Filter by provider</label>
            <select id="cost-provider" name="provider" class="input" onchange="this.form.requestSubmit()">
                <option value="">All providers</option>
                @foreach($providers as $p)
                    <option value="{{ $p }}" {{ $provider === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>

            @include('glint::partials.filters.date-range', [
                'activePeriod' => $period,
                'activeFrom'   => $fromDate,
                'activeTo'     => $toDate,
            ])
        </form>
    </div>

    <div class="metric-strip">
        <div class="metric">
            <div class="metric-label">Total Spend</div>
            <div class="metric-value">${{ number_format($totalCost, 2) }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Providers</div>
            <div class="metric-value">{{ $costByProviderModel->pluck('provider')->unique()->count() }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Models</div>
            <div class="metric-value">{{ $costByProviderModel->pluck('model')->unique()->count() }}</div>
        </div>
    </div>

    <div class="main-side">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Cost Trend</span>
                <span class="panel-meta">@include('glint::partials.period-label', ['period' => $period, 'fromDate' => $fromDate, 'toDate' => $toDate])</span>
            </div>
            @if($costTrend->isEmpty())
                <div class="empty" style="flex:1">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                    </svg>
                    <div class="empty-title">No spend in this period</div>
                    <div class="empty-sub">The trend will appear once generations with cost are recorded in this range.</div>
                </div>
            @else
                @php
                        $trendPoints = $costTrend
                            ->map(fn ($row) => [
                                'label' => \Illuminate\Support\Carbon::parse((string) $row->getAttribute('date'))->format('M j'),
                                'total' => round((float) $row->getAttribute('total'), 6),
                            ])
                            ->values();
                    @endphp
                    <div class="panel-body" style="display:flex;flex-direction:column">
                        <div style="position:relative;flex:1;min-height:220px">
                            <canvas id="costTrendChart"></canvas>
                        </div>
                        <script>
                        (function () {
                            const ctx = document.getElementById('costTrendChart');
                            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
                            gradient.addColorStop(0, 'rgba(237, 94, 43, 0.28)');
                            gradient.addColorStop(1, 'rgba(237, 94, 43, 0)');
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: @json($trendPoints->pluck('label'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                                    datasets: [{
                                        data: @json($trendPoints->pluck('total'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                                        borderColor: '#FF6F3C',
                                        backgroundColor: gradient,
                                        borderWidth: 2,
                                        pointRadius: 0,
                                        pointHoverRadius: 4,
                                        pointBackgroundColor: '#FF8A57',
                                        pointBorderColor: '#0A0A0C',
                                        fill: true,
                                        tension: 0.35,
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { intersect: false, mode: 'index' },
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: ctx => '$' + ctx.parsed.y.toFixed(4)
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            grid: { display: false },
                                            border: { display: false },
                                            ticks: { maxTicksLimit: 8, maxRotation: 0 }
                                        },
                                        y: {
                                            grid: { color: 'rgba(255,255,255,0.04)' },
                                            border: { display: false },
                                            ticks: {
                                                maxTicksLimit: 5,
                                                padding: 8,
                                                callback: val => '$' + val.toFixed(2)
                                            }
                                        }
                                    }
                                }
                            });
                        })();
                        </script>
                    </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Cost by Model</span>
                @if($costByProviderModel->isNotEmpty())
                    <span class="panel-meta">{{ $costByProviderModel->count() }} {{ Str::plural('model', $costByProviderModel->count()) }}</span>
                @endif
            </div>
            @if($costByProviderModel->isEmpty())
                <div class="empty" style="flex:1">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33"/>
                    </svg>
                    <div class="empty-title">No model costs in this period</div>
                    <div class="empty-sub">Per-model spend will appear once priced generations are recorded in this range.</div>
                </div>
            @else
                @php $maxModelCost = max(0.000001, (float) $costByProviderModel->max('total_cost')); @endphp
                <div class="listbar" style="flex:1">
                    @foreach($costByProviderModel->take(8) as $row)
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
                            <div class="listbar-val"><strong>${{ number_format((float) $row->total_cost, 4) }}</strong></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

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
                <div class="empty-title">No cost data in this period</div>
                <div class="empty-sub">Cost data will appear once your app records LLM generations with pricing information.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Model</th>
                        <th class="num">Requests</th>
                        <th class="num">Total Tokens</th>
                        <th class="num">Cost (USD)</th>
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
                            <td class="t-muted t-mono num">{{ number_format($row->total_requests) }}</td>
                            <td class="t-muted t-mono num">{{ $row->total_tokens !== null ? number_format($row->total_tokens) : '—' }}</td>
                            <td class="t-mono t-cost num">${{ number_format($row->total_cost, 6) }}</td>
                            <td style="width:180px">
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
                        <td colspan="4" style="text-align:right;color:var(--text-3);font-size:10.5px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase">Total</td>
                        <td class="t-mono num" style="font-weight:600;color:var(--text-1)">${{ number_format($totalCost, 6) }}</td>
                        <td class="t-dim" style="font-family:var(--font-mono);font-size:11px">100%</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Top Use Cases by Cost</span>
                <a href="{{ route('glint.traces.index') }}" class="panel-link">Traces <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg></a>
            </div>
            @if($topTraceUseCases->isEmpty())
                <div class="empty" style="flex:1">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                    </svg>
                    <div class="empty-title">No use cases in this period</div>
                    <div class="empty-sub">Name your traces to see spend grouped by use case here.</div>
                </div>
            @else
                @php $maxUseCaseCost = max(0.000001, (float) $topTraceUseCases->max('total_cost')); @endphp
                <div class="listbar" style="flex:1">
                    @foreach($topTraceUseCases as $i => $row)
                        <div class="listbar-row">
                            <span class="listbar-rank">{{ $i + 1 }}</span>
                            <div class="listbar-main">
                                <div class="listbar-name">
                                    {{ $row->name }}
                                    <span class="listbar-sub">{{ number_format($row->trace_count) }} {{ Str::plural('trace', $row->trace_count) }}</span>
                                </div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((float) $row->total_cost / $maxUseCaseCost) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="listbar-val"><strong>${{ number_format((float) $row->total_cost, 4) }}</strong></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Top Generations by Cost</span>
                <a href="{{ route('glint.generations.index') }}" class="panel-link">Generations <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg></a>
            </div>
            @if($topGenerationUseCases->isEmpty())
                <div class="empty" style="flex:1">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    </svg>
                    <div class="empty-title">No generations in this period</div>
                    <div class="empty-sub">Name your generations to see spend grouped by name here.</div>
                </div>
            @else
                @php $maxGenCost = max(0.000001, (float) $topGenerationUseCases->max('total_cost')); @endphp
                <div class="listbar" style="flex:1">
                    @foreach($topGenerationUseCases as $i => $row)
                        <div class="listbar-row">
                            <span class="listbar-rank">{{ $i + 1 }}</span>
                            <div class="listbar-main">
                                <div class="listbar-name">
                                    {{ $row->name }}
                                    <span class="listbar-sub">{{ number_format($row->request_count) }} {{ Str::plural('request', $row->request_count) }}</span>
                                </div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((float) $row->total_cost / $maxGenCost) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="listbar-val"><strong>${{ number_format((float) $row->total_cost, 4) }}</strong></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
