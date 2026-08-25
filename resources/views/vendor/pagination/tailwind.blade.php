@if ($paginator->hasPages())
    <nav role="navigation" aria-label="分页导航" class="admin-pagination">
        <p class="admin-pagination-summary">
            共 <strong>{{ $paginator->total() }}</strong> 条，当前显示第
            @if ($paginator->firstItem())
                <strong>{{ $paginator->firstItem() }}</strong> 至 <strong>{{ $paginator->lastItem() }}</strong>
            @else
                <strong>0</strong>
            @endif
            条
        </p>

        <div class="admin-pagination-pages">
            @if ($paginator->onFirstPage())
                <span class="admin-pagination-muted" aria-disabled="true" aria-label="上一页">
                    <svg class="admin-pagination-icon" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="admin-pagination-link" aria-label="上一页">
                    <svg class="admin-pagination-icon" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="admin-pagination-muted" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="admin-pagination-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="admin-pagination-link" aria-label="第 {{ $page }} 页">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="admin-pagination-link" aria-label="下一页">
                    <svg class="admin-pagination-icon" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @else
                <span class="admin-pagination-muted" aria-disabled="true" aria-label="下一页">
                    <svg class="admin-pagination-icon" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
