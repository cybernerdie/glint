@extends('glint::layout')

@section('page-title', 'Users')

@section('content')

    <form method="GET" action="{{ route('glint.users.index') }}">

    <div class="page-head">
        <div>
            <div class="page-title">Users</div>
            <div class="page-desc">LLM activity and cost breakdown by user</div>
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
            </div>

            @if(method_exists($users, 'total'))
                <span class="panel-meta" style="margin-left:auto">{{ number_format($users->total()) }} {{ Str::plural('user', $users->total()) }}</span>
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
                    Users appear here once your app passes a <code style="font-family:var(--font-mono);font-size:12px">user_id</code> when recording traces.
                </div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th class="num">Traces</th>
                        <th class="num">Tokens</th>
                        <th class="num">Total Cost</th>
                        <th class="num">Avg Duration</th>
                        <th class="num">Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr onclick="if (!event.target.closest('a')) glintVisit('{{ route('glint.users.show', $user->user_id) }}')">
                            <td>
                                <a href="{{ route('glint.users.show', $user->user_id) }}" class="row-link-mono">
                                    {{ $user->user_id }}
                                </a>
                            </td>
                            <td class="t-muted t-mono num">{{ number_format($user->trace_count) }}</td>
                            <td class="t-muted t-mono num">{{ $user->total_tokens !== null ? number_format($user->total_tokens) : '—' }}</td>
                            <td class="t-mono t-cost num">
                                {{ $user->total_cost !== null ? '$'.number_format((float) $user->total_cost, 6) : '—' }}
                            </td>
                            <td class="t-muted t-mono num">
                                {{ $user->avg_duration !== null ? number_format((float) $user->avg_duration).'ms' : '—' }}
                            </td>
                            <td class="t-dim num" style="font-size:12px">
                                {{ $user->last_seen ? \Illuminate\Support\Carbon::parse($user->last_seen)->diffForHumans(short: true) : '—' }}
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

    </form>

@endsection
