@extends('layouts.app')

@section('title', $anneeAcademique->libelle)

@section('content')
<x-page-header
    :title="$anneeAcademique->libelle"
    :subtitle="'Du '.$anneeAcademique->date_debut->format('d/m/Y').' au '.$anneeAcademique->date_fin->format('d/m/Y')"
>
    <x-slot:actions>
        <a href="{{ route('academique.annees.index') }}" class="btn ghost">← Années académiques</a>
        @if ($anneeAcademique->est_active)
            <span class="doc-status-badge complet">Année active</span>
        @else
            <form method="POST" action="{{ route('academique.annees.demarrer', $anneeAcademique) }}"
                  data-confirm-submit data-confirm-danger="1" data-confirm-label="Démarrer cette année"
                  data-confirm-title="Démarrer cette année académique"
                  data-confirm-message="Démarrer « {{ $anneeAcademique->libelle }} » ?{{ $anneeActive ? ' Les élèves admis ou redoublants de « '.$anneeActive->libelle.' » seront automatiquement inscrits dans une classe de cette nouvelle année, selon leur décision de fin d\'année.' : '' }} Cette action ne peut pas être annulée simplement.">
                @csrf
                <button type="submit" class="btn danger">Démarrer cette année</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@if (session('nonResolus') && count(session('nonResolus')))
    <div class="alert-error wizard-error-summary">
        <b>{{ count(session('nonResolus')) }} élève(s) n'ont pas pu être affecté(s) automatiquement à une classe :</b>
        <ul>
            @foreach (session('nonResolus') as $cas)
                <li>{{ $cas['raison'] }}</li>
            @endforeach
        </ul>
        <div class="hint">Utilisez le menu déroulant « Classe » depuis la liste des apprenants pour les affecter manuellement.</div>
    </div>
@endif

<section class="config-section">
    <div class="config-section-head">
        <h2>Programme par niveau</h2>
        <button type="button" class="btn primary" data-panel-open="new-niveau-matiere">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter une matière au programme
        </button>
    </div>

    <x-data-table id="niveau-matieres-table">
        <x-slot:head>
            <th>Niveau</th>
            <th>Matières (coefficient)</th>
        </x-slot:head>

        @forelse ($niveaux as $niveau)
            @php $lignes = $anneeAcademique->niveauMatieres->where('niveau_id', $niveau->id); @endphp
            <tr>
                <td><b>{{ $niveau->libelle }}</b></td>
                <td>
                    <div class="chips">
                        @forelse ($lignes as $ligne)
                            <span class="chip">
                                {{ $ligne->matiere->nom }} ({{ rtrim(rtrim(number_format($ligne->coefficient, 1), '0'), '.') }})
                                <form method="POST" action="{{ route('academique.niveau-matieres.destroy', $ligne) }}" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="chip-remove" title="Retirer">✕</button>
                                </form>
                            </span>
                        @empty
                            <span class="table-empty-state">Aucune matière au programme.</span>
                        @endforelse
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="table-empty-state">Aucun niveau — créez-en depuis « Niveaux &amp; matières ».</td>
            </tr>
        @endforelse
    </x-data-table>
</section>

<section class="config-section">
    <div class="config-section-head">
        <h2>Classes</h2>
        <button type="button" class="btn primary" data-panel-open="new-classe">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter une classe
        </button>
    </div>

    <x-data-table id="classes-table">
        <x-slot:head>
            <th>Nom</th>
            <th>Niveau</th>
            <th>Matières</th>
            <th></th>
        </x-slot:head>

        @forelse ($anneeAcademique->classes as $classe)
            <tr>
                <td><b>{{ $classe->nom }}</b></td>
                <td>{{ $classe->niveau->libelle }}</td>
                <td>{{ $classe->matieres->count() }}</td>
                <td>
                    <form method="POST" action="{{ route('academique.classes.destroy', $classe) }}"
                          data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
                          data-confirm-title="Supprimer cette classe"
                          data-confirm-message="Supprimer la classe « {{ $classe->nom }} » ?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="table-empty-state">Aucune classe pour cette année pour l'instant.</td>
            </tr>
        @endforelse
    </x-data-table>
</section>

<section class="config-section">
    <div class="config-section-head">
        <h2>Affectations enseignants</h2>
        <button type="button" class="btn primary" data-panel-open="new-affectation">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Affecter un enseignant
        </button>
    </div>

    <x-data-table id="affectations-table">
        <x-slot:head>
            <th>Enseignant</th>
            <th>Classe</th>
            <th>Matière</th>
            <th>Professeur principal</th>
            <th></th>
        </x-slot:head>

        @forelse ($anneeAcademique->affectations as $affectation)
            <tr>
                <td>{{ $affectation->enseignant->name }}</td>
                <td>{{ $affectation->classe->nom }}</td>
                <td>{{ $affectation->matiere->nom }}</td>
                <td>{{ $affectation->est_professeur_principal ? 'Oui' : '—' }}</td>
                <td>
                    <form method="POST" action="{{ route('academique.affectations.destroy', $affectation) }}"
                          data-confirm-submit data-confirm-danger="1" data-confirm-label="Retirer"
                          data-confirm-title="Retirer cette affectation"
                          data-confirm-message="Retirer {{ $affectation->enseignant->name }} de « {{ $affectation->classe->nom }} » ({{ $affectation->matiere->nom }}) ?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="row-delete" title="Retirer">🗑</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="table-empty-state">Aucune affectation pour cette année pour l'instant.</td>
            </tr>
        @endforelse
    </x-data-table>
</section>

{{-- Ajouter une matière au programme d'un niveau --}}
<x-slide-panel id="new-niveau-matiere" title="Ajouter une matière au programme">
    <form method="POST" action="{{ route('academique.annees.niveau-matieres.store', $anneeAcademique) }}" id="new-niveau-matiere-form">
        @csrf
        <input type="hidden" name="_panel" value="new-niveau-matiere">

        @error('niveau_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('matiere_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('coefficient')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-niveau-matiere-niveau">Niveau</label>
            <select class="role-select" id="new-niveau-matiere-niveau" name="niveau_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected((string) old('niveau_id') === (string) $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-niveau-matiere-matiere">Matière</label>
            <select class="role-select" id="new-niveau-matiere-matiere" name="matiere_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($matieres as $matiere)
                    <option value="{{ $matiere->id }}" @selected((string) old('matiere_id') === (string) $matiere->id)>{{ $matiere->nom }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-niveau-matiere-coefficient">Coefficient</label>
            <input type="number" id="new-niveau-matiere-coefficient" name="coefficient" step="0.5" min="0.5" max="20" value="{{ old('coefficient') }}" required>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-niveau-matiere">Annuler</button>
        <button type="submit" form="new-niveau-matiere-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter une classe --}}
<x-slide-panel id="new-classe" title="Ajouter une classe">
    <form method="POST" action="{{ route('academique.annees.classes.store', $anneeAcademique) }}" id="new-classe-form">
        @csrf
        <input type="hidden" name="_panel" value="new-classe">

        @error('niveau_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('nom')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-classe-niveau">Niveau</label>
            <select class="role-select" id="new-classe-niveau" name="niveau_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected((string) old('niveau_id') === (string) $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-classe-nom">Nom de la classe</label>
            <input type="text" id="new-classe-nom" name="nom" placeholder="Ex : CM1 A" value="{{ old('nom') }}" required>
            <div class="hint">La classe hérite automatiquement du programme (matières + coefficients) défini ci-dessus pour ce niveau.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-classe">Annuler</button>
        <button type="submit" form="new-classe-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Affecter un enseignant --}}
<x-slide-panel id="new-affectation" title="Affecter un enseignant">
    <form method="POST" action="{{ route('academique.annees.affectations.store', $anneeAcademique) }}" id="new-affectation-form">
        @csrf
        <input type="hidden" name="_panel" value="new-affectation">

        @error('enseignant_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('classe_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('matiere_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-affectation-enseignant">Enseignant</label>
            <select class="role-select" id="new-affectation-enseignant" name="enseignant_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($enseignants as $enseignant)
                    <option value="{{ $enseignant->id }}" @selected((string) old('enseignant_id') === (string) $enseignant->id)>{{ $enseignant->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-affectation-classe">Classe</label>
            <select class="role-select" id="new-affectation-classe" name="classe_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($anneeAcademique->classes as $classe)
                    <option
                        value="{{ $classe->id }}"
                        data-matiere-ids="{{ $classe->matieres->pluck('id')->implode(',') }}"
                        @selected((string) old('classe_id') === (string) $classe->id)
                    >{{ $classe->nom }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-affectation-matiere">Matière</label>
            <select class="role-select" id="new-affectation-matiere" name="matiere_id" required>
                <option value="">— Sélectionnez d'abord une classe —</option>
                @foreach ($matieres as $matiere)
                    <option value="{{ $matiere->id }}" @selected((string) old('matiere_id') === (string) $matiere->id)>{{ $matiere->nom }}</option>
                @endforeach
            </select>
            <div class="hint">La liste se limite aux matières du programme de la classe choisie.</div>
        </div>

        <div class="field field-inline">
            <label class="toggle-switch">
                <input type="checkbox" name="est_professeur_principal" value="1" @checked(old('est_professeur_principal'))>
                <span class="toggle-slider"></span>
            </label>
            <span>Professeur principal de cette classe</span>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-affectation">Annuler</button>
        <button type="submit" form="new-affectation-form" class="btn dark">Affecter</button>
    </x-slot:footer>
</x-slide-panel>

@endsection
