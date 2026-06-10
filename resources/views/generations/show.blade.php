@extends('glint::layout')

@section('page-title', ($generation->name ?: 'Generation').' — Generation')

@section('content')

    <nav class="breadcrumb">
        <a href="{{ route('glint.dashboard') }}" class="breadcrumb-link">Glint</a>
        <span class="breadcrumb-sep">/</span>
        <a href="{{ route('glint.generations.index') }}" class="breadcrumb-link">Generations</a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current">{{ substr($generation->id, 0, 16) }}&hellip;</span>
    </nav>

    <div class="page-header">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="page-title">{{ $generation->name ?: 'Generation' }}</div>
            @include('glint::partials.status-badge', ['status' => $generation->status])
        </div>
        <div class="page-desc" style="font-family:var(--font-mono);font-size:12px;margin-top:6px;color:var(--text-3)">
            {{ $generation->id }}
        </div>
    </div>

    {{-- Core info --}}
    <div class="info-card">
        <div class="info-card-header">
            <span class="info-card-title">Generation Info</span>
        </div>
        <div class="info-card-body">
            <div class="field-grid">
                <div class="field">
                    <label>Provider</label>
                    <div class="field-val">{{ $generation->provider }}</div>
                </div>
                <div class="field">
                    <label>Model</label>
                    <div class="field-val-mono">{{ $generation->model }}</div>
                </div>
                <div class="field">
                    <label>Duration</label>
                    <div class="field-val-mono">{{ $generation->duration_ms !== null ? number_format($generation->duration_ms).'ms' : '—' }}</div>
                </div>
                <div class="field">
                    <label>Cost (USD)</label>
                    <div class="field-val-mono" style="color:var(--accent);font-weight:600">
                        {{ $generation->cost_usd !== null ? '$'.number_format($generation->cost_usd, 6) : '—' }}
                    </div>
                </div>
                <div class="field">
                    <label>Started At</label>
                    <div class="field-val-mono">{{ $generation->started_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>Ended At</label>
                    <div class="field-val-mono">{{ $generation->ended_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tokens & parameters --}}
    <div class="info-card">
        <div class="info-card-header">
            <span class="info-card-title">Tokens &amp; Parameters</span>
        </div>
        <div class="info-card-body">
            <div class="field-grid">
                <div class="field">
                    <label>Prompt Tokens</label>
                    <div class="field-val-mono">{{ $generation->prompt_tokens !== null ? number_format($generation->prompt_tokens) : '—' }}</div>
                </div>
                <div class="field">
                    <label>Completion Tokens</label>
                    <div class="field-val-mono">{{ $generation->completion_tokens !== null ? number_format($generation->completion_tokens) : '—' }}</div>
                </div>
                <div class="field">
                    <label>Total Tokens</label>
                    <div class="field-val-mono" style="font-weight:600">{{ $generation->total_tokens !== null ? number_format($generation->total_tokens) : '—' }}</div>
                </div>
                <div class="field">
                    <label>Finish Reason</label>
                    <div class="field-val-muted">{{ $generation->finish_reason ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>Streaming</label>
                    <div class="field-val">
                        @if($generation->is_streaming)
                            <span class="badge badge-running">streaming</span>
                        @else
                            <span class="field-val-muted">No</span>
                        @endif
                    </div>
                </div>
                <div class="field">
                    <label>Temperature</label>
                    <div class="field-val-mono">{{ $generation->temperature ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Parent trace link --}}
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

    {{-- Error --}}
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

    {{-- Prompt --}}
    @if($generation->prompt)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Prompt</span>
                <button type="button" class="copy-btn" onclick="glintCopy(this)">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="copy-label">Copy</span>
                </button>
            </div>
            <div class="code-body">{{ is_array($generation->prompt) ? json_encode($generation->prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $generation->prompt }}</div>
        </div>
    @endif

    {{-- Completion --}}
    @if($generation->completion)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Completion</span>
                <button type="button" class="copy-btn" onclick="glintCopy(this)">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="copy-label">Copy</span>
                </button>
            </div>
            <div class="code-body">{{ $generation->completion }}</div>
        </div>
    @endif

    {{-- Metadata --}}
    @if(!empty($generation->metadata))
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Metadata</span>
                <button type="button" class="copy-btn" onclick="glintCopy(this)">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="copy-label">Copy</span>
                </button>
            </div>
            <div class="code-body">{{ json_encode($generation->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
        </div>
    @endif

@endsection
