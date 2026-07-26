{{-- Search box submitted as a real GET param, so server-side filtering and
     pagination stay in sync (see UserAccountController::index). --}}
@props(['name' => 'search', 'value' => null, 'placeholder' => 'Rechercher...'])

<div class="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}">
</div>
