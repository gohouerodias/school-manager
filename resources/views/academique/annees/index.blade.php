@extends('layouts.app')

@section('title', 'Années académiques')

@section('content')
<x-page-header
    title="Années académiques"
    subtitle="Créez une nouvelle année, configurez son programme, ses classes et ses affectations, puis démarrez-la."
>
    <x-slot:actions>
        <a href="{{ route('academique.niveaux-matieres.index') }}" class="btn ghost">Niveaux &amp; matières</a>
        <button type="button" class="btn primary" data-panel-open="new-annee">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Créer une année académique
        </button>
    </x-slot:actions>
</x-page-header>

<x-data-table id="annees-table">
    <x-slot:head>
        <th>Année</th>
        <th>Du</th>
        <th>Au</th>
        <th>Statut</th>
        <th></th>
    </x-slot:head>

    @forelse ($annees as $annee)
        <tr>
            <td><b>{{ $annee->libelle }}</b></td>
            <td>{{ $annee->date_debut->format('d/m/Y') }}</td>
            <td>{{ $annee->date_fin->format('d/m/Y') }}</td>
            <td>
                @if ($annee->est_active)
                    <span class="doc-status-badge complet">Active</span>
                @else
                    <span class="doc-status-badge manquant">En préparation</span>
                @endif
            </td>
            <td>
                <div class="row-actions-group">
                    <button
                        type="button"
                        class="row-edit"
                        title="Modifier les dates"
                        data-panel-open="edit-annee"
                        data-edit-annee-trigger
                        data-edit-url="{{ route('academique.annees.update', $annee) }}"
                        data-edit-libelle="{{ $annee->libelle }}"
                        data-edit-date-debut="{{ $annee->date_debut->format('Y-m-d') }}"
                        data-edit-date-fin="{{ $annee->date_fin->format('Y-m-d') }}"
                    >✎</button>
                    <a href="{{ route('academique.annees.show', $annee) }}" class="btn ghost">Configurer →</a>
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="table-empty-state">Aucune année académique pour l'instant.</td>
        </tr>
    @endforelse
</x-data-table>

{{-- Créer une année académique --}}
<x-slide-panel id="new-annee" title="Créer une année académique">
    <form method="POST" action="{{ route('academique.annees.store') }}" id="new-annee-form">
        @csrf
        <input type="hidden" name="_panel" value="new-annee">

        @error('libelle')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_debut')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_fin')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-annee-libelle">Libellé</label>
            <input type="text" id="new-annee-libelle" name="libelle" placeholder="Ex : 2026-2027" value="{{ old('libelle') }}" required>
        </div>

        <div class="field">
            <label for="new-annee-date-debut">Date de début</label>
            <input type="date" id="new-annee-date-debut" name="date_debut" value="{{ old('date_debut') }}" required>
        </div>

        <div class="field">
            <label for="new-annee-date-fin">Date de fin</label>
            <input type="date" id="new-annee-date-fin" name="date_fin" value="{{ old('date_fin') }}" required>
        </div>

        <div class="hint">
            La nouvelle année est créée « en préparation » : ajoutez son programme, ses classes et ses affectations
            avant de la démarrer depuis sa fiche — démarrer une année promeut automatiquement les élèves de l'année
            active vers celle-ci.
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-annee">Annuler</button>
        <button type="submit" form="new-annee-form" class="btn dark">Créer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier les dates d'une année académique : le libellé reste fixe une
     fois l'année créée (voir UpdateAnneeAcademiqueRequest). --}}
<x-slide-panel id="edit-annee" title="Modifier les dates">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-annee-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-annee">
        <input type="hidden" name="_edit_url" id="edit-annee-edit-url" value="{{ old('_edit_url') }}">

        @error('date_debut')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_fin')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label>Année académique</label>
            <div class="field-static" id="edit-annee-libelle">—</div>
        </div>

        <div class="field">
            <label for="edit-annee-date-debut">Date de début</label>
            <input type="date" id="edit-annee-date-debut" name="date_debut" value="{{ old('date_debut') }}" required>
        </div>

        <div class="field">
            <label for="edit-annee-date-fin">Date de fin</label>
            <input type="date" id="edit-annee-date-fin" name="date_fin" value="{{ old('date_fin') }}" required>
        </div>

        <div class="hint">
            Les nouvelles dates doivent continuer à englober tous les examens déjà créés pour cette année.
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-annee">Annuler</button>
        <button type="submit" form="edit-annee-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

@endsection
