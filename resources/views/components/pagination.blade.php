@if ($paginator->hasPages())
    <nav class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Phân trang">
        <p class="text-muted">Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} trong {{ $paginator->total() }}</p>
        <div class="flex flex-wrap gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50">← Trước</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Trước</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 py-1 text-muted">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary btn-sm" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="btn btn-secondary btn-sm" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau →</a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50">Sau →</span>
            @endif
        </div>
    </nav>
@endif
