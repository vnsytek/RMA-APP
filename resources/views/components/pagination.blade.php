@if ($paginator->hasPages())
    <nav class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Phân trang">
        <p class="text-muted">Đang xem {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} trong {{ $paginator->total() }}</p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true"><x-icon name="chevron-left" />Trước</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="chevron-left" />Trước</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1.5 text-muted max-sm:hidden">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary btn-sm min-w-8" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="btn btn-ghost btn-sm min-w-8 max-sm:hidden" href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau<x-icon name="chevron-right" /></a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true">Sau<x-icon name="chevron-right" /></span>
            @endif
        </div>
    </nav>
@endif
