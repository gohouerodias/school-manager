{{-- Search box submitted as a real GET param, so server-side filtering and
     pagination stay in sync (see UserAccountController::index).

     Pass live-search-url + live-search-target to also wire up
     resources/js/live-search.js: as the user types, the field's <form> is
     re-submitted via fetch (debounced) instead of a full page reload, and
     the element whose id is live-search-target has its HTML swapped for
     the response — see EleveController::index()'s $request->ajax() branch
     for the matching server side. --}}
@props(['name' => 'search', 'value' => null, 'placeholder' => 'Rechercher...', 'liveSearchUrl' => null, 'liveSearchTarget' => null])

<div class="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input
        type="search"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        @if ($liveSearchUrl)
            data-live-search
            data-live-search-url="{{ $liveSearchUrl }}"
            data-live-search-target="{{ $liveSearchTarget }}"
        @endif
    >
    @if ($liveSearchUrl)
        <span class="search-spinner" data-live-search-spinner aria-hidden="true"></span>
    @endif
</div>
