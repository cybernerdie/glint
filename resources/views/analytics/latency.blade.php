@extends('glint::layout')

@section('page-title', 'Latency')

@section('content')

    <div class="page-header">
        <div class="page-header-row">
            <div>
                <div class="page-title">Latency</div>
                <div class="page-desc">Percentile latency, throughput, and worst-case analysis</div>
            </div>
            <form method="GET" action="{{ route('glint.analytics.latency') }}">
                @include('glint::partials.filters.date-range', [
                    'activePeriod' => $period,
                    'activeFrom'   => $fromDate,
                    'activeTo'     => $toDate,
                ])
            </form>
        </div>
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

    {{-- Trace latency percentiles --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Trace Latency Percentiles</span>
        </div>

        @if(empty($tracePercentiles))
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-title">No trace latency data yet</div>
                <div class="empty-sub">Trace duration data will appear once your app records named traces with durations.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Trace Name</th>
                        <th>Count</th>
                        <th style="color:var(--text-2)">p50</th>
                        <th style="color:var(--text-2)">p90</th>
                        <th style="color:var(--accent)">p95 ▼</th>
                        <th style="color:var(--error-text)">p99</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tracePercentiles as $row)
                        <tr>
                            <td style="font-family:var(--font-mono);font-size:12.5px;font-weight:500">{{ $row['name'] }}</td>
                            <td class="t-muted t-mono">{{ number_format($row['count']) }}</td>
                            <td class="t-mono t-muted">{{ $fmtMs($row['p50']) }}</td>
                            <td class="t-mono t-muted">{{ $fmtMs($row['p90']) }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--accent)">{{ $fmtMs($row['p95']) }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--error-text)">{{ $fmtMs($row['p99']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Generation latency percentiles --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Generation Latency Percentiles</span>
        </div>

        @if(empty($generationPercentiles))
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-title">No generation latency data yet</div>
                <div class="empty-sub">Generation duration data will appear once your app records LLM API calls.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Provider</th>
                        <th>Count</th>
                        <th style="color:var(--text-2)">p50</th>
                        <th style="color:var(--text-2)">p90</th>
                        <th style="color:var(--accent)">p95 ▼</th>
                        <th style="color:var(--error-text)">p99</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($generationPercentiles as $row)
                        <tr>
                            <td class="t-mono" style="font-weight:500">{{ $row['model'] }}</td>
                            <td class="t-muted">{{ $row['provider'] }}</td>
                            <td class="t-muted t-mono">{{ number_format($row['count']) }}</td>
                            <td class="t-mono t-muted">{{ $fmtMs($row['p50']) }}</td>
                            <td class="t-mono t-muted">{{ $fmtMs($row['p90']) }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--accent)">{{ $fmtMs($row['p95']) }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--error-text)">{{ $fmtMs($row['p99']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Latency by Use Case (avg) --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Avg Latency by Use Case</span>
        </div>

        @if($traceLatency->isEmpty())
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-title">No latency data yet</div>
                <div class="empty-sub">Latency data appears once named traces with durations are recorded.</div>
            </div>
        @else
            @php $maxDuration = max(1, $traceLatency->max('avg_duration')); @endphp
            <table>
                <thead>
                    <tr>
                        <th>Use Case</th>
                        <th>Traces</th>
                        <th>Avg</th>
                        <th>Max</th>
                        <th>Min</th>
                        <th style="width:180px">Relative</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($traceLatency as $row)
                        @php $pct = round(($row->avg_duration / $maxDuration) * 100); @endphp
                        <tr>
                            <td style="font-weight:500;font-family:var(--font-mono);font-size:12.5px">{{ $row->name }}</td>
                            <td class="t-muted t-mono">{{ number_format($row->trace_count) }}</td>
                            <td class="t-mono" style="font-weight:600">{{ $fmtMs((int) $row->avg_duration) }}</td>
                            <td class="t-muted t-mono">{{ $fmtMs((int) $row->max_duration) }}</td>
                            <td class="t-muted t-mono">{{ $fmtMs((int) $row->min_duration) }}</td>
                            <td>
                                <div class="share-cell">
                                    <div class="share-bar-track">
                                        <div class="share-bar-fill" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="share-pct">{{ $fmtMs((int) $row->avg_duration) }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Token Throughput by Model --}}
    @if($tokenThroughput->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Throughput by Model</span>
            </div>
            @php $maxTps = max(1, $tokenThroughput->max('tokens_per_second')); @endphp
            <table>
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Provider</th>
                        <th>Requests</th>
                        <th>Avg Tokens</th>
                        <th>Avg Duration</th>
                        <th>Tokens / sec</th>
                        <th style="width:160px">Speed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokenThroughput as $row)
                        @php $pct = round(($row->tokens_per_second / $maxTps) * 100); @endphp
                        <tr>
                            <td class="t-mono" style="font-weight:500">{{ $row->model }}</td>
                            <td class="t-muted">{{ $row->provider }}</td>
                            <td class="t-muted t-mono">{{ number_format($row->request_count) }}</td>
                            <td class="t-muted t-mono">{{ number_format((float) $row->avg_tokens) }}</td>
                            <td class="t-muted t-mono">{{ $fmtMs((int) $row->avg_duration) }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--success-text)">
                                {{ number_format((float) $row->tokens_per_second, 1) }}
                            </td>
                            <td>
                                <div class="share-cell">
                                    <div class="share-bar-track" style="background:var(--success-bg)">
                                        <div class="share-bar-fill" style="width:{{ $pct }}%;background:var(--success)"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Latency by User --}}
    @if($userLatency->isNotEmpty())
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Max Latency by User</span>
            </div>
            @php $maxUserDuration = max(1, $userLatency->max('max_duration')); @endphp
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Traces</th>
                        <th>Avg</th>
                        <th>Max</th>
                        <th style="width:180px">Worst case</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($userLatency as $row)
                        @php $pct = round(($row->max_duration / $maxUserDuration) * 100); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('glint.users.show', $row->user_id) }}"
                                   style="color:var(--text-1);text-decoration:none;font-family:var(--font-mono);font-size:12.5px;font-weight:500"
                                   onmouseover="this.style.color='var(--accent)'"
                                   onmouseout="this.style.color='var(--text-1)'">
                                    {{ $row->user_id }}
                                </a>
                            </td>
                            <td class="t-muted t-mono">{{ number_format($row->trace_count) }}</td>
                            <td class="t-muted t-mono">{{ $fmtMs((int) $row->avg_duration) }}</td>
                            <td class="t-mono" style="font-weight:600;color:var(--warning-text)">
                                {{ $fmtMs((int) $row->max_duration) }}
                            </td>
                            <td>
                                <div class="share-cell">
                                    <div class="share-bar-track" style="background:var(--warning-bg)">
                                        <div class="share-bar-fill" style="width:{{ $pct }}%;background:var(--warning)"></div>
                                    </div>
                                    <span class="share-pct">{{ $fmtMs((int) $row->max_duration) }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
