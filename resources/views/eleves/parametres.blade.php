@extends('layouts.app')

@section('title', 'Paramètres des dossiers')

@section('content')
<x-page-header title="Paramètres des dossiers" subtitle="Types de documents attendus et champs du formulaire apprenant" />

<section class="config-section">
    <div class="config-section-head">
        <h2>Types de documents</h2>
        <button type="button" class="btn primary" data-panel-open="new-type-document">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter un type de document
        </button>
    </div>

    <x-data-table id="types-documents-table">
        <x-slot:head>
            <th>Nom</th>
            <th>Formats acceptés</th>
            <th>Obligatoire</th>
            <th></th>
        </x-slot:head>

        @foreach ($typesDocuments as $type)
            <tr>
                <td>
                    <b>{{ $type->libelle }}</b>
                    @if ($type->protege)
                        <span class="lock-badge" title="Ce type de document est protégé : il ne peut pas être modifié ni supprimé.">🔒 Protégé</span>
                    @endif
                </td>
                <td>
                    <div class="chips">
                        @foreach ($type->formats_acceptes ?? [] as $format)
                            <span class="chip">{{ $format }}</span>
                        @endforeach
                    </div>
                </td>
                <td>
                    <form method="POST" action="{{ route('eleves.parametres.types-documents.update', $type) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="libelle" value="{{ $type->libelle }}">
                        @foreach ($type->formats_acceptes ?? [] as $format)
                            <input type="hidden" name="formats_acceptes[]" value="{{ $format }}">
                        @endforeach
                        <input type="hidden" name="obligatoire" id="type-obligatoire-hidden-{{ $type->id }}" value="{{ $type->obligatoire ? '1' : '0' }}">
                        <label class="toggle-switch">
                            <input
                                type="checkbox"
                                @checked($type->obligatoire)
                                @disabled($type->protege)
                                onchange="document.getElementById('type-obligatoire-hidden-{{ $type->id }}').value = this.checked ? '1' : '0'; this.form.requestSubmit();"
                            >
                            <span class="toggle-slider"></span>
                        </label>
                    </form>
                </td>
                <td>
                    @if ($type->protege)
                        <div class="row-actions-group">
                            <span class="row-edit disabled" title="Type de document protégé : non modifiable">🔒</span>
                        </div>
                    @else
                        <div class="row-actions-group">
                            <button
                                type="button"
                                class="row-edit"
                                title="Modifier"
                                data-panel-open="edit-type-document"
                                data-edit-type-document-trigger
                                data-edit-url="{{ route('eleves.parametres.types-documents.update', $type) }}"
                                data-edit-libelle="{{ $type->libelle }}"
                                data-edit-formats="{{ implode(',', $type->formats_acceptes ?? []) }}"
                                data-edit-obligatoire="{{ $type->obligatoire ? '1' : '0' }}"
                            >✎</button>
                            <form method="POST" action="{{ route('eleves.parametres.types-documents.destroy', $type) }}" onsubmit="return confirm('Supprimer ce type de document ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</section>

<section class="config-section">
    <div class="config-section-head">
        <h2>Champs du formulaire apprenant</h2>
        <button type="button" class="btn primary" data-panel-open="new-champ">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Ajouter un champ
        </button>
    </div>

    <p class="config-section-note">Nom, Prénom, Sexe et Date de naissance sont des champs fixes et n'apparaissent pas ici.</p>

    <x-data-table id="champs-table">
        <x-slot:head>
            <th>Nom</th>
            <th>Type</th>
            <th>Obligatoire</th>
            <th></th>
        </x-slot:head>

        @foreach ($champsPersonnalises as $champ)
            <tr>
                <td><b>{{ $champ->libelle }}</b></td>
                <td>
                    <span class="type-badge">{{ $champ->type->label() }}</span>
                    @if ($champ->type->value === 'liste_deroulante')
                        <div class="chips">
                            @foreach ($champ->options ?? [] as $option)
                                <span class="chip">{{ $option }}</span>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td>
                    <form method="POST" action="{{ route('eleves.parametres.champs.update', $champ) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="libelle" value="{{ $champ->libelle }}">
                        <input type="hidden" name="type" value="{{ $champ->type->value }}">
                        @foreach ($champ->options ?? [] as $option)
                            <input type="hidden" name="options[]" value="{{ $option }}">
                        @endforeach
                        <input type="hidden" name="obligatoire" id="champ-obligatoire-hidden-{{ $champ->id }}" value="{{ $champ->obligatoire ? '1' : '0' }}">
                        <label class="toggle-switch">
                            <input
                                type="checkbox"
                                @checked($champ->obligatoire)
                                onchange="document.getElementById('champ-obligatoire-hidden-{{ $champ->id }}').value = this.checked ? '1' : '0'; this.form.requestSubmit();"
                            >
                            <span class="toggle-slider"></span>
                        </label>
                    </form>
                </td>
                <td>
                    <form method="POST" action="{{ route('eleves.parametres.champs.destroy', $champ) }}" onsubmit="return confirm('Supprimer ce champ ? Les valeurs déjà saisies pour les apprenants seront perdues.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</section>

{{-- Ajouter un type de document --}}
<x-slide-panel id="new-type-document" title="Ajouter un type de document">
    <form method="POST" action="{{ route('eleves.parametres.types-documents.store') }}" id="new-type-document-form">
        @csrf
        <input type="hidden" name="_panel" value="new-type-document">

        @error('libelle')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('formats_acceptes')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-type-libelle">Nom du document</label>
            <input type="text" id="new-type-libelle" name="libelle" placeholder="Ex : Certificat de résidence" value="{{ old('libelle') }}" required>
        </div>

        <div class="field">
            <label>Formats acceptés</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="formats_acceptes[]" value="PDF" @checked(in_array('PDF', old('formats_acceptes', [])))> PDF</label>
                <label><input type="checkbox" name="formats_acceptes[]" value="JPG" @checked(in_array('JPG', old('formats_acceptes', [])))> JPG</label>
                <label><input type="checkbox" name="formats_acceptes[]" value="PNG" @checked(in_array('PNG', old('formats_acceptes', [])))> PNG</label>
            </div>
        </div>

        <div class="field field-inline">
            <label class="toggle-switch">
                <input type="checkbox" name="obligatoire" value="1" @checked(old('obligatoire'))>
                <span class="toggle-slider"></span>
            </label>
            <span>Document obligatoire</span>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-type-document">Annuler</button>
        <button type="submit" form="new-type-document-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier un type de document --}}
<x-slide-panel id="edit-type-document" title="Modifier le type de document">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-type-document-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-type-document">
        <input type="hidden" name="_edit_url" id="edit-type-document-edit-url" value="{{ old('_edit_url') }}">

        @error('libelle')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        @error('formats_acceptes')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="edit-type-libelle">Nom du document</label>
            <input type="text" id="edit-type-libelle" name="libelle" placeholder="Ex : Certificat de résidence" value="{{ old('libelle') }}" required>
        </div>

        <div class="field">
            <label>Formats acceptés</label>
            <div class="checkbox-group">
                <label><input type="checkbox" id="edit-type-format-pdf" name="formats_acceptes[]" value="PDF" @checked(in_array('PDF', old('formats_acceptes', [])))> PDF</label>
                <label><input type="checkbox" id="edit-type-format-jpg" name="formats_acceptes[]" value="JPG" @checked(in_array('JPG', old('formats_acceptes', [])))> JPG</label>
                <label><input type="checkbox" id="edit-type-format-png" name="formats_acceptes[]" value="PNG" @checked(in_array('PNG', old('formats_acceptes', [])))> PNG</label>
            </div>
        </div>

        <div class="field field-inline">
            <input type="hidden" id="edit-type-obligatoire-hidden" name="obligatoire" value="{{ old('obligatoire', '0') }}">
            <label class="toggle-switch">
                <input type="checkbox" id="edit-type-obligatoire" @checked(old('obligatoire'))>
                <span class="toggle-slider"></span>
            </label>
            <span>Document obligatoire</span>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-type-document">Annuler</button>
        <button type="submit" form="edit-type-document-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter un champ --}}
<x-slide-panel id="new-champ" title="Ajouter un champ">
    <form method="POST" action="{{ route('eleves.parametres.champs.store') }}" id="new-champ-form">
        @csrf
        <input type="hidden" name="_panel" value="new-champ">

        @error('libelle')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('type')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('options_raw')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-champ-libelle">Nom du champ</label>
            <input type="text" id="new-champ-libelle" name="libelle" placeholder="Ex : Régime alimentaire" value="{{ old('libelle') }}" required>
        </div>

        <div class="field">
            <label for="new-champ-type">Type</label>
            <select class="role-select" id="new-champ-type" name="type" data-champ-type-select required>
                @foreach (\App\Enums\TypeChampPersonnalise::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="field" id="new-champ-options-field" style="display:{{ old('type') === 'liste_deroulante' ? 'block' : 'none' }};">
            <label for="new-champ-options">Options (une par ligne)</label>
            <textarea id="new-champ-options" name="options_raw" rows="4" placeholder="Option 1&#10;Option 2">{{ old('options_raw') }}</textarea>
        </div>

        <div class="field field-inline">
            <label class="toggle-switch">
                <input type="checkbox" name="obligatoire" value="1" @checked(old('obligatoire'))>
                <span class="toggle-slider"></span>
            </label>
            <span>Champ obligatoire</span>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-champ">Annuler</button>
        <button type="submit" form="new-champ-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>
@endsection
