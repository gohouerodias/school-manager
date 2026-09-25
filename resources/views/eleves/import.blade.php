@extends('layouts.app')

@section('title', 'Importer des apprenants')

@section('content')
<x-page-header
    title="Importer des apprenants"
    subtitle="Fichier Excel (.xlsx, .xls) ou CSV avec les colonnes : Matricule, Nom, Prénom, Sexe, Date de naissance, Classe, Statut — mêmes colonnes que l'export."
>
    <x-slot:actions>
        <a href="{{ route('eleves.index') }}" class="btn ghost">← Liste des apprenants</a>
    </x-slot:actions>
</x-page-header>

<section class="config-section">
    <form method="POST" action="{{ route('eleves.import.store') }}" enctype="multipart/form-data">
        @csrf

        @error('fichier')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="fichier">Fichier à importer</label>
            <input type="file" id="fichier" name="fichier" accept=".xlsx,.xls,.csv" required>
            <div class="hint">
                Un apprenant dont le matricule correspond déjà à une fiche existante voit cette fiche mise à jour (jamais dupliquée).
                Un matricule vide ou inconnu crée une nouvelle fiche. La colonne « Classe » (nom exact) n'est appliquée que
                pour l'année académique active — laissez-la vide pour ne pas toucher à la classe d'un apprenant existant.
            </div>
        </div>

        <button type="submit" class="btn dark">Importer</button>
    </form>
</section>
@endsection
