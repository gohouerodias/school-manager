@extends('layouts.app')

@section('title', 'Liste des apprenants')

@section('content')
<x-page-header title="Liste des apprenants" :subtitle="$subtitle">
    <x-slot:actions>
        <x-export-buttons :excel-route="route('eleves.export.excel')" :pdf-route="route('eleves.export.pdf')" />
        <button type="button" class="btn primary" data-panel-open="new-eleve">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Nouvel apprenant
        </button>
    </x-slot:actions>
</x-page-header>

<form method="GET" action="{{ route('eleves.index') }}" class="toolbar">
    <x-toolbar-search name="search" :value="$search" placeholder="Rechercher par nom, prénom ou matricule..." />

    <x-filter-select name="classe" :selected="$classeFilter" placeholder="Toutes les classes" :options="
        collect(['sans_classe' => 'Sans classe attribuée'])
            ->merge($classes->mapWithKeys(fn ($classe) => [(string) $classe->id => $classe->niveau->libelle.' — '.$classe->nom]))
            ->all()
    " />

    <x-filter-select name="statut" :selected="$statutFilter" placeholder="Tous les statuts" :options="[
        'actif' => 'Actif',
        'archive' => 'Archivé',
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

@if ($eleves->isEmpty())
    <p class="table-empty-state">Aucun apprenant ne correspond à votre recherche.</p>
@endif

<x-data-table id="eleves-table">
    <x-slot:head>
        <th>Apprenant</th>
        <th>Classe</th>
        <th>Statut</th>
        <th>Date de création</th>
        <th>Documents</th>
        <th></th>
    </x-slot:head>

    @foreach ($eleves as $eleve)
        @php
            $isArchived = $eleve->statut === \App\Enums\StatutEleve::Archive;
            $inscription = $eleve->inscriptions->first();
            $missingCount = $obligatoireTypeIds->diff($eleve->documents->pluck('type_document_id'))->count();
            $champsMap = $eleve->valeursPersonnalisees->pluck('valeur', 'champ_personnalise_id');
        @endphp
        <tr>
            <td class="name-cell">
                <div class="avatar avatar-neutral">{{ mb_strtoupper(mb_substr($eleve->nom, 0, 1).mb_substr($eleve->prenom, 0, 1)) }}</div>
                <div class="info"><b>{{ $eleve->nomComplet() }}</b><span>{{ $eleve->matricule }}</span></div>
            </td>
            <td>
                @if ($inscription?->classe)
                    <span class="classe-badge">{{ $inscription->classe->niveau->libelle }} — {{ $inscription->classe->nom }}</span>
                @elseif ($eleve->niveauSouhaite)
                    <span class="classe-badge none">Sans classe — {{ $eleve->niveauSouhaite->libelle }} souhaité</span>
                @else
                    <span class="classe-badge none">Sans classe</span>
                @endif
            </td>
            <td>
                <span @class(['status', $isArchived ? 'archived' : 'active'])>
                    <span class="dot"></span>{{ $isArchived ? 'Archivé' : 'Actif' }}
                </span>
            </td>
            <td>{{ $eleve->created_at->format('d/m/Y') }}</td>
            <td>
                @if ($missingCount === 0)
                    <span class="doc-status-badge complet">✓ Complet</span>
                @else
                    <span class="doc-status-badge manquant">⚠ {{ $missingCount }} manquant(s)</span>
                @endif
            </td>
            <td>
                <x-action-menu>
                    <button
                        type="button"
                        data-panel-open="fiche"
                        data-fiche-trigger
                        data-fiche-url="{{ route('eleves.fiche', $eleve) }}"
                    >👁 Consulter la fiche</button>

                    @if ($isArchived)
                        <hr>
                        <form method="POST" action="{{ route('eleves.desarchiver', $eleve) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="positive">↺ Désarchiver</button>
                        </form>
                    @else
                        <button
                            type="button"
                            data-panel-open="edit-eleve"
                            data-edit-eleve-trigger
                            data-edit-url="{{ route('eleves.update', $eleve) }}"
                            data-edit-nom="{{ $eleve->nom }}"
                            data-edit-prenom="{{ $eleve->prenom }}"
                            data-edit-sexe="{{ $eleve->sexe }}"
                            data-edit-date-naissance="{{ $eleve->date_naissance->format('Y-m-d') }}"
                            data-edit-niveau-souhaite-id="{{ $eleve->niveau_souhaite_id }}"
                            data-edit-champs="{{ json_encode($champsMap) }}"
                        >✎ Modifier la fiche</button>
                        <hr>
                        <form method="POST" action="{{ route('eleves.archiver', $eleve) }}" onsubmit="return confirm('Archiver cette fiche ?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="danger">🗄 Archiver</button>
                        </form>
                    @endif
                </x-action-menu>
            </td>
        </tr>
    @endforeach
</x-data-table>

<x-pagination :paginator="$eleves" />

{{-- Nouvel apprenant --}}
<x-slide-panel id="new-eleve" title="Nouvel apprenant">
    <form method="POST" action="{{ route('eleves.store') }}" id="new-eleve-form">
        @csrf
        <input type="hidden" name="_panel" value="new-eleve">

        @error('nom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('prenom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('sexe')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_naissance')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('niveau_souhaite_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @foreach ($champsPersonnalises as $champ)
            @error("champs.{$champ->id}")
                <div class="alert-error">{{ $champ->libelle }} : {{ $message }}</div>
            @enderror
        @endforeach

        <div class="field">
            <label for="new-eleve-nom">Nom</label>
            <input type="text" id="new-eleve-nom" name="nom" value="{{ old('nom') }}" required>
        </div>

        <div class="field">
            <label for="new-eleve-prenom">Prénom(s)</label>
            <input type="text" id="new-eleve-prenom" name="prenom" value="{{ old('prenom') }}" required>
        </div>

        <div class="field">
            <label for="new-eleve-sexe">Sexe</label>
            <select class="role-select" id="new-eleve-sexe" name="sexe" required>
                <option value="M" @selected(old('sexe', 'M') === 'M')>Masculin</option>
                <option value="F" @selected(old('sexe') === 'F')>Féminin</option>
            </select>
        </div>

        <div class="field">
            <label for="new-eleve-date-naissance">Date de naissance</label>
            <input type="date" id="new-eleve-date-naissance" name="date_naissance" value="{{ old('date_naissance') }}" required>
        </div>

        @foreach ($champsPersonnalises as $champ)
            <div class="field">
                <label for="new-champ-{{ $champ->id }}">{{ $champ->libelle }}{{ $champ->obligatoire ? ' *' : '' }}</label>
                @if ($champ->type->value === 'liste_deroulante')
                    <select class="role-select" id="new-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" @if ($champ->obligatoire) required @endif>
                        <option value="">—</option>
                        @foreach ($champ->options ?? [] as $option)
                            <option value="{{ $option }}" @selected(old("champs.{$champ->id}") === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif ($champ->type->value === 'date')
                    <input type="date" id="new-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}") }}" @if ($champ->obligatoire) required @endif>
                @elseif ($champ->type->value === 'nombre')
                    <input type="number" id="new-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}") }}" @if ($champ->obligatoire) required @endif>
                @else
                    <input type="text" id="new-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}") }}" @if ($champ->obligatoire) required @endif>
                @endif
            </div>
        @endforeach

        <div class="field">
            <label for="new-eleve-niveau-souhaite">Classe désirée</label>
            <select class="role-select" id="new-eleve-niveau-souhaite" name="niveau_souhaite_id">
                <option value="">Non précisé</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected((string) old('niveau_souhaite_id') === (string) $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
            <div class="hint">Le matricule sera généré automatiquement à l'enregistrement. Ce niveau est indicatif pour la répartition à venir : l'apprenant reste « Sans classe attribuée » tant qu'une classe précise ne lui est pas assignée. Les documents s'ajoutent ensuite depuis l'onglet « Documents » de la fiche.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-eleve">Annuler</button>
        <button type="submit" form="new-eleve-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier la fiche --}}
<x-slide-panel id="edit-eleve" title="Modifier la fiche">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-eleve-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-eleve">
        <input type="hidden" name="_edit_url" id="edit-eleve-edit-url" value="{{ old('_edit_url') }}">

        @error('nom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('prenom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('sexe')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_naissance')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('niveau_souhaite_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @foreach ($champsPersonnalises as $champ)
            @error("champs.{$champ->id}")
                <div class="alert-error">{{ $champ->libelle }} : {{ $message }}</div>
            @enderror
        @endforeach

        <div class="field">
            <label for="edit-eleve-nom">Nom</label>
            <input type="text" id="edit-eleve-nom" name="nom" value="{{ old('nom') }}" required>
        </div>

        <div class="field">
            <label for="edit-eleve-prenom">Prénom(s)</label>
            <input type="text" id="edit-eleve-prenom" name="prenom" value="{{ old('prenom') }}" required>
        </div>

        <div class="field">
            <label for="edit-eleve-sexe">Sexe</label>
            <select class="role-select" id="edit-eleve-sexe" name="sexe" required>
                <option value="M" @selected(old('sexe') === 'M')>Masculin</option>
                <option value="F" @selected(old('sexe') === 'F')>Féminin</option>
            </select>
        </div>

        <div class="field">
            <label for="edit-eleve-date-naissance">Date de naissance</label>
            <input type="date" id="edit-eleve-date-naissance" name="date_naissance" value="{{ old('date_naissance') }}" required>
        </div>

        @foreach ($champsPersonnalises as $champ)
            <div class="field">
                <label for="edit-champ-{{ $champ->id }}">{{ $champ->libelle }}{{ $champ->obligatoire ? ' *' : '' }}</label>
                @if ($champ->type->value === 'liste_deroulante')
                    <select class="role-select" id="edit-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" @if ($champ->obligatoire) required @endif>
                        <option value="">—</option>
                        @foreach ($champ->options ?? [] as $option)
                            <option value="{{ $option }}" @selected(old("champs.{$champ->id}") === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif ($champ->type->value === 'date')
                    <input type="date" id="edit-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}") }}" @if ($champ->obligatoire) required @endif>
                @elseif ($champ->type->value === 'nombre')
                    <input type="number" id="edit-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}") }}" @if ($champ->obligatoire) required @endif>
                @else
                    <input type="text" id="edit-champ-{{ $champ->id }}" name="champs[{{ $champ->id }}]" value="{{ old("champs.{$champ->id}") }}" @if ($champ->obligatoire) required @endif>
                @endif
            </div>
        @endforeach

        <div class="field">
            <label for="edit-eleve-niveau-souhaite">Classe désirée</label>
            <select class="role-select" id="edit-eleve-niveau-souhaite" name="niveau_souhaite_id">
                <option value="">Non précisé</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected((string) old('niveau_souhaite_id') === (string) $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
            <div class="hint">Indicatif tant qu'aucune classe précise n'est assignée.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-eleve">Annuler</button>
        <button type="submit" form="edit-eleve-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Fiche apprenant (consultation) --}}
<x-fiche-modal
    id="fiche"
    data-tuteur-url-template="{{ route('eleves.tuteurs.store', ['eleve' => '__ID__']) }}"
    data-document-url-template="{{ route('eleves.documents.store', ['eleve' => '__ID__']) }}"
    data-tuteur-delete-url-template="{{ route('eleves.tuteurs.destroy', ['eleve' => '__EID__', 'parentTuteur' => '__PID__']) }}"
    data-document-delete-url-template="{{ route('eleves.documents.destroy', ['eleve' => '__EID__', 'document' => '__DID__']) }}"
    data-document-view-url-template="{{ route('eleves.documents.show', ['eleve' => '__EID__', 'document' => '__DID__']) }}"
    data-document-download-url-template="{{ route('eleves.documents.download', ['eleve' => '__EID__', 'document' => '__DID__']) }}"
>
    <div class="fiche-head">
        <div class="fiche-avatar" id="fiche-avatar"></div>
        <div class="fiche-head-info">
            <h2 id="fiche-nom"></h2>
            <div class="fiche-meta">
                <span class="classe-badge" id="fiche-matricule"></span>
                <span class="status active" id="fiche-statut"></span>
            </div>
        </div>
        <button type="button" class="panel-close" data-panel-close="fiche">✕</button>
    </div>

    <div class="fiche-tabs">
        <button type="button" class="fiche-tab active" data-fiche-tab="identite">Identité</button>
        <button type="button" class="fiche-tab" data-fiche-tab="parents">Parents/Tuteurs</button>
        <button type="button" class="fiche-tab" data-fiche-tab="parcours">Parcours scolaire</button>
        <button type="button" class="fiche-tab" data-fiche-tab="documents">Documents</button>
    </div>

    <div class="fiche-tab-content" data-fiche-content="identite" style="display:block;">
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

        <div class="field">
            <label for="tuteur-nom-prenom">Nom et prénom</label>
            <input type="text" id="tuteur-nom-prenom" name="nom_prenom" placeholder="Ex : Grégoire Ahouansou" value="{{ old('nom_prenom') }}" required>
            <div class="hint">Si ce nom, prénom et téléphone correspondent à un tuteur déjà enregistré (ex : parent d'un autre élève), il sera lié à cette fiche plutôt que dupliqué.</div>
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
