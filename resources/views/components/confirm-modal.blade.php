@props(['id', 'title' => 'Confirmer'])

{{-- Generic, reusable confirmation dialog: a centered dialog variant of
     <x-slide-panel> (same data-panel-open/data-panel/data-panel-overlay
     contract, no extra open/close JS needed), whose message + confirm
     action are set dynamically by resources/js/confirm-modal.js's
     askConfirmation() right before it opens — one singleton instance
     (see layouts/app.blade.php) is reused by every feature that needs a
     "are you sure?" step, instead of each one building its own modal. --}}
<div class="overlay" data-panel-overlay="{{ $id }}"></div>

<div {{ $attributes->merge(['class' => 'panel confirm-modal', 'id' => 'panel-'.$id, 'data-panel' => $id]) }}>
    <div class="panel-head">
        <h2 data-confirm-title="{{ $id }}">{{ $title }}</h2>
        <button type="button" class="panel-close" data-panel-close="{{ $id }}">✕</button>
    </div>
    <div class="panel-body">
        <p class="confirm-modal-message" data-confirm-message="{{ $id }}"></p>
    </div>
    <div class="panel-foot">
        <button type="button" class="btn ghost" data-confirm-cancel="{{ $id }}" data-panel-close="{{ $id }}">Annuler</button>
        <button type="button" class="btn dark" data-confirm-confirm="{{ $id }}">Confirmer</button>
    </div>
</div>
