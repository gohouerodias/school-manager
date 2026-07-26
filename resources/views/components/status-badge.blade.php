@props(['user'])

@php
    $isArchived = $user->statut === \App\Enums\StatutUtilisateur::Archive;
    $isPending = ! $isArchived && $user->estEnAttenteActivation();

    $class = $isArchived ? 'archived' : ($isPending ? 'pending-status' : 'active');
    $label = $isArchived ? 'Archivé' : ($isPending ? 'Invitation en attente' : 'Actif');
@endphp

<span {{ $attributes->class(['status', $class]) }}><span class="dot"></span>{{ $label }}</span>
