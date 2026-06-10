@extends('glint::layout')

@section('page-title', 'Trace Detail')

@section('content')

    <nav class="breadcrumb">
        <a href="{{ route('glint.dashboard') }}" class="breadcrumb-link">Glint</a>
        <span class="breadcrumb-sep">/</span>
        <a href="{{ route('glint.traces.index') }}" class="breadcrumb-link">Traces</a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current">{{ substr($trace->id, 0, 16) }}&hellip;</span>
    </nav>

    <div class="page-header">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="page-title">{{ $trace->name ?: 'Unnamed Trace' }}</div>
            @include('glint::partials.status-badge', ['status' => $trace->status])
        </div>
        <div class="page-desc" style="font-family:var(--font-mono);font-size:12px;margin-top:6px;color:var(--text-3)">
            {{ $trace->id }}
        </div>
    </div>

    {{-- Trace info card --}}
    <div class="info-card">
        <div class="info-card-header">
            <span class="info-card-title">Trace Info</span>
        </div>
        <div class="info-card-body">
            <div class="field-grid">
                <div class="field">
                    <label>Started At</label>
                    <div class="field-val-mono">{{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>Ended At</label>
                    <div class="field-val-mono">{{ $trace->ended_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>Duration</label>
                    <div class="field-val-mono">{{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}</div>
                </div>
                <div class="field">
                    <label>User ID</label>
                    <div class="field-val-muted">{{ $trace->user_id ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>Session ID</label>
                    <div class="field-val-muted">{{ $trace->session_id ?? '—' }}</div>
                </div>
                <div class="field">
                    <label>Team ID</label>
                    <div class="field-val-muted">{{ $trace->team_id ?? '—' }}</div>
                </div>
            </div>

            @php $traceTags = $trace->metadata['tags'] ?? []; @endphp
            @if(!empty($traceTags))
                <div class="body-divider"></div>
                <div style="font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px">Tags</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    @foreach($traceTags as $tagKey => $tagVal)
                        <span class="tag"><span class="tag-key">{{ $tagKey }}</span>{{ $tagVal }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Trace input/output --}}
    @if($trace->input ?? false)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Input</span>
                <button type="button" class="copy-btn" onclick="glintCopy(this)">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="copy-label">Copy</span>
                </button>
            </div>
            <div class="code-body">{{ $trace->input }}</div>
        </div>
    @endif

    @if($trace->output ?? false)
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Output</span>
                <button type="button" class="copy-btn" onclick="glintCopy(this)">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="copy-label">Copy</span>
                </button>
            </div>
            <div class="code-body">{{ $trace->output }}</div>
        </div>
    @endif

    @if(!empty($trace->metadata))
        <div class="code-card">
            <div class="code-header">
                <span class="code-label">Metadata</span>
                <button type="button" class="copy-btn" onclick="glintCopy(this)">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="copy-label">Copy</span>
                </button>
            </div>
            <div class="code-body">{{ json_encode($trace->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</div>
        </div>
    @endif

    {{-- LLM Calls --}}
    @if($generations->isNotEmpty())
        <div style="margin-bottom:24px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <div style="font-size:14px;font-weight:600;color:var(--text-1)">LLM Calls</div>
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
                                <span class="llm-call-stat" style="color:var(--accent)">${{ number_format($gen->cost_usd, 6) }}</span>
                            @endif
                            @if($gen->duration_ms !== null)
                                <span class="llm-call-stat">{{ number_format($gen->duration_ms) }}ms</span>
                            @endif
                            <a href="{{ route('glint.generations.show', $gen->id) }}"
                               style="font-size:11.5px;color:var(--text-3);text-decoration:none;font-family:var(--font-mono)"
                               onclick="event.stopPropagation()"
                               onmouseover="this.style.color='var(--accent)'"
                               onmouseout="this.style.color='var(--text-3)'">
                                view
                            </a>
                            <svg class="llm-call-chevron" :class="open ? 'is-open' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <div class="llm-call-body" x-show="open" style="display:none">
                        {{-- Error --}}
                        @if($gen->error_message)
                            <div style="padding:12px 16px;background:#FFF5F5;border-bottom:1px solid #FED7D7">
                                <div style="font-size:11px;font-weight:600;color:var(--error-text);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px">Error</div>
                                <div style="font-family:var(--font-mono);font-size:12px;color:var(--error-text);white-space:pre-wrap">{{ $gen->error_message }}</div>
                            </div>
                        @endif

                        {{-- Prompt messages --}}
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

                        {{-- Completion --}}
                        @if($gen->completion)
                            <div class="llm-msg-section" style="border-top:1px solid var(--border)">
                                <div class="llm-msg-label" style="background:rgba(22,163,74,0.06)">Completion</div>
                                <div class="llm-completion">{{ $gen->completion }}</div>
                            </div>
                        @endif

                        {{-- Stats footer --}}
                        <div style="display:flex;gap:24px;padding:10px 16px;border-top:1px solid var(--border);background:var(--surface-2)">
                            @if($gen->prompt_tokens !== null)
                                <div>
                                    <span style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.06em">Prompt tokens</span>
                                    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-1);margin-left:6px">{{ number_format($gen->prompt_tokens) }}</span>
                                </div>
                            @endif
                            @if($gen->completion_tokens !== null)
                                <div>
                                    <span style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.06em">Completion tokens</span>
                                    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-1);margin-left:6px">{{ number_format($gen->completion_tokens) }}</span>
                                </div>
                            @endif
                            @if($gen->temperature !== null)
                                <div>
                                    <span style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.06em">Temperature</span>
                                    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-1);margin-left:6px">{{ $gen->temperature }}</span>
                                </div>
                            @endif
                            @if($gen->finish_reason)
                                <div>
                                    <span style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.06em">Finish reason</span>
                                    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-1);margin-left:6px">{{ $gen->finish_reason }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Spans --}}
    @if($spans->isNotEmpty())
        <div style="margin-bottom:24px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <div style="font-size:14px;font-weight:600;color:var(--text-1)">Spans</div>
                <span class="panel-meta">{{ $spans->count() }} {{ Str::plural('span', $spans->count()) }}</span>
            </div>

            @foreach($spans as $span)
                <div class="span-card" x-data="{ open: false }">
                    <div class="span-card-header" @click="open = !open">
                        <div style="display:flex;align-items:center;gap:12px;min-width:0">
                            <span class="badge badge-neutral"><span class="badge-dot"></span>{{ $span->type->value }}</span>
                            <span style="font-size:13.5px;font-weight:500;color:var(--text-1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $span->name }}</span>
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
        </div>
    @endif

    @if($generations->isEmpty() && $spans->isEmpty())
        <div class="info-card">
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
