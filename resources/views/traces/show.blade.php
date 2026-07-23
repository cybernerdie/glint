@extends('glint::layout')

@section('page-title', $trace->name ?: 'Unnamed Trace')

@section('breadcrumb')
    <span class="topbar-sep">/</span>
    <a href="{{ route('glint.traces.index') }}" class="breadcrumb-link">Traces</a>
    <span class="topbar-sep">/</span>
    <span class="breadcrumb-current">{{ $trace->name ?: substr($trace->id, 0, 12).'…' }}</span>
@endsection

@section('content')

    <div class="hero">
        <div class="hero-title-row">
            <div class="hero-title">{{ $trace->name ?: 'Unnamed Trace' }}</div>
            @include('glint::partials.status-badge', ['status' => $trace->status])
        </div>
        <div class="hero-id">{{ $trace->id }}</div>
    </div>

    <div class="stat-strip">
        <div class="stat">
            <div class="stat-label">Started</div>
            <div class="stat-value">{{ $trace->started_at?->format('M j, H:i:s') ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Ended</div>
            <div class="stat-value">{{ $trace->ended_at?->format('M j, H:i:s') ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Duration</div>
            <div class="stat-value">{{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">User</div>
            <div class="stat-value">{{ $trace->user_id ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Session</div>
            <div class="stat-value">{{ $trace->session_id ?? '—' }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Team</div>
            <div class="stat-value">{{ $trace->team_id ?? '—' }}</div>
        </div>
    </div>

    @php $traceTags = $trace->metadata['tags'] ?? []; @endphp
    @if(!empty($traceTags))
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px">
            @foreach($traceTags as $tagKey => $tagVal)
                <span class="tag"><span class="tag-key">{{ $tagKey }}</span>{{ $tagVal }}</span>
            @endforeach
        </div>
    @endif

    @if($trace->input ?? false)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Input</span>
                @include('glint::partials.copy-button')
            </div>
            <div class="code-body">{{ $trace->input }}</div>
        </div>
    @endif

    @if($trace->output ?? false)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Output</span>
                @include('glint::partials.copy-button')
            </div>
            <div class="code-body">{{ $trace->output }}</div>
        </div>
    @endif

    @if(!empty($trace->metadata))
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Metadata</span>
                @include('glint::partials.copy-button')
            </div>
            <div class="code-body">{{ json_encode($trace->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
        </div>
    @endif

    @if($generations->isNotEmpty())
        <div class="section-head">
            <div class="section-title">LLM Calls</div>
            <span class="panel-meta">{{ $generations->count() }} {{ Str::plural('call', $generations->count()) }}</span>
        </div>

        @foreach($generations as $i => $gen)
            <div class="llm-call" x-data="{ open: false }">
                <div class="llm-call-header" @click="open = !open">
                    <div class="llm-call-left">
                        <span class="llm-call-index">#{{ $i + 1 }}</span>
                        <div style="min-width:0">
                            <div class="llm-call-model">{{ $gen->model }}</div>
                            @if($gen->name)
                                <div class="llm-call-name">{{ $gen->name }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="llm-call-right">
                        @include('glint::partials.status-badge', ['status' => $gen->status])
                        @if($gen->total_tokens !== null)
                            <span class="llm-call-stat">{{ number_format($gen->total_tokens) }} tokens</span>
                        @endif
                        @if($gen->cost_usd !== null)
                            <span class="llm-call-stat" style="color:var(--accent-bright)">${{ number_format($gen->cost_usd, 6) }}</span>
                        @endif
                        @if($gen->duration_ms !== null)
                            <span class="llm-call-stat">{{ number_format($gen->duration_ms) }}ms</span>
                        @endif
                        <a href="{{ route('glint.generations.show', $gen->id) }}"
                           class="row-link-dim"
                           style="font-size:11.5px"
                           onclick="event.stopPropagation()">
                            view
                        </a>
                        <svg class="llm-call-chevron" :class="open ? 'is-open' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                <div class="llm-call-body" x-show="open" style="display:none">
                    @if($gen->error_message)
                        <div style="padding:12px 16px;background:var(--error-bg);border-bottom:1px solid rgba(248,113,113,0.18)">
                            <div style="font-size:11px;font-weight:600;color:var(--error-text);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px">Error</div>
                            <div style="font-family:var(--font-mono);font-size:12px;color:var(--error-text);white-space:pre-wrap">{{ $gen->error_message }}</div>
                        </div>
                    @endif

                    @if($gen->prompt)
                        <div class="llm-msg-section">
                            <div class="llm-msg-label">Prompt</div>
                            @if(is_array($gen->prompt))
                                @foreach($gen->prompt as $msg)
                                    @php
                                        $role = is_array($msg) ? ($msg['role'] ?? 'unknown') : 'message';
                                        $content = is_array($msg) ? ($msg['content'] ?? '') : (string) $msg;
                                        if (is_array($content)) {
                                            $content = collect($content)->map(fn($p) => is_array($p) ? ($p['text'] ?? json_encode($p)) : $p)->implode("\n");
                                        }
                                    @endphp
                                    <div class="llm-msg-item">
                                        <div class="llm-msg-role role-{{ $role }}">{{ $role }}</div>
                                        <div class="llm-msg-content">{{ $content }}</div>
                                    </div>
                                @endforeach
                            @else
                                <div class="llm-msg-item">
                                    <div class="llm-msg-content">{{ $gen->prompt }}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($gen->completion)
                        <div class="llm-msg-section" style="border-top:1px solid var(--border)">
                            <div class="llm-msg-label" style="background:rgba(52,211,153,0.06)">Completion</div>
                            <div class="llm-completion">{{ $gen->completion }}</div>
                        </div>
                    @endif

                    <div style="display:flex;gap:24px;padding:10px 16px;border-top:1px solid var(--border);background:var(--surface-2)">
                        @if($gen->prompt_tokens !== null)
                            <div>
                                <span class="llm-stat-label">Prompt tokens</span>
                                <span class="llm-stat-val">{{ number_format($gen->prompt_tokens) }}</span>
                            </div>
                        @endif
                        @if($gen->completion_tokens !== null)
                            <div>
                                <span class="llm-stat-label">Completion tokens</span>
                                <span class="llm-stat-val">{{ number_format($gen->completion_tokens) }}</span>
                            </div>
                        @endif
                        @if($gen->temperature !== null)
                            <div>
                                <span class="llm-stat-label">Temperature</span>
                                <span class="llm-stat-val">{{ $gen->temperature }}</span>
                            </div>
                        @endif
                        @if($gen->max_tokens !== null)
                            <div>
                                <span class="llm-stat-label">Max tokens</span>
                                <span class="llm-stat-val">{{ number_format($gen->max_tokens) }}</span>
                            </div>
                        @endif
                        @if($gen->top_p !== null)
                            <div>
                                <span class="llm-stat-label">Top P</span>
                                <span class="llm-stat-val">{{ $gen->top_p }}</span>
                            </div>
                        @endif
                        @if($gen->is_streaming)
                            <div>
                                <span class="llm-stat-label">Streaming</span>
                                <span class="llm-stat-val">Yes</span>
                            </div>
                        @endif
                        @if($gen->finish_reason)
                            <div>
                                <span class="llm-stat-label">Finish reason</span>
                                <span class="llm-stat-val">{{ $gen->finish_reason }}</span>
                            </div>
                        @endif
                    </div>

                    @if(!empty($gen->metadata))
                        <div class="llm-msg-section" style="border-top:1px solid var(--border)">
                            <div class="llm-msg-label">Metadata</div>
                            <div class="llm-msg-content" style="padding:12px 16px">{{ json_encode($gen->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    @if($spans->isNotEmpty())
        <div class="section-head">
            <div class="section-title">Spans</div>
            <span class="panel-meta">{{ $spans->count() }} {{ Str::plural('span', $spans->count()) }}</span>
        </div>

        @foreach($spans as $span)
            <div class="span-card" x-data="{ open: false }">
                <div class="span-card-header" @click="open = !open">
                    <div style="display:flex;align-items:center;gap:12px;min-width:0">
                        <span class="badge badge-neutral">{{ $span->type->value }}</span>
                        <span style="font-size:13px;font-weight:500;color:var(--text-1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $span->name }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:14px;flex-shrink:0">
                        @include('glint::partials.status-badge', ['status' => $span->status])
                        <span class="llm-call-stat">{{ $span->duration_ms !== null ? number_format($span->duration_ms).'ms' : '—' }}</span>
                        <span class="llm-call-stat">{{ $span->started_at?->format('H:i:s') ?? '—' }}</span>
                        <svg class="llm-call-chevron" :class="open ? 'is-open' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                <div class="span-card-body" x-show="open" style="display:none">
                    @if($span->input)
                        <div class="llm-msg-label">Input</div>
                        <div class="llm-msg-content" style="padding:12px 16px">{{ $span->input }}</div>
                    @endif
                    @if($span->output)
                        <div class="llm-msg-label" style="border-top:1px solid var(--border)">Output</div>
                        <div class="llm-msg-content" style="padding:12px 16px">{{ $span->output }}</div>
                    @endif
                    @if(!$span->input && !$span->output)
                        <div style="padding:16px;color:var(--text-3);font-size:13px;text-align:center">No input/output recorded for this span.</div>
                    @endif
                    @if(!empty($span->metadata))
                        <div class="llm-msg-label" style="border-top:1px solid var(--border)">Metadata</div>
                        <div class="llm-msg-content" style="padding:12px 16px">{{ json_encode($span->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    @if($generations->isEmpty() && $spans->isEmpty())
        <div class="panel">
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                </svg>
                <div class="empty-title">No activity recorded</div>
                <div class="empty-sub">No generations or spans were recorded for this trace.</div>
            </div>
        </div>
    @endif

@endsection
