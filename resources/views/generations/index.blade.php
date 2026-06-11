@extends('glint::layout')

@section('page-title', 'Generations')
@section('refresh-interval', 5)

@section('content')

    <form method="GET" action="{{ route('glint.generations.index') }}">

    <div class="page-head">
        <div>
            <div class="page-title">Generations</div>
            <div class="page-desc">Individual LLM API calls</div>
        </div>
        <div class="page-toolbar">
            @include('glint::partials.filters.date-range', [
                'activePeriod' => $period,
                'activeFrom'   => $fromDate,
                'activeTo'     => $toDate,
            ])
        </div>
    </div>

    <div class="panel">
        <div class="panel-toolbar">
            <label for="gen-provider" class="sr-only">Filter by provider</label>
            <select id="gen-provider" name="provider" class="input" onchange="this.form.requestSubmit()">
                <option value="">All providers</option>
                @foreach($providers as $p)
                    <option value="{{ $p }}" {{ $provider === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>

            <label for="gen-model" class="sr-only">Filter by model</label>
            <select id="gen-model" name="model" class="input" onchange="this.form.requestSubmit()">
                <option value="">All models</option>
                @foreach($models as $m)
                    <option value="{{ $m }}" {{ $model === $m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>

            <input type="hidden" name="status" id="gen-status-val" value="{{ $status }}">
            <div class="status-pills" role="group" aria-label="Filter by status" style="margin-left:auto">
                <button type="button"
                        class="status-pill {{ !$status ? 'is-active' : '' }}"
                        onclick="document.getElementById('gen-status-val').value=''; this.closest('form').requestSubmit()">All</button>
                @foreach($statuses as $s)
                    <button type="button"
                            class="status-pill {{ $status === $s->value ? 'is-active' : '' }}"
                            onclick="document.getElementById('gen-status-val').value='{{ $s->value }}'; this.closest('form').requestSubmit()">
                        {{ ucfirst($s->value) }}
                    </button>
                @endforeach
            </div>
        </div>
        @if(method_exists($generations, 'isEmpty') ? $generations->isEmpty() : count($generations) === 0)
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                </svg>
                <div class="empty-title">No generations found</div>
                <div class="empty-sub">
                    Generations appear here once your app makes LLM API calls.
                </div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Model</th>
                        <th class="num">Tokens</th>
                        <th class="num">Cost</th>
                        <th class="num">Duration</th>
                        <th>Status</th>
                        <th class="num">Started</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($generations as $gen)
                        <tr onclick="if (!event.target.closest('a')) glintVisit('{{ route('glint.generations.show', $gen->id) }}')">
                            <td>
                                <a href="{{ route('glint.generations.show', $gen->id) }}" class="id-chip">
                                    {{ substr($gen->id, 0, 8) }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('glint.generations.show', $gen->id) }}" class="row-link">
                                    {{ $gen->name ?: '—' }}
                                </a>
                            </td>
                            <td>
                                <div class="t-mono" style="font-size:12px">{{ $gen->model }}</div>
                                <div class="t-sub">{{ $gen->provider }}</div>
                            </td>
                            <td class="t-muted t-mono num">{{ $gen->total_tokens !== null ? number_format($gen->total_tokens) : '—' }}</td>
                            <td class="t-mono t-cost num">{{ $gen->cost_usd !== null ? '$'.number_format($gen->cost_usd, 6) : '—' }}</td>
                            <td class="t-muted t-mono num">{{ $gen->duration_ms !== null ? number_format($gen->duration_ms).'ms' : '—' }}</td>
                            <td>@include('glint::partials.status-badge', ['status' => $gen->status])</td>
                            <td class="t-dim t-mono num" style="font-size:11.5px">{{ $gen->started_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($generations, 'links'))
                <div class="pagination">
                    {{ $generations->links('glint::pagination') }}
                </div>
            @endif
        @endif
    </div>

    </form>

@endsection
