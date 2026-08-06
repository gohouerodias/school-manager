@extends('layouts.app')

@section('title', 'Liste des apprenants')

@section('content')
<x-page-header title="Liste des apprenants" :subtitle="$subtitle">
    <x-slot:actions>
        @php
            $activeFilters = array_filter([
                'search' => $search,
                'classe' => $classeFilter,
                'statut' => $statutFilter,
                'date_creation' => $dateFilter,
            ]);
        @endphp
        <x-export-buttons
            :excel-route="route('eleves.export.excel', $activeFilters)"
            :pdf-route="route('eleves.export.pdf', $activeFilters)"
        />
        <a href="{{ route('eleves.wizard.create') }}" class="btn primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Nouvel apprenant
        </a>
    </x-slot:actions>
</x-page-header>

<form method="GET" action="{{ route('eleves.index') }}" class="toolbar">
    <x-toolbar-search
        name="search"
        :value="$search"
        placeholder="Rechercher par nom, prénom ou matricule..."
        :live-search-url="route('eleves.index')"
        live-search-target="eleves-table-region"
    />

    <x-filter-select name="classe" :selected="$classeFilter" placeholder="Toutes les classes" :options="
        ['sans_classe' => 'Sans classe attribuée'] +
        $classes->mapWithKeys(fn ($classe) => [(string) $classe->id => $classe->niveau->libelle.' — '.$classe->nom])->all()
    " />

    <x-filter-select name="statut" :selected="$statutFilter" placeholder="Tous les statuts" :options="[
        'actif' => 'Actif',
        'archive' => 'Archivé',
        'brouillon' => 'Brouillon',
    ]" />

    <div class="date-filter-wrap">
        <label for="date_creation">Créées le :</label>
        <input type="date" id="date_creation" name="date_creation" value="{{ $dateFilter }}">
    </div>

    <button type="submit" class="btn ghost">Filtrer</button>

    @if ($search !== '' || $classeFilter !== '' || $statutFilter !== '' || $dateFilter !== '')
        <a href="{{ route('eleves.index') }}" class="btn ghost">Réinitialiser</a>
    @endif
</form>

<div id="eleves-table-region">
    @include('eleves.partials.table')
</div>

{{-- Hidden singleton form submitted by eleve-classe-assign.js once the
     confirmation modal is accepted — action + classe_id are set from the
     picked row/select right before submit(), same "one shared form"
     pattern as the fiche's add-tuteur/add-document panels. --}}
<form method="POST" action="" id="classe-assign-form" style="display:none;">
    @csrf
    @method('PATCH')
    <input type="hidden" name="classe_id" id="classe-assign-classe-id">
</form>

{{-- Same pattern, for eleve-statut-assign.js: submits to whichever of
     eleves.archiver / eleves.desarchiver matches the picked option, once
     the confirmation modal is accepted. No extra body fields needed —
     both routes take none. --}}
<form method="POST" action="" id="statut-assign-form" style="display:none;">
    @csrf
    @method('PATCH')
</form>

{{-- Fiche apprenant (consultation) --}}
<x-fiche-modal
    id="fiche"
    data-tuteur-url-template="{{ route('eleves.tuteurs.store', ['eleve' => '__ID__']) }}"
    data-document-url-template="{{ route('eleves.documents.store', ['eleve' => '__ID__']) }}"
    data-tuteur-update-url-template="{{ route('eleves.tuteurs.update', ['eleve' => '__EID__', 'parentTuteur' => '__PID__']) }}"
    data-tuteur-delete-url-template="{{ route('eleves.tuteurs.destroy', ['eleve' => '__EID__', 'parentTuteur' => '__PID__']) }}"
    data-document-delete-url-template="{{ route('eleves.documents.destroy', ['eleve' => '__EID__', 'document' => '__DID__']) }}"
    data-document-view-url-template="{{ route('eleves.documents.show', ['eleve' => '__EID__', 'document' => '__DID__']) }}"
    data-document-download-url-template="{{ route('eleves.documents.download', ['eleve' => '__EID__', 'document' => '__DID__']) }}"
    data-edit-eleve-url-template="{{ route('eleves.wizard.edit', ['eleve' => '__ID__']) }}"
    data-tuteur-recherche-url="{{ route('eleves.wizard.tuteurs.recherche') }}"
>
    <div class="fiche-breadcrumb-row">
        <span class="fiche-breadcrumb-text">Dossier élève et documents / Liste des apprenants</span>
        <button type="button" class="panel-close" data-panel-close="fiche">✕</button>
    </div>

    <div class="fiche-head">
        <div class="fiche-avatar">
            {{-- Cliquable uniquement quand une vraie photo est affichée (voir
                 .fiche-avatar img { cursor: pointer } et le click listener
                 dans eleve-fiche.js) : ouvre resources/js/image-lightbox.js. --}}
            <img id="fiche-avatar-img" src="" alt="" style="display:none;">
            <svg id="fiche-avatar-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="fiche-head-info">
            <div class="fiche-head-title-row">
                <h2><span id="fiche-nom-famille"></span> <span id="fiche-prenom"></span></h2>
                <span class="classe-badge" id="fiche-matricule"></span>
            </div>
            <div class="fiche-meta-line">
                Classe : <b id="fiche-classe">—</b> &nbsp; Statut : <b id="fiche-statut"></b> &nbsp; Date de création : <b id="fiche-date-creation"></b>
            </div>
        </div>
    </div>

    <div class="fiche-tabs">
        <button type="button" class="fiche-tab active" data-fiche-tab="identite">Identité</button>
        <button type="button" class="fiche-tab" data-fiche-tab="parents">Parents/Tuteurs</button>
        <button type="button" class="fiche-tab" data-fiche-tab="parcours">Parcours scolaire</button>
        <button type="button" class="fiche-tab" data-fiche-tab="documents">Documents</button>
    </div>

    <div class="fiche-tab-content" data-fiche-content="identite" style="display:block;">
        <div class="fiche-actions-row">
            {{-- Static link, re-pointed on every fiche render (see
                 eleve-fiche.js's renderFiche() -> populateEditEleveTrigger())
                 to the fiche élève wizard's "modifier" page for whichever
                 élève is currently open — the wizard prefills itself
                 server-side from the Eleve model, so no data-edit-* payload
                 is needed here anymore. --}}
            <a class="btn ghost" id="fiche-edit-eleve-trigger" href="#">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                Modifier
            </a>
        </div>
        <div class="fiche-grid" id="fiche-identite-grid"></div>
    </div>
    <div class="fiche-tab-content" data-fiche-content="parents" style="display:none;">
        <div class="fiche-actions-row">
            <button type="button" class="btn primary" data-panel-open="add-tuteur">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Ajouter un tuteur
            </button>
        </div>
        <div id="fiche-parents-list"></div>
    </div>
    <div class="fiche-tab-content" data-fiche-content="parcours" style="display:none;">
        <div id="fiche-parcours-list"></div>
    </div>
    <div class="fiche-tab-content" data-fiche-content="documents" style="display:none;">
        <div class="fiche-actions-row">
            <button type="button" class="btn primary" data-panel-open="add-document">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Ajouter un document
            </button>
        </div>
        <div class="fiche-grid" id="fiche-documents-grid"></div>
    </div>
</x-fiche-modal>

{{-- Ajouter un tuteur (depuis la fiche ouverte) --}}
<x-slide-panel id="add-tuteur" title="Ajouter un tuteur">
    <form method="POST" action="{{ old('_action', '') }}" id="add-tuteur-form">
        @csrf
        <input type="hidden" name="_panel" value="add-tuteur">
        <input type="hidden" name="_action" id="add-tuteur-action" value="{{ old('_action') }}">
        <input type="hidden" name="existing_id" id="tuteur-existing-id" value="{{ old('existing_id') }}">

        @error('nom_prenom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('lien_parente')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('telephone')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('email')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div id="tuteur-match" class="wizard-tuteur-match" style="display:none;"></div>

        <div class="field">
            <label for="tuteur-nom-prenom">Nom et prénom</label>
            <input type="text" id="tuteur-nom-prenom" name="nom_prenom" placeholder="Ex : Grégoire Ahouansou" value="{{ old('nom_prenom') }}" required autocomplete="off">
            <div id="tuteur-suggestions" class="wizard-tuteur-suggestions"></div>
            <div class="hint">Si ce tuteur est déjà enregistré (ex : parent d'un autre élève), il sera proposé ci-dessus pendant la saisie et lié à cette fiche plutôt que dupliqué.</div>
        </div>

        <div class="field">
            <label for="tuteur-lien">Lien de parenté</label>
            <select class="role-select" id="tuteur-lien" name="lien_parente" required>
                @foreach (['Père', 'Mère', 'Tuteur légal', 'Autre'] as $lien)
                    <option @selected(old('lien_parente', 'Père') === $lien)>{{ $lien }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="tuteur-telephone">Téléphone</label>
            <input type="tel" id="tuteur-telephone" name="telephone" placeholder="+229 XX XX XX XX" value="{{ old('telephone') }}" required>
        </div>

        <div class="field">
            <label for="tuteur-email">Adresse e-mail (optionnel)</label>
            <input type="email" id="tuteur-email" name="email" placeholder="exemple@email.com" value="{{ old('email') }}">
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="add-tuteur">Annuler</button>
        <button type="submit" form="add-tuteur-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier un tuteur (depuis la fiche ouverte) --}}
<x-slide-panel id="edit-tuteur" title="Modifier le tuteur">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-tuteur-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-tuteur">
        <input type="hidden" name="_edit_url" id="edit-tuteur-edit-url" value="{{ old('_edit_url') }}">
        <input type="hidden" name="existing_id" id="edit-tuteur-existing-id" value="{{ old('existing_id') }}">

        @error('nom_prenom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('lien_parente')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('telephone')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('email')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div id="edit-tuteur-match" class="wizard-tuteur-match" style="display:none;"></div>

        <div class="field">
            <label for="edit-tuteur-nom-prenom">Nom et prénom</label>
            <input type="text" id="edit-tuteur-nom-prenom" name="nom_prenom" placeholder="Ex : Grégoire Ahouansou" value="{{ old('nom_prenom') }}" required autocomplete="off">
            <div id="edit-tuteur-suggestions" class="wizard-tuteur-suggestions"></div>
            <div class="hint">Ce tuteur peut être lié à d'autres élèves (fratrie) : modifier ses informations ici les met à jour partout où il est enregistré. Si la nouvelle saisie correspond à un autre tuteur déjà enregistré, il sera proposé ci-dessus.</div>
        </div>

        <div class="field">
            <label for="edit-tuteur-lien">Lien de parenté</label>
            <select class="role-select" id="edit-tuteur-lien" name="lien_parente" required>
                @foreach (['Père', 'Mère', 'Tuteur légal', 'Autre'] as $lien)
                    <option @selected(old('lien_parente') === $lien)>{{ $lien }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="edit-tuteur-telephone">Téléphone</label>
            <input type="tel" id="edit-tuteur-telephone" name="telephone" placeholder="+229 XX XX XX XX" value="{{ old('telephone') }}" required>
        </div>

        <div class="field">
            <label for="edit-tuteur-email">Adresse e-mail (optionnel)</label>
            <input type="email" id="edit-tuteur-email" name="email" placeholder="exemple@email.com" value="{{ old('email') }}">
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-tuteur">Annuler</button>
        <button type="submit" form="edit-tuteur-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter un document (depuis la fiche ouverte) --}}
<x-slide-panel id="add-document" title="Ajouter un document">
    <form method="POST" action="{{ old('_action', '') }}" id="add-document-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_panel" value="add-document">
        <input type="hidden" name="_action" id="add-document-action" value="{{ old('_action') }}">

        @error('type_document_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('fichier')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="document-type">Type de document</label>
            <select class="role-select" id="document-type" name="type_document_id" required>
                @foreach ($typesDocuments as $type)
                    <option
                        value="{{ $type->id }}"
                        data-formats="{{ implode(',', $type->formats_acceptes ?? []) }}"
                        @selected((string) old('type_document_id') === (string) $type->id)
                    >
                        {{ $type->libelle }}{{ $type->obligatoire ? ' *' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Fichier</label>
            <div class="alert-error" id="document-file-client-error" style="display:none;">Sélectionnez un fichier avant d'enregistrer.</div>
            <div class="dropzone" id="document-dropzone" tabindex="0">
                <input type="file" id="document-file-input" name="fichier" hidden>
                <div class="dropzone-text" id="document-dropzone-text">
                    Glissez-déposez un fichier ici, ou <b>parcourez vos fichiers</b><br>
                    <span id="document-formats-hint">PDF, JPG ou PNG — 5 Mo maximum</span>
                </div>
                <div class="dropzone-filename" id="document-filename" style="display:none;"></div>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="add-document">Annuler</button>
        <button type="submit" form="add-document-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>
@endsection
