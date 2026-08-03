{{-- data-export-base-url (the route with any query string stripped) lets
     live-search.js (if present on the page) keep these links' filters in
     sync as the user types, by rebuilding "base?currentParams" on every
     search — see eleves/index.blade.php. Harmless no-op where there's no
     live search (e.g. the comptes page). --}}
@props(['excelRoute', 'pdfRoute'])

<div class="export-buttons">
    <a href="{{ $excelRoute }}" class="btn ghost" data-export-link="excel" data-export-base-url="{{ \Illuminate\Support\Str::before($excelRoute, '?') }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
        Excel
    </a>
    <a href="{{ $pdfRoute }}" class="btn ghost" data-export-link="pdf" data-export-base-url="{{ \Illuminate\Support\Str::before($pdfRoute, '?') }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
        PDF
    </a>
</div>
