@extends('layouts.app')

@section('title', "Résultat de l'import")

@section('content')
<x-page-header title="Résultat de l'import" subtitle="Compte-rendu de l'import du fichier apprenants.">
    <x-slot:actions>
        <a href="{{ route('eleves.import.create') }}" class="btn ghost">Importer un autre fichier</a>
        <a href="{{ route('eleves.index') }}" class="btn primary">Voir la liste des apprenants</a>
    </x-slot:actions>
</x-page-header>

<section class="config-section">
    <div class="rapport-summary-row">
        <div class="rapport-summary-card">
            <div class="rapport-summary-label">Fiches créées</div>
            <div class="rapport-summary-value">{{ $crees }}</div>
        </div>
        <div class="rapport-summary-card">
            <div class="rapport-summary-label">Fiches mises à jour</div>
            <div class="rapport-summary-value">{{ $misAJour }}</div>
        </div>
        <div class="rapport-summary-card">
            <div class="rapport-summary-label">Lignes en erreur</div>
            <div class="rapport-summary-value">{{ count($erreurs) }}</div>
        </div>
    </div>

    @if (count($erreurs) > 0)
        <div class="config-section-head" style="margin-top: 24px;">
            <h2>Erreurs</h2>
        </div>
        <ul>
            @foreach ($erreurs as $erreur)
                <li class="alert-error" style="margin-bottom: 8px;">{{ $erreur }}</li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
