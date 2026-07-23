@extends('glint::layout')

@section('page-title', 'Costs')

@section('content')

    <div class="page-header">
        <div class="page-title">Costs</div>
        <div class="page-subtitle">LLM spend breakdown</div>
    </div>

    {{-- Total summary --}}
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-bottom:24px">
        <div class="card">
            <div class="card-title">Total Spend (All Time)</div>
            <div class="card-value">${{ number_format($totalCost, 4) }}</div>
            <div class="card-sub">USD across all providers</div>
        </div>

        <div class="card">
            <div class="card-title">Providers</div>
            <div class="card-value">{{ $costByProviderModel->pluck('provider')->unique()->count() }}</div>
            <div class="card-sub">Distinct providers used</div>
        </div>

        <div class="card">
            <div class="card-title">Models</div>
            <div class="card-value">{{ $costByProviderModel->pluck('model')->unique()->count() }}</div>
            <div class="card-sub">Distinct models used</div>
        </div>
    </div>

    {{-- Breakdown table --}}
    <div class="table-wrap mb-6">
        <div class="table-header">
            <span class="table-title">Cost Breakdown by Provider &amp; Model</span>
        </div>

        @if($costByProviderModel->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon">&#128176;</div>
                <div class="empty-state-text">No cost data recorded yet.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Requests</th>
                        <th>Total Tokens</th>
                        <th>Total Cost (USD)</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($costByProviderModel as $row)
                        @php
                            $pct = $totalCost > 0 ? round(($row->total_cost / $totalCost) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td>{{ $row->provider }}</td>
                            <td class="td-mono">{{ $row->model }}</td>
                            <td class="td-muted">{{ number_format($row->total_requests) }}</td>
                            <td class="td-muted">{{ $row->total_tokens !== null ? number_format($row->total_tokens) : '—' }}</td>
                            <td style="font-weight:600">${{ number_format($row->total_cost, 6) }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="width:80px;height:6px;background:var(--sidebar);border-radius:3px;overflow:hidden">
                                        <div style="height:100%;width:{{ $pct }}%;background:var(--accent);border-radius:3px"></div>
                                    </div>
                                    <span class="td-muted">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight:700">
                        <td colspan="4" style="text-align:right;color:var(--muted);font-size:12px;padding-right:16px">Total</td>
                        <td style="font-weight:700">${{ number_format($totalCost, 6) }}</td>
                        <td class="td-muted">100%</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    {{-- Daily aggregates (last 30 days) --}}
    @if($dailyAggregates->isNotEmpty())
        <div class="table-wrap">
            <div class="table-header">
                <span class="table-title">Daily Aggregates (Last 30 Days)</span>
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
                            <td class="td-muted">{{ $agg->period_at?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $agg->provider }}</td>
                            <td class="td-mono">{{ $agg->model }}</td>
                            <td class="td-muted">{{ number_format($agg->total_requests) }}</td>
                            <td class="td-muted">{{ number_format($agg->total_tokens) }}</td>
                            <td>${{ number_format($agg->total_cost_usd, 6) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
