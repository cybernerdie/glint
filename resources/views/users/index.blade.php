@extends('glint::layout')

@section('page-title', 'Users')

@section('content')

    <div class="page-header">
        <div class="page-header-row">
            <div>
                <div class="page-title">Users</div>
                <div class="page-desc">LLM activity and cost breakdown by user</div>
            </div>
            <form method="GET" action="{{ route('glint.users.index') }}">
                @include('glint::partials.filters.date-range', [
                    'activePeriod' => $period,
                    'activeFrom'   => $fromDate,
                    'activeTo'     => $toDate,
                ])
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('glint.users.index') }}" class="filter-bar">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="from" value="{{ $fromDate }}">
        <input type="hidden" name="to" value="{{ $toDate }}">

        <label for="users-search" class="sr-only">Search by user ID</label>
        <input
            id="users-search"
            type="text"
            name="search"
            value="{{ $search }}"
            placeholder="Search user ID..."
            class="input"
            style="width:220px"
            autocomplete="off"
        >

        @if($search)
            <a href="{{ route('glint.users.index', array_merge(request()->query(), ['search' => ''])) }}" class="filter-chip">
                "{{ $search }}" <span class="filter-chip-x">&times;</span>
            </a>
        @endif
        @if($search || $period !== 'today')
            <a href="{{ route('glint.users.index') }}" class="filter-chip" style="background:rgba(0,0,0,0.04);border-color:var(--border);color:var(--text-2)">
                Clear all <span class="filter-chip-x">&times;</span>
            </a>
        @endif
    </form>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Users</span>
            @if(method_exists($users, 'total'))
                <span class="panel-meta">{{ number_format($users->total()) }} {{ Str::plural('user', $users->total()) }}</span>
            @endif
        </div>

        @php $isEmpty = method_exists($users, 'isEmpty') ? $users->isEmpty() : count($users) === 0; @endphp

        @if($isEmpty)
            <div class="empty">
                <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
                <div class="empty-title">No users found</div>
                <div class="empty-sub">
                    @if($search || $period)
                        Try adjusting your filters, or <a href="{{ route('glint.users.index') }}" class="text-link">clear them</a>.
                    @else
                        Users appear here once your app passes a <code style="font-family:var(--font-mono);font-size:12px">user_id</code> when recording traces.
                    @endif
                </div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Traces</th>
                        <th>Tokens</th>
                        <th>Total Cost</th>
                        <th>Avg Duration</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr onclick="window.location.href='{{ route('glint.users.show', $user->user_id) }}'"
>
                            <td>
                                <a href="{{ route('glint.users.show', $user->user_id) }}" class="row-link-mono">
                                    {{ $user->user_id }}
                                </a>
                            </td>
                            <td class="t-muted t-mono">{{ number_format($user->trace_count) }}</td>
                            <td class="t-muted t-mono">{{ $user->total_tokens !== null ? number_format($user->total_tokens) : '—' }}</td>
                            <td class="t-mono" style="color:var(--accent);font-weight:600">
                                {{ $user->total_cost !== null ? '$'.number_format((float) $user->total_cost, 6) : '—' }}
                            </td>
                            <td class="t-muted t-mono">
                                {{ $user->avg_duration !== null ? number_format((float) $user->avg_duration).'ms' : '—' }}
                            </td>
                            <td class="t-muted" style="font-size:12.5px">
                                {{ $user->last_seen ? \Illuminate\Support\Carbon::parse($user->last_seen)->diffForHumans() : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($users, 'links'))
                <div class="pagination">
                    {{ $users->links('glint::pagination') }}
                </div>
            @endif
        @endif
    </div>

@endsection
