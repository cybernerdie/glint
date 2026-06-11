@extends('glint::layout')

@section('page-title', $generation->name ?: 'Generation')

@section('breadcrumb')
    <span class="topbar-sep">/</span>
    <a href="{{ route('glint.generations.index') }}" class="breadcrumb-link">Generations</a>
    <span class="topbar-sep">/</span>
    <span class="breadcrumb-current">{{ $generation->name ?: substr($generation->id, 0, 12).'…' }}</span>
@endsection

@section('content')

    <div class="hero">
        <div class="hero-title-row">
            <div class="hero-title">{{ $generation->name ?: 'Generation' }}</div>
            @include('glint::partials.status-badge', ['status' => $generation->status])
            @if($generation->is_streaming)
                <span class="badge badge-running">streaming</span>
            @endif
        </div>
        <div class="hero-id">{{ $generation->id }}</div>
    </div>

    <div class="stat-strip">
        <div class="stat">
            <div class="stat-label">Model</div>
            <div class="stat-value">{{ $generation->model }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Provider</div>
            <div class="stat-value">{{ $generation->provider }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Duration</div>
            <div class="stat-value">{{ $generation->duration_ms !== null ? number_format($generation->duration_ms).'ms' : '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Cost</div>
            <div class="stat-value is-cost">{{ $generation->cost_usd !== null ? '$'.number_format($generation->cost_usd, 6) : '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Started</div>
            <div class="stat-value">{{ $generation->started_at?->format('M j, H:i:s') ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Ended</div>
            <div class="stat-value">{{ $generation->ended_at?->format('M j, H:i:s') ?? '—' }}</div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat">
            <div class="stat-label">Prompt Tokens</div>
            <div class="stat-value">{{ $generation->prompt_tokens !== null ? number_format($generation->prompt_tokens) : '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Completion Tokens</div>
            <div class="stat-value">{{ $generation->completion_tokens !== null ? number_format($generation->completion_tokens) : '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Tokens</div>
            <div class="stat-value">{{ $generation->total_tokens !== null ? number_format($generation->total_tokens) : '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Temperature</div>
            <div class="stat-value">{{ $generation->temperature ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Finish Reason</div>
            <div class="stat-value">{{ $generation->finish_reason ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Streaming</div>
            <div class="stat-value">{{ $generation->is_streaming ? 'Yes' : 'No' }}</div>
        </div>
    </div>

    @if($generation->trace_id)
        <div style="margin-bottom:16px">
            <a href="{{ route('glint.traces.show', $generation->trace_id) }}" class="btn btn-ghost btn-sm">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" style="width:13px;height:13px">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
                View parent trace
                <span style="font-family:var(--font-mono);font-size:11px;color:var(--text-3)">{{ substr($generation->trace_id, 0, 12) }}&hellip;</span>
            </a>
        </div>
    @endif

    @if($generation->error_message)
        <div class="error-card">
            <div class="error-card-header">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;color:var(--error-text);flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <span class="error-card-title">Error</span>
            </div>
            <div class="error-body">{{ $generation->error_message }}</div>
        </div>
    @endif

    @if($generation->prompt)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Prompt</span>
                @include('glint::partials.copy-button')
            </div>
            <div class="code-body">{{ is_array($generation->prompt) ? json_encode($generation->prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $generation->prompt }}</div>
        </div>
    @endif

    @if($generation->completion)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Completion</span>
                @include('glint::partials.copy-button')
            </div>
            <div class="code-body">{{ $generation->completion }}</div>
        </div>
    @endif

    @if(!empty($generation->metadata))
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Metadata</span>
                @include('glint::partials.copy-button')
            </div>
            <div class="code-body">{{ json_encode($generation->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
        </div>
    @endif

@endsection
