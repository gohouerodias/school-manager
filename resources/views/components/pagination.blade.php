@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        <div class="pagination-info">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sur {{ $paginator->total() }}
        </div>
        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="page-link disabled">← Précédent</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="page-link">← Précédent</a>
            @endif

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                <a href="{{ $url }}" @class(['page-link', 'active' => $page === $paginator->currentPage()])>{{ $page }}</a>
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="page-link">Suivant →</a>
            @else
                <span class="page-link disabled">Suivant →</span>
            @endif
        </div>
    </nav>
@endif
