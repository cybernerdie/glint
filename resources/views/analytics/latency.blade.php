@extends('glint::layout')

@section('page-title', 'Latency')

@section('content')

    <div class="page-head">
        <div>
            <div class="page-title">Latency</div>
            <div class="page-desc">Percentile latency, throughput, and worst-case analysis</div>
        </div>
        <form method="GET" action="{{ route('glint.analytics.latency') }}" class="page-toolbar">
            @include('glint::partials.filters.date-range', [
                'activePeriod' => $period,
                'activeFrom'   => $fromDate,
                'activeTo'     => $toDate,
            ])
        </form>
    </div>

    @php
        $fmtMs = static function (int $ms): string {
            if ($ms < 1000) {
                return $ms . 'ms';
            }
            if ($ms < 60000) {
                return number_format($ms / 1000, 2) . 's';
            }
            $m = (int) floor($ms / 60000);
            $s = (int) floor(($ms % 60000) / 1000);
            return $m . 'm ' . $s . 's';
        };
    @endphp

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Latency by Use Case</span>
            <span class="panel-meta">p50 / p95 / p99 per named trace</span>
        </div>

        @if(empty($tracePercentiles))
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-title">No trace latency yet</div>
                <div class="empty-sub">Latency percentiles appear once your app records named traces with durations.</div>
            </div>
        @else
            @php $maxTraceP99 = max(1, collect($tracePercentiles)->max('p99')); @endphp
            <table>
                <thead>
                    <tr>
                        <th>Use Case</th>
                        <th class="num">Count</th>
                        <th class="num">p50</th>
                        <th class="num">p90</th>
                        <th class="num">p95</th>
                        <th class="num" style="color:var(--error-text)">p99</th>
                        <th style="width:220px">Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tracePercentiles as $row)
                        @php
                            $w50 = round(($row['p50'] / $maxTraceP99) * 100, 1);
                            $w95 = round((($row['p95'] - $row['p50']) / $maxTraceP99) * 100, 1);
                            $w99 = round((($row['p99'] - $row['p95']) / $maxTraceP99) * 100, 1);
                        @endphp
                        <tr>
                            <td class="t-name">{{ $row['name'] }}</td>
                            <td class="t-muted t-mono num">{{ number_format($row['count']) }}</td>
                            <td class="t-mono t-muted num">{{ $fmtMs($row['p50']) }}</td>
                            <td class="t-mono t-muted num">{{ $fmtMs($row['p90']) }}</td>
                            <td class="t-mono num" style="font-weight:500">{{ $fmtMs($row['p95']) }}</td>
                            <td class="t-mono num" style="font-weight:600;color:var(--error-text)">{{ $fmtMs($row['p99']) }}</td>
                            <td>
                                <div class="pct-bar" title="p50 {{ $fmtMs($row['p50']) }} · p95 {{ $fmtMs($row['p95']) }} · p99 {{ $fmtMs($row['p99']) }}">
                                    <div class="pct-seg-p50" style="width:{{ $w50 }}%"></div>
                                    <div class="pct-seg-p95" style="width:{{ $w95 }}%"></div>
                                    <div class="pct-seg-p99" style="width:{{ $w99 }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Latency by Model</span>
            <span class="panel-meta">p50 / p95 / p99 per model</span>
        </div>

        @if(empty($generationPercentiles))
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-title">No generation latency yet</div>
                <div class="empty-sub">Model latency percentiles appear once your app records LLM API calls.</div>
            </div>
        @else
            @php $maxGenP99 = max(1, collect($generationPercentiles)->max('p99')); @endphp
            <table>
                <thead>
                    <tr>
                        <th>Model</th>
                        <th class="num">Count</th>
                        <th class="num">p50</th>
                        <th class="num">p90</th>
                        <th class="num">p95</th>
                        <th class="num" style="color:var(--error-text)">p99</th>
                        <th style="width:220px">Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($generationPercentiles as $row)
                        @php
                            $w50 = round(($row['p50'] / $maxGenP99) * 100, 1);
                            $w95 = round((($row['p95'] - $row['p50']) / $maxGenP99) * 100, 1);
                            $w99 = round((($row['p99'] - $row['p95']) / $maxGenP99) * 100, 1);
                        @endphp
                        <tr>
                            <td>
                                <div class="t-mono" style="font-size:12px;font-weight:500">{{ $row['model'] }}</div>
                                <div class="t-sub">{{ $row['provider'] }}</div>
                            </td>
                            <td class="t-muted t-mono num">{{ number_format($row['count']) }}</td>
                            <td class="t-mono t-muted num">{{ $fmtMs($row['p50']) }}</td>
                            <td class="t-mono t-muted num">{{ $fmtMs($row['p90']) }}</td>
                            <td class="t-mono num" style="font-weight:500">{{ $fmtMs($row['p95']) }}</td>
                            <td class="t-mono num" style="font-weight:600;color:var(--error-text)">{{ $fmtMs($row['p99']) }}</td>
                            <td>
                                <div class="pct-bar" title="p50 {{ $fmtMs($row['p50']) }} · p95 {{ $fmtMs($row['p95']) }} · p99 {{ $fmtMs($row['p99']) }}">
                                    <div class="pct-seg-p50" style="width:{{ $w50 }}%"></div>
                                    <div class="pct-seg-p95" style="width:{{ $w95 }}%"></div>
                                    <div class="pct-seg-p99" style="width:{{ $w99 }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Throughput by Model</span>
                <span class="panel-meta">tokens / second</span>
            </div>
            @if($tokenThroughput->isEmpty())
                <div class="empty" style="flex:1">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                    </svg>
                    <div class="empty-title">No throughput in this period</div>
                    <div class="empty-sub">Tokens-per-second appears once generations with token counts and durations are recorded.</div>
                </div>
            @else
                @php $maxTps = max(0.001, (float) $tokenThroughput->max('tokens_per_second')); @endphp
                <div class="listbar" style="flex:1">
                    @foreach($tokenThroughput as $i => $row)
                        <div class="listbar-row">
                            <span class="listbar-rank">{{ $i + 1 }}</span>
                            <div class="listbar-main">
                                <div class="listbar-name">
                                    {{ $row->model }}
                                    <span class="listbar-sub">{{ $row->provider }} &middot; {{ number_format($row->request_count) }} {{ Str::plural('request', $row->request_count) }}</span>
                                </div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((float) $row->tokens_per_second / $maxTps) * 100) }}%;background:linear-gradient(90deg,#10B981,#34D399)"></div>
                                </div>
                            </div>
                            <div class="listbar-val"><strong style="color:var(--success-text)">{{ number_format((float) $row->tokens_per_second, 1) }}</strong> tok/s</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Slowest Users</span>
                <span class="panel-meta">worst-case trace duration</span>
            </div>
            @if($userLatency->isEmpty())
                <div class="empty" style="flex:1">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                    <div class="empty-title">No user latency in this period</div>
                    <div class="empty-sub">Pass a <code style="font-family:var(--font-mono);font-size:11px">user_id</code> when recording traces to see worst-case latency per user.</div>
                </div>
            @else
                @php $maxUserDuration = max(1, (int) $userLatency->max('max_duration')); @endphp
                <div class="listbar" style="flex:1">
                    @foreach($userLatency as $i => $row)
                        <a class="listbar-row" href="{{ route('glint.users.show', $row->user_id) }}">
                            <span class="listbar-rank">{{ $i + 1 }}</span>
                            <div class="listbar-main">
                                <div class="listbar-name">
                                    {{ $row->user_id }}
                                    <span class="listbar-sub">{{ number_format($row->trace_count) }} {{ Str::plural('trace', $row->trace_count) }} &middot; avg {{ $fmtMs((int) $row->avg_duration) }}</span>
                                </div>
                                <div class="listbar-track">
                                    <div class="listbar-fill" style="width:{{ round(((int) $row->max_duration / $maxUserDuration) * 100) }}%;background:linear-gradient(90deg,#D97706,#FBBF24)"></div>
                                </div>
                            </div>
                            <div class="listbar-val"><strong style="color:var(--warning-text)">{{ $fmtMs((int) $row->max_duration) }}</strong></div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
