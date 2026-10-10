@props(['items' => []])

{{-- Every item but the last is followed by a "/" separator: a link when it
     has a URL, plain (non-bold) text when it's only a section name with no
     page of its own (e.g. « Académique »). The last item is the current page. --}}
@if (! empty($items))
    <nav class="breadcrumbs" aria-label="Fil d'Ariane">
        @foreach ($items as $label => $url)
            @if ($loop->last)
                <span class="breadcrumb-current">{{ $label }}</span>
            @else
                @if ($url)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span class="breadcrumb-section">{{ $label }}</span>
                @endif
                <span class="breadcrumb-sep">/</span>
            @endif
        @endforeach
    </nav>
@endif
