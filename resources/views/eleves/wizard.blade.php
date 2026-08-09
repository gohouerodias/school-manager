@extends('layouts.app')

@section('title', $eleve ? 'Modifier la fiche élève' : 'Nouvelle fiche élève')

@section('content')

@php
    // Which step to land on: 1 by default, or whichever step the failed
    // validation actually concerns (see SaveEleveWizardRequest) — a hard
    // page reload after a failed "Terminer" would otherwise always drop the
    // agent back on Étape 1 even if the mistake is on Étape 4.
    $activeStep = 1;
    if ($errors->any()) {
        $champErrors = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'champs.'));
        $tuteurErrors = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'tuteurs.'));
        $documentErrors = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'documents.'));

        if ($errors->has('niveau_souhaite_id')) {
            $activeStep = 1;
        } elseif ($errors->has('nom') || $errors->has('prenom') || $errors->has('sexe') || $errors->has('date_naissance') || $errors->has('matricule') || $champErrors) {
            $activeStep = 2;
        } elseif ($tuteurErrors) {
            $activeStep = 3;
        } elseif ($documentErrors) {
            $activeStep = 4;
        }
    }
@endphp

<x-page-header
    :title="$eleve ? 'Modifier la fiche de '.$eleve->nomComplet() : 'Nouvelle fiche élève'"
    subtitle="Classe désirée, infos personnelles, parents/tuteurs et documents — 4 étapes, à compléter dans l'ordre voulu."
>
    <x-slot:actions>
        <a href="{{ route('eleves.index') }}" class="btn ghost">← Retour à la liste</a>
    </x-slot:actions>
</x-page-header>

@if ($errors->any())
    <div class="alert-error wizard-error-summary">
        <b>Certains champs nécessitent votre attention avant de pouvoir « Terminer » cette fiche :</b>
        <ul>
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($eleve && $eleve->statut->value === 'brouillon')
    <div class="wizard-draft-banner">
        Cette fiche est un <b>brouillon</b> : elle n'apparaîtra comme « Actif » dans la liste qu'une fois toutes les étapes terminées.
    </div>
@endif

<div class="wizard-steps" id="wizard-steps">
    <button type="button" class="wizard-step @if ($activeStep === 1) active @endif" data-wizard-step-btn="1">
        <span class="wizard-step-num">1</span> Classe désirée
    </button>
    <button type="button" class="wizard-step @if ($activeStep === 2) active @endif" data-wizard-step-btn="2">
        <span class="wizard-step-num">2</span> Infos personnelles
    </button>
    <button type="button" class="wizard-step @if ($activeStep === 3) active @endif" data-wizard-step-btn="3">
        <span class="wizard-step-num">3</span> Parents / Tuteurs
    </button>
    <button type="button" class="wizard-step @if ($activeStep === 4) active @endif" data-wizard-step-btn="4">
        <span class="wizard-step-num">4</span> Documents
    </button>
</div>

<div class="wizard-layout">
<form
    method="POST"
    action="{{ $eleve ? route('eleves.wizard.update', $eleve) : route('eleves.wizard.store') }}"
    id="eleve-wizard-form"
    enctype="multipart/form-data"
    data-tuteur-recherche-url="{{ route('eleves.wizard.tuteurs.recherche') }}"
    {{-- When "Terminer" fails validation (e.g. a missing document at étape 4),
         Laravel flashes the raw submission back via old() — every plain input
         below already reads old('field', ...) so it survives the reload, but
         the "Parents / Tuteurs" pending list is built client-side in JS
         (eleve-wizard.js) and would otherwise reset to empty on every page
         load. Passing old('tuteurs') through here lets initTuteurPendingList()
         rehydrate it instead of making the agent re-type every tuteur. --}}
    data-old-tuteurs="{{ old('tuteurs') ? json_encode(old('tuteurs')) : '' }}"
>
    @csrf
    @if ($eleve)
        @method('PATCH')
    @endif

    <div class="wizard-panel">

        {{-- Étape 1 : Classe désirée --}}
        <div class="wizard-step-content" data-wizard-step="1" style="@if ($activeStep !== 1) display:none; @endif">
            <div class="field @error('niveau_souhaite_id') invalid @enderror">
                <label for="wizard-niveau-souhaite">Classe désirée</label>
                <select class="role-select" id="wizard-niveau-souhaite" name="niveau_souhaite_id">
                    <option value="">— Sélectionner —</option>
                    @foreach ($niveaux as $niveau)
                        <option
                            value="{{ $niveau->id }}"
                            data-premiere-scolarisation="{{ $niveau->premiere_scolarisation ? '1' : '0' }}"
                            @selected((string) old('niveau_souhaite_id', $eleve?->niveau_souhaite_id) === (string) $niveau->id)
                        >{{ $niveau->libelle }}</option>
                    @endforeach
                </select>
                @error('niveau_souhaite_id')
                    <div class="error">{{ $message }}</div>
                @enderror
                <div class="hint">
                    Si la classe désirée est « Maternelle 1 » ou « Maternelle 2 », aucun bulletin ni certificat
                    d'une école antérieure ne sera demandé à l'étape « Documents ». Pour toute autre classe, ces
                    documents seront requis pour terminer la fiche.
                </div>
            </div>
        </div>

        {{-- Étape 2 : Infos personnelles --}}
        <div class="wizard-step-content" data-wizard-step="2" style="@if ($activeStep !== 2) display:none; @endif">
            <div class="field @error('matricule') invalid @enderror">
                <label for="wizard-matricule">Matricule</label>
                <input type="text" id="wizard-matricule" name="matricule" value="{{ old('matricule', $eleve?->matricule) }}" placeholder="Ex : 2026-1000">
                <div class="hint">Optionnel : délivré par Educmaster, pas par cette application. Renseignez-le si vous l'avez, ou complétez-le plus tard.</div>
                @error('matricule')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field @error('nom') invalid @enderror">
                <label for="wizard-nom">Nom *</label>
                <input type="text" id="wizard-nom" name="nom" value="{{ old('nom', $eleve?->nom) }}">
                @error('nom')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field @error('prenom') invalid @enderror">
                <label for="wizard-prenom">Prénom(s) *</label>
                <input type="text" id="wizard-prenom" name="prenom" value="{{ old('prenom', $eleve?->prenom) }}">
                @error('prenom')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field @error('sexe') invalid @enderror">
                <label for="wizard-sexe">Sexe *</label>
                <select class="role-select" id="wizard-sexe" name="sexe">
                    <option value="M" @selected(old('sexe', $eleve?->sexe ?? 'M') === 'M')>Masculin</option>
                    <option value="F" @selected(old('sexe', $eleve?->sexe) === 'F')>Féminin</option>
                </select>
                @error('sexe')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field @error('date_naissance') invalid @enderror">
                <label for="wizard-date-naissance">Date de naissance *</label>
                <input type="date" id="wizard-date-naissance" name="date_naissance" value="{{ old('date_naissance', $eleve?->date_naissance?->format('Y-m-d')) }}">
                @error('date_naissance')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            @foreach ($champsPersonnalises as $champ)
                @php
                    $valeurExistante = $eleve?->valeursPersonnalisees->firstWhere('champ_personnalise_id', $champ->id)?->valeur;
                @endphp
                <div class="field @error("champs.{$champ->id}") invalid @enderror">
                    <label for="wizard-champ-{{ $champ->id }}">{{ $champ->libelle }}{{ $champ->obligatoire ? ' *' : '' }}</label>
                    @if ($champ->type->value === 'liste_deroulante')
                        <select class="role-select" id="wizard-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]">
                            <option value="">—</option>
                            @foreach ($champ->options ?? [] as $option)
                                <option value="{{ $option }}" @selected(old("champs.{$champ->id}", $valeurExistante) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif ($champ->type->value === 'date')
                        <input type="date" id="wizard-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}", $valeurExistante) }}">
                    @elseif ($champ->type->value === 'nombre')
                        <input type="number" id="wizard-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}", $valeurExistante) }}">
                    @else
                        <input type="text" id="wizard-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}", $valeurExistante) }}">
                    @endif
                    @error("champs.{$champ->id}")
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
        </div>

        {{-- Étape 3 : Parents / Tuteurs --}}
        <div class="wizard-step-content" data-wizard-step="3" style="@if ($activeStep !== 3) display:none; @endif">
            @if ($eleve && $eleve->parents->isNotEmpty())
                <div class="pending-list-title" style="margin-top:0; padding-top:0; border-top:none;">Déjà liés à cette fiche</div>
                @foreach ($eleve->parents as $parent)
                    <div class="fiche-parent">
                        <div>
                            <b>{{ $parent->nom }} {{ $parent->prenom }}</b> — {{ $parent->pivot->lien_parente }}
                            <br><span>{{ $parent->telephone ?: '—' }}{{ $parent->email ? ' · '.$parent->email : '' }}</span>
                        </div>
                    </div>
                @endforeach
                <div class="hint" style="margin: 6px 0 22px;">Pour modifier ou retirer un tuteur déjà lié, utilisez le bouton « Modifier » depuis la fiche (consultation, liste des apprenants).</div>
            @endif

            <div class="pending-list-title" style="margin-top:0; padding-top:0; border-top:none;">Ajouter un parent / tuteur</div>

            <div id="wizard-tuteur-match" class="wizard-tuteur-match" style="display:none;"></div>

            <div class="field">
                <label for="wizard-tuteur-nom-prenom">Nom et prénom</label>
                <input type="text" id="wizard-tuteur-nom-prenom" autocomplete="off" placeholder="Ex : Adjovi Koffi">
                <div id="wizard-tuteur-suggestions" class="wizard-tuteur-suggestions"></div>
            </div>
            <div class="field">
                <label for="wizard-tuteur-lien">Lien de parenté</label>
                <select class="role-select" id="wizard-tuteur-lien">
                    <option value="Père">Père</option>
                    <option value="Mère">Mère</option>
                    <option value="Tuteur légal">Tuteur légal</option>
                    <option value="Autre">Autre</option>
                </select>
            </div>
            <div class="field">
                <label for="wizard-tuteur-telephone">Téléphone</label>
                <input type="text" id="wizard-tuteur-telephone">
            </div>
            <div class="field">
                <label for="wizard-tuteur-email">Email</label>
                <input type="email" id="wizard-tuteur-email">
            </div>
            <button type="button" class="btn add-pending" id="wizard-tuteur-add-btn">+ Ajouter à la liste</button>

            <div id="wizard-tuteur-pending-section" style="display:none;">
                <div class="pending-list-title">En attente d'enregistrement (<span id="wizard-tuteur-pending-count">0</span>)</div>
                <div id="wizard-tuteur-pending-list"></div>
            </div>
        </div>

        {{-- Étape 4 : Documents --}}
        <div class="wizard-step-content" data-wizard-step="4" style="@if ($activeStep !== 4) display:none; @endif">
            @foreach ($typesDocuments as $type)
                @php
                    $documentExistant = $eleve?->documents->firstWhere('type_document_id', $type->id);
                @endphp
                <div
                    class="field wizard-document-field @error("documents.{$type->id}") invalid @enderror"
                    data-requis-si-transfert="{{ $type->requis_si_transfert ? '1' : '0' }}"
                >
                    <label for="wizard-document-{{ $type->id }}">
                        {{ $type->libelle }}{{ ($type->obligatoire || $type->requis_si_transfert) ? ' *' : '' }}
                        @if (! empty($type->formats_acceptes))
                            <span class="wizard-document-formats">({{ implode(', ', $type->formats_acceptes) }})</span>
                        @endif
                    </label>
                    <input type="file" id="wizard-document-{{ $type->id }}" name="documents[{{ $type->id }}]">
                    @if ($documentExistant)
                        <div class="hint">Déjà fourni — choisissez un fichier pour le remplacer.</div>
                    @endif
                    @error("documents.{$type->id}")
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
        </div>

    </div>

    <div class="wizard-nav">
        <button type="button" class="btn ghost" id="wizard-prev-btn">Précédent</button>
        <div class="wizard-nav-right">
            <button type="button" class="btn dark" id="wizard-next-btn">Suivant</button>
            <button type="submit" class="btn primary" id="wizard-finish-btn">Terminer</button>
        </div>
    </div>
</form>

{{-- Right-hand info card: one panel per étape (see eleve-wizard.js's
     goToStep(), which toggles [data-wizard-info] alongside the step content
     itself), so the tip shown always matches whichever étape is on screen —
     same idea as the reference mockup's "À propos de ce service" sidebar. --}}
<aside class="wizard-info-card">
    <h3 class="wizard-info-title">Informations utiles</h3>

    <div class="wizard-info-content" data-wizard-info="1" style="@if ($activeStep !== 1) display:none; @endif">
        <p>La classe désirée détermine les documents demandés à l'étape « Documents ».</p>
        <p>Si vous choisissez <b>Maternelle 1</b> ou <b>Maternelle 2</b>, aucun bulletin ni certificat d'une école antérieure ne sera demandé : ces classes marquent le début de la scolarisation.</p>
        <p>Pour toute autre classe, ces documents seront requis pour terminer la fiche.</p>
    </div>

    <div class="wizard-info-content" data-wizard-info="2" style="@if ($activeStep !== 2) display:none; @endif">
        <p>Le <b>matricule</b> est délivré par Educmaster (le système du gouvernement) : renseignez-le si vous l'avez déjà, sinon complétez-le plus tard.</p>
        <p>Les champs marqués d'un <b>*</b> sont obligatoires pour « Terminer » la fiche — ils sont configurables depuis « Paramètres des dossiers ».</p>
    </div>

    <div class="wizard-info-content" data-wizard-info="3" style="@if ($activeStep !== 3) display:none; @endif">
        <p>En tapant le nom et prénom d'un parent/tuteur, une recherche rapide vérifie s'il est déjà enregistré (par exemple pour un frère ou une sœur déjà inscrit).</p>
        <p>S'il existe, sélectionnez-le dans les suggestions : il sera associé à cette fiche avec le lien de parenté choisi, au lieu d'être dupliqué.</p>
    </div>

    <div class="wizard-info-content" data-wizard-info="4" style="@if ($activeStep !== 4) display:none; @endif">
        <p>Les documents marqués d'un <b>*</b> sont obligatoires pour « Terminer » la fiche.</p>
        <p>Le bulletin et le certificat de l'école précédente ne sont demandés que si la classe désirée choisie à l'étape 1 n'est pas Maternelle 1 ou 2.</p>
        <p>Un document déjà fourni lors d'un enregistrement précédent reste conservé : ne choisissez un fichier que pour le remplacer.</p>
    </div>
</aside>
</div>

@endsection
