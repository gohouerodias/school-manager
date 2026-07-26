@props(['profil'])

@php
    $class = match ($profil->value) {
        'administrateur' => 'admin',
        'agent_scolarite' => 'scol',
        'enseignant' => 'ens',
        'direction' => 'dir',
        default => '',
    };
@endphp

<span {{ $attributes->class(['role-badge', $class]) }}>{{ $profil->label() }}</span>
