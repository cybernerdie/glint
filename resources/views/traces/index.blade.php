@extends('glint::layout')

@section('page-title', 'Traces')
@section('refresh-interval', 5)

@section('content')

    <form method="GET" action="{{ route('glint.traces.index') }}">

    <div class="page-head">
        <div>
            <div class="page-title">Traces</div>
            <div class="page-desc">Request-level trace history</div>
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
            <div class="search-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <label for="traces-search" class="sr-only">Search by name</label>
                <input
                    id="traces-search"
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search by name..."
                    class="input"
                    style="width:220px"
                    autocomplete="off"
                >
            </div>

            <label for="traces-user" class="sr-only">Filter by user ID</label>
            <input
                id="traces-user"
                type="text"
                name="user_id"
                value="{{ $userId }}"
                placeholder="User ID..."
                class="input"
                style="width:150px"
                autocomplete="off"
            >

            <input type="hidden" name="status" id="traces-status-val" value="{{ $status }}">
            <div class="status-pills" role="group" aria-label="Filter by status" style="margin-left:auto">
                <button type="button"
                        class="status-pill {{ !$status ? 'is-active' : '' }}"
                        onclick="document.getElementById('traces-status-val').value=''; this.closest('form').requestSubmit()">All</button>
                @foreach($statuses as $s)
                    <button type="button"
                            class="status-pill {{ $status === $s->value ? 'is-active' : '' }}"
                            onclick="document.getElementById('traces-status-val').value='{{ $s->value }}'; this.closest('form').requestSubmit()">
                        {{ ucfirst($s->value) }}
                    </button>
                @endforeach
            </div>
        </div>
        @if(method_exists($traces, 'isEmpty') ? $traces->isEmpty() : count($traces) === 0)
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
                <div class="empty-title">No traces found</div>
                <div class="empty-sub">
                    Traces will appear here once your app makes LLM calls.
                </div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Trace</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>User</th>
                        <th class="num">Duration</th>
                        <th class="num">Started</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($traces as $trace)
                        <tr onclick="if (!event.target.closest('a')) glintVisit('{{ route('glint.traces.show', $trace->id) }}')">
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="id-chip">
                                    {{ substr($trace->id, 0, 8) }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="row-link">
                                    {{ $trace->name ?: 'Unnamed' }}
                                </a>
                            </td>
                            <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                            <td class="t-muted t-mono">{{ $trace->user_id ?? '—' }}</td>
                            <td class="t-muted t-mono num">
                                {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                            </td>
                            <td class="t-dim t-mono num" style="font-size:11.5px">
                                {{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($traces, 'links'))
                <div class="pagination">
                    {{ $traces->links('glint::pagination') }}
                </div>
            @endif
        @endif
    </div>

    </form>

@endsection
