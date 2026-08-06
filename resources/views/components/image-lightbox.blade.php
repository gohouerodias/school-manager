@props(['id'])

{{-- Generic, reusable full-size image viewer: same data-panel-open/data-panel/
     data-panel-overlay contract as <x-slide-panel> (slide-panel.js already
     handles closing it — ✕ button, overlay click — with no extra JS), but
     opened programmatically via resources/js/image-lightbox.js's
     openImageLightbox(url) rather than a static [data-panel-open] trigger,
     since the image shown is only known at render time (e.g. the fiche
     apprenant's photo d'identité). One singleton instance (see
     layouts/app.blade.php) is reused by anything that needs to show a
     larger version of a thumbnail. --}}
<div class="overlay lightbox-overlay" data-panel-overlay="{{ $id }}"></div>

<div {{ $attributes->merge(['class' => 'panel image-lightbox', 'id' => 'panel-'.$id, 'data-panel' => $id]) }}>
    <button type="button" class="lightbox-close" data-panel-close="{{ $id }}">✕</button>
    <img id="{{ $id }}-img" src="" alt="">
</div>
