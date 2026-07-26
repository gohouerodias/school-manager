@props(['items' => []])

@if (! empty($items))
    <nav class="breadcrumbs" aria-label="Fil d'Ariane">
        @foreach ($items as $label => $url)
            @if (! $loop->last && $url)
                <a href="{{ $url }}">{{ $label }}</a>
                <span class="breadcrumb-sep">/</span>
            @else
                <span class="breadcrumb-current">{{ $label }}</span>
            @endif
        @endforeach
    </nav>
@endif
