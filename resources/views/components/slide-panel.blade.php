@props(['id', 'title'])

<div class="overlay" data-panel-overlay="{{ $id }}"></div>

<div class="panel" id="panel-{{ $id }}" data-panel="{{ $id }}">
    <div class="panel-head">
        <h2>{{ $title }}</h2>
        <button type="button" class="panel-close" data-panel-close="{{ $id }}">✕</button>
    </div>
    <div class="panel-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="panel-foot">{{ $footer }}</div>
    @endisset
</div>
