@props(['name', 'profil' => null])

@php
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $colorClass = match ($profil?->value) {
        'administrateur' => 'avatar-admin',
        'agent_scolarite' => 'avatar-scol',
        'enseignant' => 'avatar-ens',
        'direction' => 'avatar-dir',
        default => 'avatar-neutral',
    };
@endphp

<div {{ $attributes->class(['avatar', $colorClass]) }}>{{ $initials }}</div>
