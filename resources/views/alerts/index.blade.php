@extends('glint::layout')

@section('page-title', 'Alerts')

@section('content')

    <div class="page-head">
        <div>
            <div class="page-title">Alerts</div>
        </div>
        <div class="page-toolbar">
            <a href="{{ route('glint.alerts.create') }}" class="btn btn-primary">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                New Rule
            </a>
        </div>
    </div>

    {{-- Alert Rules --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Alert Rules</span>
            @if(method_exists($rules, 'total') && $rules->total() > 0)
                <span class="panel-meta">{{ number_format($rules->total()) }} {{ Str::plural('rule', $rules->total()) }}</span>
            @endif
        </div>

        @php $rulesEmpty = method_exists($rules, 'isEmpty') ? $rules->isEmpty() : count($rules) === 0; @endphp

        @if($rulesEmpty)
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
                <div class="empty-title">No alert rules yet</div>
                <div class="empty-sub">Create a rule to get notified when cost, error rate, latency, or token usage exceeds a threshold.</div>
                <a href="{{ route('glint.alerts.create') }}" class="btn btn-primary btn-sm" style="margin-top:16px">New Rule</a>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Threshold</th>
                        <th>Scope</th>
                        <th>Channels</th>
                        <th>Status</th>
                        <th class="num">Last Triggered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules as $rule)
                        @php
                            $config = $rule->threshold_config ?? [];
                            $threshold = is_numeric($config['threshold'] ?? null) ? (float) $config['threshold'] : null;
                            $period = is_string($config['period'] ?? null) ? $config['period'] : 'day';
                            $thresholdLabel = match($rule->type->value) {
                                'cost_threshold' => $threshold !== null ? '$'.number_format($threshold, 2) : '—',
                                'error_rate'     => $threshold !== null ? number_format($threshold, 1).'%' : '—',
                                'latency_spike'  => $threshold !== null ? number_format($threshold).'ms' : '—',
                                'token_spike'    => $threshold !== null ? number_format($threshold).' tokens' : '—',
                                default          => '—',
                            };
                            $typeLabel = match($rule->type->value) {
                                'cost_threshold' => 'Cost',
                                'error_rate'     => 'Error Rate',
                                'latency_spike'  => 'Latency',
                                'token_spike'    => 'Tokens',
                                default          => $rule->type->value,
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="row-link">{{ $rule->name }}</span>
                            </td>
                            <td>
                                <span class="badge badge-pending">{{ $typeLabel }}</span>
                            </td>
                            <td class="t-mono t-muted">
                                {{ $thresholdLabel }}
                                <span class="t-dim" style="font-size:11px">/ {{ $period }}</span>
                            </td>
                            <td class="t-muted" style="font-size:12.5px">
                                {{ ucfirst($rule->scope->value) }}
                                @if($rule->scope_id)
                                    <span class="t-dim t-mono" style="font-size:11px">{{ $rule->scope_id }}</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap">
                                    @foreach((array) ($rule->channels ?? []) as $channel)
                                        <span class="badge badge-pending" style="font-size:9.5px">{{ $channel }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($rule->enabled)
                                    <span class="badge badge-success">enabled</span>
                                @else
                                    <span class="badge" style="background:rgba(255,255,255,0.04);color:var(--text-3)">disabled</span>
                                @endif
                            </td>
                            <td class="t-dim t-mono num" style="font-size:11.5px">
                                {{ $rule->last_triggered_at?->diffForHumans(short: true) ?? '—' }}
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;justify-content:flex-end">
                                    {{-- Toggle --}}
                                    <form method="POST" action="{{ route('glint.alerts.toggle', $rule->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" title="{{ $rule->enabled ? 'Disable' : 'Enable' }}">
                                            @if($rule->enabled)
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" style="width:13px;height:13px">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                                Disable
                                            @else
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" style="width:13px;height:13px">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 12.728M12 3v9"/>
                                                </svg>
                                                Enable
                                            @endif
                                        </button>
                                    </form>
                                    {{-- Delete --}}
                                    <form method="POST" action="{{ route('glint.alerts.destroy', $rule->id) }}"
                                          onsubmit="return confirm('Delete alert rule &quot;{{ addslashes($rule->name) }}&quot;? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--error-text)">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" style="width:13px;height:13px">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($rules, 'links'))
                <div class="pagination">
                    {{ $rules->links('glint::pagination') }}
                </div>
            @endif
        @endif
    </div>

    {{-- Recent Alert Events --}}
    <div class="panel" style="margin-top:24px">
        <div class="panel-header">
            <span class="panel-title">Recent Events</span>
            <span class="panel-meta">Last 50</span>
        </div>

        @php $eventsEmpty = $recentEvents->isEmpty(); @endphp

        @if($eventsEmpty)
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-title">No events triggered yet</div>
                <div class="empty-sub">Alert events will appear here when a rule threshold is exceeded.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Rule</th>
                        <th>Channel</th>
                        <th>Value</th>
                        <th>Threshold</th>
                        <th>Status</th>
                        <th class="num">Triggered</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentEvents as $event)
                        @php
                            $ctx = $event->context ?? [];
                            $type = is_string($ctx['type'] ?? null) ? $ctx['type'] : '';
                            $currentValue = is_numeric($ctx['current_value'] ?? null) ? (float) $ctx['current_value'] : null;
                            $threshold = is_numeric($ctx['threshold'] ?? null) ? (float) $ctx['threshold'] : null;
                            $formatValue = function (?float $v) use ($type): string {
                                if ($v === null) { return '—'; }
                                return match($type) {
                                    'cost_threshold' => '$'.number_format($v, 4),
                                    'error_rate'     => number_format($v, 1).'%',
                                    'latency_spike'  => number_format($v).'ms',
                                    'token_spike'    => number_format($v).' tokens',
                                    default          => number_format($v, 2),
                                };
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="row-link">{{ $event->rule?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="badge badge-pending" style="font-size:9.5px">{{ $event->channel }}</span>
                            </td>
                            <td class="t-mono t-muted" style="font-size:12.5px">
                                {{ $formatValue($currentValue) }}
                            </td>
                            <td class="t-mono t-dim" style="font-size:12.5px">
                                {{ $formatValue($threshold) }}
                            </td>
                            <td>
                                @if($event->status->value === 'sent')
                                    <span class="badge badge-success">sent</span>
                                @else
                                    <span class="badge badge-error">failed</span>
                                @endif
                            </td>
                            <td class="t-dim t-mono num" style="font-size:11.5px">
                                {{ $event->triggered_at?->diffForHumans(short: true) ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

@endsection
