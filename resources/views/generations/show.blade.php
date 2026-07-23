@extends('glint::layout')

@section('page-title', 'Generation Detail')

@section('content')

    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
        <div>
            <div style="display:flex;align-items:center;gap:10px">
                <a href="{{ route('glint.generations.index') }}" class="btn btn-ghost" style="padding:5px 10px;font-size:12px">&larr; Generations</a>
                <div class="page-title">{{ $generation->name ?: 'Generation' }}</div>
                @include('glint::partials.status-badge', ['status' => $generation->status])
            </div>
            <div class="page-subtitle" style="margin-top:4px">
                Generation ID: <span style="font-family:monospace">{{ $generation->id }}</span>
            </div>
        </div>
    </div>

    {{-- Core meta --}}
    <div class="detail-grid mb-6">
        <div class="detail-item">
            <label>Provider</label>
            <div class="value">{{ $generation->provider }}</div>
        </div>
        <div class="detail-item">
            <label>Model</label>
            <div class="value-mono">{{ $generation->model }}</div>
        </div>
        <div class="detail-item">
            <label>Duration</label>
            <div class="value">{{ $generation->duration_ms !== null ? number_format($generation->duration_ms).'ms' : '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Cost (USD)</label>
            <div class="value">{{ $generation->cost_usd !== null ? '$'.number_format($generation->cost_usd, 6) : '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Started At</label>
            <div class="value">{{ $generation->started_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Ended At</label>
            <div class="value">{{ $generation->ended_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
        </div>
    </div>

    {{-- Token counts --}}
    <div class="detail-grid mb-6">
        <div class="detail-item">
            <label>Prompt Tokens</label>
            <div class="value">{{ $generation->prompt_tokens !== null ? number_format($generation->prompt_tokens) : '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Completion Tokens</label>
            <div class="value">{{ $generation->completion_tokens !== null ? number_format($generation->completion_tokens) : '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Total Tokens</label>
            <div class="value">{{ $generation->total_tokens !== null ? number_format($generation->total_tokens) : '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Finish Reason</label>
            <div class="value">{{ $generation->finish_reason ?? '—' }}</div>
        </div>
        <div class="detail-item">
            <label>Streaming</label>
            <div class="value">{{ $generation->is_streaming ? 'Yes' : 'No' }}</div>
        </div>
        <div class="detail-item">
            <label>Temperature</label>
            <div class="value">{{ $generation->temperature ?? '—' }}</div>
        </div>
    </div>

    @if($generation->trace_id)
        <div class="mb-4">
            <div class="section-title">Trace</div>
            <a href="{{ route('glint.traces.show', $generation->trace_id) }}" class="link">
                View parent trace &rarr; <span class="td-mono">{{ substr($generation->trace_id, 0, 16) }}&hellip;</span>
            </a>
        </div>
    @endif

    @if($generation->error_message)
        <div class="mb-6">
            <div class="section-title" style="color:var(--error)">Error</div>
            <div class="code-block" style="border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.05)">{{ $generation->error_message }}</div>
        </div>
    @endif

    @if($generation->prompt)
        <div class="mb-6">
            <div class="section-title">Prompt</div>
            <div class="code-block">{{ is_array($generation->prompt) ? json_encode($generation->prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $generation->prompt }}</div>
        </div>
    @endif

    @if($generation->completion)
        <div class="mb-6">
            <div class="section-title">Completion</div>
            <div class="code-block">{{ $generation->completion }}</div>
        </div>
    @endif

    @if(!empty($generation->metadata))
        <div class="mb-6">
            <div class="section-title">Metadata</div>
            <div class="code-block">{{ json_encode($generation->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
        </div>
    @endif

@endsection
