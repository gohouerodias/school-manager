@extends('layouts.app')

@section('title', 'Liste des tuteurs')

@section('content')
<x-page-header title="Liste des tuteurs" :subtitle="$subtitle" />

<form method="GET" action="{{ route('tuteurs.index') }}" class="toolbar">
    <x-toolbar-search
        name="search"
        :value="$search"
        placeholder="Rechercher par nom, prénom ou téléphone..."
        :live-search-url="route('tuteurs.index')"
        live-search-target="tuteurs-table-region"
    />

    <button type="submit" class="btn ghost">Filtrer</button>

    @if ($search !== '')
        <a href="{{ route('tuteurs.index') }}" class="btn ghost">Réinitialiser</a>
    @endif
</form>

<div id="tuteurs-table-region">
    @include('tuteurs.partials.table')
</div>

{{-- Modifier un tuteur (infos partagées : pas de lien de parenté ici, voir
     UpdateTuteurInfoRequest) --}}
<x-slide-panel id="edit-tuteur-info" title="Modifier le tuteur">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-tuteur-info-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-tuteur-info">
        <input type="hidden" name="_edit_url" id="edit-tuteur-info-edit-url" value="{{ old('_edit_url') }}">

        @error('nom_prenom')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('telephone')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('email')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="edit-tuteur-info-nom-prenom">Nom et prénom</label>
            <input type="text" id="edit-tuteur-info-nom-prenom" name="nom_prenom" value="{{ old('nom_prenom') }}" required>
            <div class="hint">Ce tuteur peut être lié à plusieurs élèves (fratrie) : la modification s'applique partout où il est enregistré.</div>
        </div>

        <div class="field">
            <label for="edit-tuteur-info-telephone">Téléphone</label>
            <input type="tel" id="edit-tuteur-info-telephone" name="telephone" value="{{ old('telephone') }}" required>
        </div>

        <div class="field">
            <label for="edit-tuteur-info-email">Adresse e-mail (optionnel)</label>
            <input type="email" id="edit-tuteur-info-email" name="email" value="{{ old('email') }}">
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-tuteur-info">Annuler</button>
        <button type="submit" form="edit-tuteur-info-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Voir les enfants liés à ce tuteur --}}
<x-fiche-modal id="tuteur-enfants">
    <div class="fiche-breadcrumb-row">
        <span class="fiche-breadcrumb-text">Dossier élève et documents / Liste des tuteurs</span>
        <button type="button" class="panel-close" data-panel-close="tuteur-enfants">✕</button>
    </div>

    <div class="fiche-head">
        <div class="fiche-avatar">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="fiche-head-info">
            <div class="fiche-head-title-row">
                <h2 id="tuteur-enfants-nom"></h2>
            </div>
            <div class="fiche-meta-line">
                Téléphone : <b id="tuteur-enfants-telephone"></b> &nbsp; Email : <b id="tuteur-enfants-email">—</b>
            </div>
        </div>
    </div>

    <div class="fiche-tab-content" style="display:block; max-height:50vh;">
        <div id="tuteur-enfants-list"></div>
    </div>
</x-fiche-modal>
@endsection
