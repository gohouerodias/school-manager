@props(['title', 'subtitle' => null])

<div class="content-head">
    <div>
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="content-head-actions">{{ $actions }}</div>
    @endisset
</div>
