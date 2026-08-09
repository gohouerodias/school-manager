@extends('layouts.app')

@section('title', 'Niveaux & matières')

@section('content')
<x-page-header
    title="Niveaux & matières"
    subtitle="Listes de référence réutilisées chaque année académique — le programme (matières + coefficients) propre à une année se configure depuis la fiche de cette année."
>
    <x-slot:actions>
        <a href="{{ route('academique.annees.index') }}" class="btn ghost">← Années académiques</a>
    </x-slot:actions>
</x-page-header>

<section class="config-section">
    <div class="config-section-head">
        <h2>Niveaux</h2>
        <button type="button" class="btn primary" data-panel-open="new-niveau">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter un niveau
        </button>
    </div>

    <x-data-table id="niveaux-table">
        <x-slot:head>
            <th>Libellé</th>
            <th>Ordre</th>
            <th>Cycle</th>
            <th>Première scolarisation</th>
            <th></th>
        </x-slot:head>

        @foreach ($niveaux as $niveau)
            <tr>
                <td><b>{{ $niveau->libelle }}</b></td>
                <td>{{ $niveau->ordre }}</td>
                <td><span class="chip">{{ ucfirst($niveau->cycle->value) }}</span></td>
                <td>{{ $niveau->premiere_scolarisation ? 'Oui' : 'Non' }}</td>
                <td>
                    <div class="row-actions-group">
                        <button
                            type="button"
                            class="row-edit"
                            title="Modifier"
                            data-panel-open="edit-niveau"
                            data-edit-niveau-trigger
                            data-edit-url="{{ route('academique.niveaux.update', $niveau) }}"
                            data-edit-libelle="{{ $niveau->libelle }}"
                            data-edit-ordre="{{ $niveau->ordre }}"
                            data-edit-cycle="{{ $niveau->cycle->value }}"
                            data-edit-premiere-scolarisation="{{ $niveau->premiere_scolarisation ? '1' : '0' }}"
                        >✎</button>
                        <form method="POST" action="{{ route('academique.niveaux.destroy', $niveau) }}"
                              data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
                              data-confirm-title="Supprimer ce niveau"
                              data-confirm-message="Supprimer le niveau « {{ $niveau->libelle }} » ?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</section>

<section class="config-section">
    <div class="config-section-head">
        <h2>Matières</h2>
        <button type="button" class="btn primary" data-panel-open="new-matiere">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter une matière
        </button>
    </div>

    <x-data-table id="matieres-table">
        <x-slot:head>
            <th>Nom</th>
            <th></th>
        </x-slot:head>

        @foreach ($matieres as $matiere)
            <tr>
                <td><b>{{ $matiere->nom }}</b></td>
                <td>
                    <div class="row-actions-group">
                        <button
                            type="button"
                            class="row-edit"
                            title="Modifier"
                            data-panel-open="edit-matiere"
                            data-edit-matiere-trigger
                            data-edit-url="{{ route('academique.matieres.update', $matiere) }}"
                            data-edit-nom="{{ $matiere->nom }}"
                        >✎</button>
                        <form method="POST" action="{{ route('academique.matieres.destroy', $matiere) }}"
                              data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
                              data-confirm-title="Supprimer cette matière"
                              data-confirm-message="Supprimer la matière « {{ $matiere->nom }} » ?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</section>

{{-- Ajouter un niveau --}}
<x-slide-panel id="new-niveau" title="Ajouter un niveau">
    <form method="POST" action="{{ route('academique.niveaux.store') }}" id="new-niveau-form">
        @csrf
        <input type="hidden" name="_panel" value="new-niveau">

        @error('libelle')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('ordre')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-niveau-libelle">Libellé</label>
            <input type="text" id="new-niveau-libelle" name="libelle" placeholder="Ex : CM1" value="{{ old('libelle') }}" required>
        </div>

        <div class="field">
            <label for="new-niveau-ordre">Ordre (position dans le cursus)</label>
            <input type="number" id="new-niveau-ordre" name="ordre" min="1" value="{{ old('ordre') }}" required>
            <div class="hint">Détermine le "niveau supérieur" lors du passage à l'année suivante.</div>
        </div>

        <div class="field">
            <label for="new-niveau-cycle">Cycle</label>
            <select class="role-select" id="new-niveau-cycle" name="cycle" required>
                <option value="maternelle" @selected(old('cycle') === 'maternelle')>Maternelle</option>
                <option value="primaire" @selected(old('cycle', 'primaire') === 'primaire')>Primaire</option>
                <option value="college" @selected(old('cycle') === 'college')>Collège</option>
            </select>
        </div>

        <div class="field field-inline">
            <label class="toggle-switch">
                <input type="checkbox" name="premiere_scolarisation" value="1" @checked(old('premiere_scolarisation'))>
                <span class="toggle-slider"></span>
            </label>
            <span>Première scolarisation (aucun document d'école antérieure demandé)</span>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-niveau">Annuler</button>
        <button type="submit" form="new-niveau-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier un niveau --}}
<x-slide-panel id="edit-niveau" title="Modifier le niveau">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-niveau-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-niveau">
        <input type="hidden" name="_edit_url" id="edit-niveau-edit-url" value="{{ old('_edit_url') }}">

        @error('libelle')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('ordre')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="edit-niveau-libelle">Libellé</label>
            <input type="text" id="edit-niveau-libelle" name="libelle" value="{{ old('libelle') }}" required>
        </div>

        <div class="field">
            <label for="edit-niveau-ordre">Ordre (position dans le cursus)</label>
            <input type="number" id="edit-niveau-ordre" name="ordre" min="1" value="{{ old('ordre') }}" required>
        </div>

        <div class="field">
            <label for="edit-niveau-cycle">Cycle</label>
            <select class="role-select" id="edit-niveau-cycle" name="cycle" required>
                <option value="maternelle" @selected(old('cycle') === 'maternelle')>Maternelle</option>
                <option value="primaire" @selected(old('cycle') === 'primaire')>Primaire</option>
                <option value="college" @selected(old('cycle') === 'college')>Collège</option>
            </select>
        </div>

        <div class="field field-inline">
            <label class="toggle-switch">
                <input type="checkbox" id="edit-niveau-premiere-scolarisation" name="premiere_scolarisation" value="1" @checked(old('premiere_scolarisation'))>
                <span class="toggle-slider"></span>
            </label>
            <span>Première scolarisation (aucun document d'école antérieure demandé)</span>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-niveau">Annuler</button>
        <button type="submit" form="edit-niveau-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter une matière --}}
<x-slide-panel id="new-matiere" title="Ajouter une matière">
    <form method="POST" action="{{ route('academique.matieres.store') }}" id="new-matiere-form">
        @csrf
        <input type="hidden" name="_panel" value="new-matiere">

        @error('nom')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-matiere-nom">Nom de la matière</label>
            <input type="text" id="new-matiere-nom" name="nom" placeholder="Ex : Mathématiques" value="{{ old('nom') }}" required>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-matiere">Annuler</button>
        <button type="submit" form="new-matiere-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier une matière --}}
<x-slide-panel id="edit-matiere" title="Modifier la matière">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-matiere-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-matiere">
        <input type="hidden" name="_edit_url" id="edit-matiere-edit-url" value="{{ old('_edit_url') }}">

        @error('nom')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="edit-matiere-nom">Nom de la matière</label>
            <input type="text" id="edit-matiere-nom" name="nom" value="{{ old('nom') }}" required>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-matiere">Annuler</button>
        <button type="submit" form="edit-matiere-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

@endsection
