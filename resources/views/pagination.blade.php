@php
    $first = $paginator->firstItem();
    $last  = $paginator->lastItem();
    $total = $paginator->total();
@endphp

@if($total > 0 || $paginator->hasPages())
    @if($total > 0)
        <span class="pagination-count">
            {{ number_format($first) }}–{{ number_format($last) }} of {{ number_format($total) }}
        </span>
    @else
        <span></span>
    @endif

    @if($paginator->hasPages())
        <nav aria-label="Pagination">
            <ul style="display:flex;gap:4px;list-style:none;padding:0;margin:0;flex-wrap:wrap">
                @if($paginator->onFirstPage())
                    <li class="disabled">
                        <span aria-hidden="true">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </span>
                    </li>
                @else
                    <li>
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                    </li>
                @endif

                @foreach($elements as $element)
                    @if(is_string($element))
                        <li class="disabled"><span>{{ $element }}</span></li>
                    @endif

                    @if(is_array($element))
                        @foreach($element as $page => $url)
                            @if($page == $paginator->currentPage())
                                <li class="active"><span>{{ $page }}</span></li>
                            @else
                                <li><a href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if($paginator->hasMorePages())
                    <li>
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </li>
                @else
                    <li class="disabled">
                        <span aria-hidden="true">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </li>
                @endif
            </ul>
        </nav>
    @endif
@endif
