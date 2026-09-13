@extends('layouts.app')

@section('title', 'Aperçu bulletin annuel — '.$eleve->nomComplet())

@section('content')
@php
    $telechargerUrl = route('eleves.bulletins.annuel.apercu.telecharger', ['classe' => $classe, 'inscription' => $inscription]);
    $partageTitre = 'Bulletin annuel — '.$eleve->nomComplet();
    $partageTexte = 'Bulletin annuel de '.$eleve->nomComplet().' — '.$classe->nom.' ('.$anneeAcademique->libelle.')';
@endphp
<x-page-header :title="'Aperçu bulletin annuel — '.$eleve->nomComplet()" :subtitle="$classe->nom.' · '.$anneeAcademique->libelle">
    <x-slot:actions>
        <a href="{{ route('eleves.bulletins.index', ['classe_id' => $classe->id]) }}" class="btn ghost">← Retour aux bulletins</a>
        <a href="{{ $telechargerUrl }}" class="btn primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
            Télécharger en PDF
        </a>
        <div class="share-dropdown">
            <button
                type="button"
                id="bulletin-partager-btn"
                class="btn dark"
                data-share-url="{{ $telechargerUrl }}"
                data-share-title="{{ $partageTitre }}"
                data-share-text="{{ $partageTexte }}"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
                Partager
            </button>
            <div class="share-menu" id="bulletin-share-menu" hidden>
                <a href="#" class="share-menu-item" data-share-channel="email">✉️ Envoyer par email</a>
                <a href="#" class="share-menu-item" data-share-channel="whatsapp">🟢 WhatsApp</a>
            </div>
        </div>
    </x-slot:actions>
</x-page-header>

<div class="bulletin-page-wrap">
    <div class="bulletin-sheet">
        <div class="bulletin-sheet-body">
            @include('eleves.bulletins._papier_annuel')
        </div>
    </div>
</div>
@endsection
