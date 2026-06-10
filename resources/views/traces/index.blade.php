@extends('glint::layout')

@section('page-title', 'Traces')
@section('refresh-interval', 5)

@section('content')

    <div class="page-header">
        <div class="page-title">Traces</div>
        <div class="page-subtitle">Request-level trace history</div>
    </div>

    {{-- Search bar --}}
    <form method="GET" action="{{ route('glint.traces.index') }}" class="filter-bar">
        <label for="traces-search" class="sr-only">Search traces by name</label>
        <input
            id="traces-search"
            type="text"
            name="search"
            value="{{ $search }}"
            placeholder="Search by name..."
            class="input"
            style="width:260px"
        >
        <button type="submit" class="btn btn-primary">Search</button>
        @if($search)
            <a href="{{ route('glint.traces.index') }}" class="btn btn-ghost">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <div class="table-header">
            <span class="table-title">
                Traces
                @if($search)
                    &mdash; matching <em style="color:var(--accent-h)">"{{ $search }}"</em>
                @endif
            </span>
        </div>

        @if(method_exists($traces, 'isEmpty') ? $traces->isEmpty() : count($traces) === 0)
            <div class="empty-state">
                <div class="empty-state-icon">&#128270;</div>
                <div class="empty-state-text">No traces found.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Trace ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>User ID</th>
                        <th>Duration</th>
                        <th>Started At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($traces as $trace)
                        <tr>
                            <td class="td-mono td-muted">
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="link td-mono">
                                    {{ substr($trace->id, 0, 8) }}&hellip;
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('glint.traces.show', $trace->id) }}" class="link">
                                    {{ $trace->name ?: 'Unnamed' }}
                                </a>
                            </td>
                            <td>@include('glint::partials.status-badge', ['status' => $trace->status])</td>
                            <td class="td-muted">{{ $trace->user_id ?? '—' }}</td>
                            <td class="td-muted">
                                {{ $trace->duration_ms !== null ? number_format($trace->duration_ms).'ms' : '—' }}
                            </td>
                            <td class="td-muted">
                                {{ $trace->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($traces, 'links'))
                <div class="pagination">
                    {{ $traces->links('glint::cursor-pagination') }}
                </div>
            @endif
        @endif
    </div>

@endsection
