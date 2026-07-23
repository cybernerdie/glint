@extends('glint::layout')

@section('page-title', 'New Alert Rule')

@section('breadcrumb')
    <span class="topbar-sep">/</span>
    <a href="{{ route('glint.alerts.index') }}" class="breadcrumb-link">Alerts</a>
    <span class="topbar-sep">/</span>
    <span class="breadcrumb-current">New Rule</span>
@endsection

@section('content')

<div class="page-head" style="max-width:720px;margin-left:auto;margin-right:auto">
    <div>
        <div class="page-title">New Alert Rule</div>
    </div>
</div>

<div class="panel" style="max-width:720px;margin-left:auto;margin-right:auto"
     x-data="{
         type: '{{ old('type', 'cost_threshold') }}',
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
         }
     }">

    <div class="panel-body">
    <form method="POST" action="{{ route('glint.alerts.store') }}">
        @csrf
        <div class="rule-header">
            <div class="rule-name-wrap">
                <label for="name">Rule Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                       class="input" placeholder="e.g. High daily cost" style="width:100%" autocomplete="off">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="rule-status-wrap">
                <span>Status</span>
                <input type="hidden" name="enabled" :value="enabled ? '1' : '0'">
                <div class="toggle-row" style="padding-top:0">
                    <div class="toggle-switch" :class="{ 'is-on': enabled }" @click="enabled = !enabled">
                        <div class="toggle-knob"></div>
                    </div>
                    <span class="toggle-label" x-text="enabled ? 'Enabled' : 'Disabled'"></span>
                </div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-section-title">Condition</div>

            <div class="form-row">
                <label class="form-label" for="type">Alert Type</label>
                <div class="form-field">
                    <select id="type" name="type" class="input" x-model="type" style="max-width:260px">
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
                <label class="form-label" for="threshold">Threshold</label>
                <div class="form-field">
                    <input id="threshold" type="number" name="threshold" value="{{ old('threshold') }}"
                           class="input" placeholder="e.g. 10" min="0" step="any" style="max-width:160px">
                    <div class="form-hint" x-text="thresholdHint()"></div>
                    @error('threshold')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="period">Evaluation Period</label>
                <div class="form-field">
                    <select id="period" name="period" class="input" style="max-width:160px">
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
        <div class="form-section">
            <div class="form-section-title">Target</div>

            <div class="form-row">
                <label class="form-label" for="provider_filter">Provider</label>
                <div class="form-field">
                    <select id="provider_filter" name="provider" class="input" style="max-width:200px">
                        <option value="">All providers</option>
                        @foreach($providers as $p)
                            <option value="{{ $p }}" @selected(old('provider') === $p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                    @error('provider')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-section-title">Notify</div>

            <div class="form-row">
                <div class="form-label">Channels</div>
                <div class="form-field">
                    <div class="check-group">
                        <label class="check-item">
                            <input type="checkbox" name="channels[]" value="log"
                                   :checked="hasChannel('log')" @change="toggleChannel('log')">
                            <div>
                                <div class="check-label">Log</div>
                                <div class="check-sub">Written to your application log. Always available.</div>
                            </div>
                        </label>
                        <label class="check-item">
                            <input type="checkbox" name="channels[]" value="mail"
                                   :checked="hasChannel('mail')" @change="toggleChannel('mail')">
                            <div>
                                <div class="check-label">Mail</div>
                                <div class="check-sub">Sends an email via Laravel's mail driver. Requires <code style="font-family:var(--font-mono);font-size:11px">illuminate/mail</code>.</div>
                            </div>
                        </label>
                        <label class="check-item">
                            <input type="checkbox" name="channels[]" value="slack"
                                   :checked="hasChannel('slack')" @change="toggleChannel('slack')">
                            <div>
                                <div class="check-label">Slack</div>
                                <div class="check-sub">Posts to a Slack channel. Requires <code style="font-family:var(--font-mono);font-size:11px">laravel/slack-notification-channel</code>.</div>
                            </div>
                        </label>
                        <label class="check-item">
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
                           class="input" placeholder="alerts@example.com" style="max-width:320px">
                    @error('mail_to')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row" x-show="hasChannel('slack')" x-cloak>
                <label class="form-label" for="slack_webhook_url">Slack Webhook URL</label>
                <div class="form-field">
                    <input id="slack_webhook_url" type="url" name="slack_webhook_url" value="{{ old('slack_webhook_url') }}"
                           class="input" placeholder="https://hooks.slack.com/services/..." style="max-width:440px">
                    <div class="form-hint">Create an Incoming Webhook in your Slack app settings.</div>
                    @error('slack_webhook_url')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row" x-show="hasChannel('webhook')" x-cloak>
                <label class="form-label" for="webhook_url">Webhook URL</label>
                <div class="form-field">
                    <input id="webhook_url" type="url" name="webhook_url" value="{{ old('webhook_url') }}"
                           class="input" placeholder="https://hooks.example.com/..." style="max-width:440px">
                    @error('webhook_url')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

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
</div>

@endsection
