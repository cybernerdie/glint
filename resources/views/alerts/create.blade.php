@extends('glint::layout')

@section('page-title', 'New Alert Rule')

@section('breadcrumb')
    <span class="topbar-sep">/</span>
    <a href="{{ route('glint.alerts.index') }}" class="breadcrumb-link">Alerts</a>
    <span class="topbar-sep">/</span>
    <span class="breadcrumb-current">New Rule</span>
@endsection

@section('content')

<style>
    .form-section { margin-bottom: 32px; }
    .form-section-title {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-3);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
    }
    .form-row {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 12px 24px;
        align-items: start;
        margin-bottom: 18px;
    }
    @media (max-width: 640px) {
        .form-row { grid-template-columns: 1fr; gap: 6px; }
    }
    .form-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-2);
        padding-top: 9px;
        line-height: 1.4;
    }
    .form-hint {
        font-size: 11.5px;
        color: var(--text-3);
        margin-top: 5px;
        line-height: 1.5;
    }
    .form-error {
        font-size: 11.5px;
        color: var(--error-text);
        margin-top: 5px;
    }
    .form-field { display: flex; flex-direction: column; }
    .input-group { display: flex; align-items: center; gap: 8px; }
    .input-suffix {
        font-size: 12.5px;
        color: var(--text-3);
        font-family: var(--font-mono);
        white-space: nowrap;
    }
    .check-group { display: flex; flex-direction: column; gap: 10px; padding-top: 4px; }
    .check-item { display: flex; align-items: center; gap: 10px; cursor: pointer; }
    .check-item input[type="checkbox"] {
        width: 16px; height: 16px;
        accent-color: var(--accent);
        cursor: pointer;
        flex-shrink: 0;
    }
    .check-label { font-size: 13px; color: var(--text-1); font-weight: 500; }
    .check-sub { font-size: 11.5px; color: var(--text-3); }
    .toggle-row { display: flex; align-items: center; gap: 12px; padding-top: 6px; }
    .toggle-switch {
        position: relative; width: 40px; height: 22px;
        background: var(--border-strong); border-radius: 9999px;
        cursor: pointer; transition: background 0.15s; flex-shrink: 0;
    }
    .toggle-switch.is-on { background: var(--accent); }
    .toggle-knob {
        position: absolute; top: 3px; left: 3px;
        width: 16px; height: 16px; background: #fff;
        border-radius: 50%; transition: transform 0.15s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    .toggle-switch.is-on .toggle-knob { transform: translateX(18px); }
    .toggle-label { font-size: 13px; color: var(--text-2); }
</style>

<div class="page-head">
    <div>
        <div class="page-title">New Alert Rule</div>
    </div>
</div>

<div class="panel"
     x-data="{
         type: '{{ old('type', 'cost_threshold') }}',
         scope: '{{ old('scope', 'global') }}',
         channels: {{ json_encode(old('channels', [])) }},
         enabled: {{ old('enabled', '1') === '1' ? 'true' : 'false' }},
         thresholdHint() {
             const map = {
                 cost_threshold: 'USD — e.g. 10.00 triggers when daily cost exceeds $10',
                 error_rate: '% — e.g. 5 triggers when error rate exceeds 5%',
                 latency_spike: 'ms — e.g. 2000 triggers when avg latency exceeds 2,000 ms',
                 token_spike: 'tokens — e.g. 500000 triggers when token usage exceeds 500K',
             };
             return map[this.type] ?? '';
         },
         hasChannel(ch) { return this.channels.includes(ch); },
         toggleChannel(ch) {
             if (this.hasChannel(ch)) {
                 this.channels = this.channels.filter(c => c !== ch);
             } else {
                 this.channels.push(ch);
             }
         },
         scopeNeedsId() { return ['user', 'team', 'provider', 'model'].includes(this.scope); },
         scopeIdLabel() {
             const map = { user: 'User ID', team: 'Team ID', provider: 'Provider name', model: 'Model name' };
             return map[this.scope] ?? 'Scope ID';
         }
     }">

    <form method="POST" action="{{ route('glint.alerts.store') }}">
        @csrf

        {{-- Basic --}}
        <div class="form-section">
            <div class="form-section-title">Basic</div>

            <div class="form-row">
                <label class="form-label" for="name">Rule Name</label>
                <div class="form-field">
                    <input id="name" type="text" name="name" value="{{ old('name') }}"
                           class="input" placeholder="e.g. High daily cost" style="max-width:480px" autocomplete="off">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-label">Status</div>
                <div class="form-field">
                    <input type="hidden" name="enabled" :value="enabled ? '1' : '0'">
                    <div class="toggle-row">
                        <div class="toggle-switch" :class="{ 'is-on': enabled }" @click="enabled = !enabled">
                            <div class="toggle-knob"></div>
                        </div>
                        <span class="toggle-label" x-text="enabled ? 'Enabled' : 'Disabled'"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Threshold --}}
        <div class="form-section">
            <div class="form-section-title">Threshold</div>

            <div class="form-row">
                <label class="form-label" for="type">Alert Type</label>
                <div class="form-field">
                    <select id="type" name="type" class="input" x-model="type" style="max-width:280px">
                        @foreach($types as $t)
                            <option value="{{ $t->value }}">
                                {{ match($t->value) {
                                    'cost_threshold' => 'Cost Threshold',
                                    'error_rate'     => 'Error Rate',
                                    'latency_spike'  => 'Latency Spike',
                                    'token_spike'    => 'Token Spike',
                                    default          => $t->value,
                                } }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="threshold">Threshold Value</label>
                <div class="form-field">
                    <div class="input-group">
                        <input id="threshold" type="number" name="threshold" value="{{ old('threshold') }}"
                               class="input" placeholder="0" min="0" step="any" style="max-width:200px">
                        <span class="input-suffix" x-text="thresholdHint()"></span>
                    </div>
                    @error('threshold')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="period">Evaluation Period</label>
                <div class="form-field">
                    <select id="period" name="period" class="input" style="max-width:180px">
                        @foreach($periods as $p)
                            <option value="{{ $p->value }}" @selected(old('period', 'day') === $p->value)>
                                {{ ucfirst($p->value) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-hint">Glint checks the most recent aggregate for this window.</div>
                    @error('period')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Scope --}}
        <div class="form-section">
            <div class="form-section-title">Scope</div>

            <div class="form-row">
                <label class="form-label" for="scope">Scope</label>
                <div class="form-field">
                    <select id="scope" name="scope" class="input" x-model="scope" style="max-width:220px">
                        @foreach($scopes as $s)
                            <option value="{{ $s->value }}">{{ ucfirst($s->value) }}</option>
                        @endforeach
                    </select>
                    <div class="form-hint">Global watches all traffic. Narrow by user, team, provider, or model.</div>
                    @error('scope')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row" x-show="scopeNeedsId()" x-cloak>
                <label class="form-label" for="scope_id" x-text="scopeIdLabel()"></label>
                <div class="form-field">
                    <input id="scope_id" type="text" name="scope_id" value="{{ old('scope_id') }}"
                           class="input" :placeholder="scopeIdLabel()" style="max-width:320px" autocomplete="off">
                    @error('scope_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="provider_filter">Provider Filter</label>
                <div class="form-field">
                    <input id="provider_filter" type="text" name="provider" value="{{ old('provider') }}"
                           class="input" placeholder="e.g. openai (optional)" style="max-width:220px" autocomplete="off">
                    <div class="form-hint">Leave blank to match all providers in the selected scope.</div>
                    @error('provider')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Channels --}}
        <div class="form-section">
            <div class="form-section-title">Notification Channels</div>

            <div class="form-row">
                <div class="form-label">Channels</div>
                <div class="form-field">
                    <div class="check-group">
                        <label class="check-item" @click.prevent="toggleChannel('log')">
                            <input type="checkbox" name="channels[]" value="log"
                                   :checked="hasChannel('log')" @change="toggleChannel('log')">
                            <div>
                                <div class="check-label">Log</div>
                                <div class="check-sub">Written to your application log. Always available.</div>
                            </div>
                        </label>
                        <label class="check-item" @click.prevent="toggleChannel('mail')">
                            <input type="checkbox" name="channels[]" value="mail"
                                   :checked="hasChannel('mail')" @change="toggleChannel('mail')">
                            <div>
                                <div class="check-label">Mail</div>
                                <div class="check-sub">Sends an email via Laravel's mail driver. Requires <code style="font-family:var(--font-mono);font-size:11px">illuminate/mail</code>.</div>
                            </div>
                        </label>
                        <label class="check-item" @click.prevent="toggleChannel('slack')">
                            <input type="checkbox" name="channels[]" value="slack"
                                   :checked="hasChannel('slack')" @change="toggleChannel('slack')">
                            <div>
                                <div class="check-label">Slack</div>
                                <div class="check-sub">Posts to a Slack channel. Requires <code style="font-family:var(--font-mono);font-size:11px">laravel/slack-notification-channel</code>.</div>
                            </div>
                        </label>
                        <label class="check-item" @click.prevent="toggleChannel('webhook')">
                            <input type="checkbox" name="channels[]" value="webhook"
                                   :checked="hasChannel('webhook')" @change="toggleChannel('webhook')">
                            <div>
                                <div class="check-label">Webhook</div>
                                <div class="check-sub">POSTs a JSON payload to any URL.</div>
                            </div>
                        </label>
                    </div>
                    @error('channels')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row" x-show="hasChannel('mail')" x-cloak>
                <label class="form-label" for="mail_to">Mail To</label>
                <div class="form-field">
                    <input id="mail_to" type="email" name="mail_to" value="{{ old('mail_to') }}"
                           class="input" placeholder="alerts@example.com" style="max-width:360px">
                    @error('mail_to')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row" x-show="hasChannel('webhook')" x-cloak>
                <label class="form-label" for="webhook_url">Webhook URL</label>
                <div class="form-field">
                    <input id="webhook_url" type="url" name="webhook_url" value="{{ old('webhook_url') }}"
                           class="input" placeholder="https://hooks.example.com/..." style="max-width:480px">
                    @error('webhook_url')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Settings --}}
        <div class="form-section">
            <div class="form-section-title">Settings</div>

            <div class="form-row">
                <label class="form-label" for="cooldown_minutes">Cooldown</label>
                <div class="form-field">
                    <div class="input-group">
                        <input id="cooldown_minutes" type="number" name="cooldown_minutes"
                               value="{{ old('cooldown_minutes', 60) }}"
                               class="input" min="1" max="10080" style="max-width:120px">
                        <span class="input-suffix">minutes</span>
                    </div>
                    <div class="form-hint">Minimum time between repeated notifications for the same rule.</div>
                    @error('cooldown_minutes')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;padding-top:4px">
            <button type="submit" class="btn btn-primary">Save Rule</button>
            <a href="{{ route('glint.alerts.index') }}" class="btn btn-ghost">Cancel</a>
        </div>

    </form>
</div>

@endsection
