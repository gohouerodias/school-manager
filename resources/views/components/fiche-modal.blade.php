@props(['id'])

{{-- Centered dialog variant of <x-slide-panel>: reuses the same
     data-panel-open/data-panel/data-panel-overlay contract (and slide-panel.js)
     so no extra JS is needed for open/close, only different CSS. --}}
<div class="overlay" data-panel-overlay="{{ $id }}"></div>

<div {{ $attributes->merge(['class' => 'panel fiche-modal', 'id' => 'panel-'.$id, 'data-panel' => $id]) }}>
    {{ $slot }}
</div>
