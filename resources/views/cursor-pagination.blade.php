@if ($paginator->hasPages())
    <nav aria-label="Pagination">
        <ul style="display:flex;gap:6px;list-style:none;padding:0;margin:0">
            @if ($paginator->onFirstPage())
                <li class="disabled"><span>&laquo; Prev</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Prev</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Next &raquo;</a></li>
            @else
                <li class="disabled"><span>Next &raquo;</span></li>
            @endif
        </ul>
    </nav>
@endif
