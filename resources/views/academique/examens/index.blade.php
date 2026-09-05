@extends('layouts.app')

@section('title', 'Examens')

@section('content')
<x-page-header
    title="Examens"
    subtitle="Créez un examen pour un système scolaire — pour l'instant, seul le système primaire est disponible."
>
    <x-slot:actions>
        <a href="{{ route('academique.annees.index') }}" class="btn ghost">← Années académiques</a>
        <button type="button" class="btn primary" data-panel-open="new-examen">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Créer un examen
        </button>
    </x-slot:actions>
</x-page-header>

@unless ($anneeActive)
    <div class="alert-error">Aucune année académique active pour l'instant — démarrez une année depuis « Années académiques » avant de créer un examen.</div>
@endunless

<x-data-table id="examens-table">
    <x-slot:head>
        <th>Système</th>
        <th>Type</th>
        <th>Année académique</th>
        <th>Date de l'examen</th>
        <th>Date limite de saisie</th>
        <th></th>
    </x-slot:head>

    @forelse ($examens as $examen)
        <tr>
            <td><span class="chip">{{ $examen->systeme->label() }}</span></td>
            <td>{{ $examen->type->label() }}</td>
            <td>{{ $examen->anneeAcademique->libelle }}</td>
            <td>{{ $examen->date_examen->format('d/m/Y') }}</td>
            <td>{{ $examen->date_limite_saisie->format('d/m/Y') }}</td>
            <td>
                <div class="row-actions-group">
                    <button
                        type="button"
                        class="row-edit"
                        title="Modifier"
                        data-panel-open="edit-examen"
                        data-edit-examen-trigger
                        data-edit-url="{{ route('academique.examens.update', $examen) }}"
                        data-edit-systeme="{{ $examen->systeme->label() }}"
                        data-edit-annee="{{ $examen->anneeAcademique->libelle }}"
                        data-edit-date-examen="{{ $examen->date_examen->format('Y-m-d') }}"
                        data-edit-date-limite="{{ $examen->date_limite_saisie->format('Y-m-d') }}"
                        data-edit-min="{{ $examen->anneeAcademique->date_debut->format('Y-m-d') }}"
                        data-edit-max="{{ $examen->anneeAcademique->date_fin->format('Y-m-d') }}"
                    >✎</button>
                    <form method="POST" action="{{ route('academique.examens.destroy', $examen) }}"
                          data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
                          data-confirm-title="Supprimer cet examen"
                          data-confirm-message="Supprimer cet examen supprimera aussi toutes les notes, commentaires et bulletins déjà saisis pour cet examen. Continuer ?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                    </form>
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="table-empty-state">Aucun examen créé pour l'instant.</td>
        </tr>
    @endforelse
</x-data-table>

{{-- Créer un examen --}}
<x-slide-panel id="new-examen" title="Créer un examen">
    <form method="POST" action="{{ route('academique.examens.store') }}" id="new-examen-form">
        @csrf
        <input type="hidden" name="_panel" value="new-examen">

        @error('systeme')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_examen')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_limite_saisie')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-examen-systeme">Système scolaire</label>
            <select class="role-select" id="new-examen-systeme" name="systeme" required>
                <option value="">— Sélectionner —</option>
                <option value="maternelle" @selected(old('systeme') === 'maternelle')>Maternelle</option>
                <option value="primaire" @selected(old('systeme', 'primaire') === 'primaire')>Primaire</option>
                <option value="secondaire" @selected(old('systeme') === 'secondaire')>Secondaire</option>
            </select>
        </div>

        <div id="new-examen-secondaire-hint" class="hint" style="display:none;">
            Le système secondaire est en cours de développement et n'est pas encore disponible. Choisir « Créer » ici affichera simplement un message d'indisponibilité, sans créer d'examen.
        </div>

        <div id="new-examen-primaire-fields">
            <div class="field">
                <label>Année académique</label>
                <div class="field-static">{{ $anneeActive?->libelle ?? '—' }}</div>
                <div class="hint">L'examen est toujours créé pour l'année académique actuellement active.</div>
            </div>

            <div class="hint">
                Un examen mensuel unique sera créé, portant sur toutes les classes et tous les élèves du système
                choisi pour cette année académique — chaque élève étant évalué dans les matières de son programme.
            </div>

            <div class="field">
                <label for="new-examen-date">Date de l'examen</label>
                <input type="date" id="new-examen-date" name="date_examen" value="{{ old('date_examen') }}"
                    @if ($anneeActive) min="{{ $anneeActive->date_debut->format('Y-m-d') }}" max="{{ $anneeActive->date_fin->format('Y-m-d') }}" @endif>
            </div>

            <div class="field">
                <label for="new-examen-date-limite">Date limite de saisie des notes</label>
                <input type="date" id="new-examen-date-limite" name="date_limite_saisie" value="{{ old('date_limite_saisie') }}"
                    @if ($anneeActive) min="{{ $anneeActive->date_debut->format('Y-m-d') }}" max="{{ $anneeActive->date_fin->format('Y-m-d') }}" @endif>
                <div class="hint">Délai laissé aux enseignants pour saisir les notes de cet examen.</div>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-examen">Annuler</button>
        <button type="submit" form="new-examen-form" class="btn dark">Créer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier un examen : seules les dates se modifient, le système reste
     fixe une fois l'examen créé (voir UpdateExamenRequest). --}}
<x-slide-panel id="edit-examen" title="Modifier l'examen">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-examen-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-examen">
        <input type="hidden" name="_edit_url" id="edit-examen-edit-url" value="{{ old('_edit_url') }}">

        @error('date_examen')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_limite_saisie')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label>Système / Année académique</label>
            <div class="field-static" id="edit-examen-systeme-annee">—</div>
        </div>

        <div class="field">
            <label for="edit-examen-date">Date de l'examen</label>
            <input type="date" id="edit-examen-date" name="date_examen" value="{{ old('date_examen') }}" required>
        </div>

        <div class="field">
            <label for="edit-examen-date-limite">Date limite de saisie des notes</label>
            <input type="date" id="edit-examen-date-limite" name="date_limite_saisie" value="{{ old('date_limite_saisie') }}" required>
            <div class="hint">Délai laissé aux enseignants pour saisir les notes de cet examen.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-examen">Annuler</button>
        <button type="submit" form="edit-examen-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

@endsection
