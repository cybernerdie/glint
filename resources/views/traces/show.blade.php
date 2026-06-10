@extends('glint::layout')

@section('page-title', 'Trace Detail')

@section('content')

    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
        <div>
            <div style="display:flex;align-items:center;gap:10px">
                <a href="{{ route('glint.traces.index') }}" class="btn btn-ghost" style="padding:5px 10px;font-size:12px">&larr; Traces</a>
                <div class="page-title">{{ $trace->name ?: 'Unnamed Trace' }}</div>
                @include('glint::partials.status-badge', ['status' => $trace->status])
            </div>
            <div class="page-subtitle" style="margin-top:4px">
                Trace ID: <span style="font-family:monospace">{{ $trace->id }}</span>
            </div>
        </div>
    </div>

    {{-- Meta info --}}
    <div class="detail-grid mb-6">
        <div class="detail-item">
            <label>Started At</label>
            <div class="value">{{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Ended At</label>
            <div class="value">{{ $trace->ended_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Duration</label>
            <div class="value">{{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}</div>
        </div>
        <div class="detail-item">
            <label>User ID</label>
            <div class="value">{{ $trace->user_id ?? '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Session ID</label>
            <div class="value">{{ $trace->session_id ?? '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Team ID</label>
            <div class="value">{{ $trace->team_id ?? '—' }}</div>
        </div>
    </div>

    @php $traceTags = $trace->metadata['tags'] ?? []; @endphp
    @if(!empty($traceTags))
        <div class="mb-4">
            <div class="section-title">Tags</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                @foreach($traceTags as $tagKey => $tagValue)
                    <span class="badge badge-info">{{ $tagKey }}: {{ $tagValue }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($trace->metadata))
        <div class="mb-6">
            <div class="section-title">Metadata</div>
            <div class="code-block">{{ json_encode($trace->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
        </div>
    @endif

    {{-- Generations timeline --}}
    @if($generations->isNotEmpty())
        <div class="mb-6">
            <div class="section-title">Generations ({{ $generations->count() }})</div>
            <div class="table-wrap" style="margin-bottom:0">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Provider</th>
                            <th>Model</th>
                            <th>Tokens</th>
                            <th>Cost</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Started At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($generations as $gen)
                            <tr>
                                <td>
                                    <a href="{{ route('glint.generations.show', $gen->id) }}" class="link">
                                        {{ $gen->name ?: 'Generation' }}
                                    </a>
                                </td>
                                <td>{{ $gen->provider }}</td>
                                <td class="td-mono">{{ $gen->model }}</td>
                                <td class="td-muted">{{ $gen->total_tokens !== null ? number_format($gen->total_tokens) : '—' }}</td>
                                <td class="td-muted">{{ $gen->cost_usd !== null ? '$'.number_format($gen->cost_usd, 6) : '—' }}</td>
                                <td class="td-muted">{{ $gen->duration_ms !== null ? number_format($gen->duration_ms).'ms' : '—' }}</td>
                                <td>@include('glint::partials.status-badge', ['status' => $gen->status])</td>
                                <td class="td-muted">{{ $gen->started_at?->format('H:i:s') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Spans timeline --}}
    @if($spans->isNotEmpty())
        <div class="mb-6">
            <div class="section-title">Spans ({{ $spans->count() }})</div>
            <div class="table-wrap" style="margin-bottom:0">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Started At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($spans as $span)
                            <tr>
                                <td>{{ $span->name }}</td>
                                <td><span class="badge badge-info">{{ $span->type->value }}</span></td>
                                <td class="td-muted">{{ $span->duration_ms !== null ? number_format($span->duration_ms).'ms' : '—' }}</td>
                                <td>@include('glint::partials.status-badge', ['status' => $span->status])</td>
                                <td class="td-muted">{{ $span->started_at?->format('H:i:s') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($generations->isEmpty() && $spans->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">&#128203;</div>
                <div class="empty-state-text">No generations or spans recorded for this trace.</div>
            </div>
        </div>
    @endif

@endsection
