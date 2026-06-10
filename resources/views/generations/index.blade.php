@extends('glint::layout')

@section('page-title', 'Generations')
@section('refresh-interval', 5)

@section('content')

    <div class="page-header">
        <div class="page-title">Generations</div>
        <div class="page-subtitle">Individual LLM API calls</div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('glint.generations.index') }}" class="filter-bar">
        <label for="gen-provider" class="sr-only">Filter by provider</label>
        <select id="gen-provider" name="provider" class="input">
            <option value="">All providers</option>
            @foreach($providers as $p)
                <option value="{{ $p }}" {{ $provider === $p ? 'selected' : '' }}>{{ $p }}</option>
            @endforeach
        </select>

        <label for="gen-model" class="sr-only">Filter by model</label>
        <select id="gen-model" name="model" class="input">
            <option value="">All models</option>
            @foreach($models as $m)
                <option value="{{ $m }}" {{ $model === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>

        @if($provider || $model)
            <a href="{{ route('glint.generations.index') }}" class="btn btn-ghost">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <div class="table-header">
            <span class="table-title">Generations</span>
        </div>

        @if(method_exists($generations, 'isEmpty') ? $generations->isEmpty() : count($generations) === 0)
            <div class="empty-state">
                <div class="empty-state-icon">&#128270;</div>
                <div class="empty-state-text">No generations found.</div>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Started At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($generations as $gen)
                        <tr>
                            <td class="td-mono">
                                <a href="{{ route('glint.generations.show', $gen->id) }}" class="link td-mono">
                                    {{ substr($gen->id, 0, 8) }}&hellip;
                                </a>
                            </td>
                            <td>{{ $gen->provider }}</td>
                            <td class="td-mono">{{ $gen->model }}</td>
                            <td class="td-muted">{{ $gen->total_tokens !== null ? number_format($gen->total_tokens) : '—' }}</td>
                            <td class="td-muted">{{ $gen->cost_usd !== null ? '$'.number_format($gen->cost_usd, 6) : '—' }}</td>
                            <td class="td-muted">{{ $gen->duration_ms !== null ? number_format($gen->duration_ms).'ms' : '—' }}</td>
                            <td>@include('glint::partials.status-badge', ['status' => $gen->status])</td>
                            <td class="td-muted">{{ $gen->started_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($generations, 'links'))
                <div class="pagination">
                    {{ $generations->links('glint::cursor-pagination') }}
                </div>
            @endif
        @endif
    </div>

@endsection
