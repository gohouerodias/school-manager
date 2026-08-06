@extends('layouts.app')

@section('title', 'Gestion des comptes utilisateurs')

@section('content')
<x-page-header title="Gestion des comptes utilisateurs" :subtitle="$subtitle">
    <x-slot:actions>
        <x-export-buttons :excel-route="route('comptes.export.excel')" :pdf-route="route('comptes.export.pdf')" />
        <button type="button" class="btn primary" data-panel-open="invite">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Inviter un utilisateur
        </button>
    </x-slot:actions>
</x-page-header>

<form method="GET" action="{{ route('comptes.index') }}" class="toolbar">
    <x-toolbar-search name="search" :value="$search" placeholder="Rechercher un utilisateur par nom ou email..." />

    <x-filter-select name="profil" :selected="$profilFilter" placeholder="Tous les profils" :options="[
        'administrateur' => 'Administrateur',
        'agent_scolarite' => 'Agent de scolarité',
        'enseignant' => 'Enseignant',
        'direction' => 'Direction',
    ]" />

    <x-filter-select name="statut" :selected="$statutFilter" placeholder="Tous les statuts" :options="[
        'actif' => 'Actif',
        'attente' => 'Invitation en attente',
        'archive' => 'Archivé',
    ]" />

    <button type="submit" class="btn ghost">Filtrer</button>

    @if ($search !== '' || $profilFilter !== '' || $statutFilter !== '')
        <a href="{{ route('comptes.index') }}" class="btn ghost">Réinitialiser</a>
    @endif
</form>

@if ($users->isEmpty())
    <p class="table-empty-state">Aucun utilisateur ne correspond à votre recherche.</p>
@endif

<x-data-table id="comptes-table">
    <x-slot:head>
        <th>Utilisateur</th>
        <th>Profil</th>
        <th>Statut</th>
        <th>Dernière connexion</th>
        <th></th>
    </x-slot:head>

    @foreach ($users as $user)
        @php
            $isArchived = $user->statut === \App\Enums\StatutUtilisateur::Archive;
            $isPending = ! $isArchived && $user->estEnAttenteActivation();
        @endphp
        <tr @class(['pending' => $isPending])>
            <td class="name-cell">
                <x-avatar :name="$user->name" :profil="$user->profil" />
                <div class="info"><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div>
            </td>
            <td><x-role-badge :profil="$user->profil" /></td>
            <td><x-status-badge :user="$user" /></td>
            <td>{{ $user->derniere_connexion_at?->diffForHumans() ?? '—' }}</td>
            <td>
                <x-action-menu>
                    @if ($isArchived)
                        <form method="POST" action="{{ route('comptes.reactiver', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="positive">↺ Réactiver le compte</button>
                        </form>
                    @else
                        <button
                            type="button"
                            data-edit-user-trigger
                            data-panel-open="edit-user"
                            data-edit-url="{{ route('comptes.update', $user) }}"
                            data-edit-name="{{ $user->name }}"
                            data-edit-telephone="{{ $user->telephone }}"
                            data-edit-email="{{ $user->email }}"
                        >✎ Modifier le profil</button>
                        <hr>
                        <form method="POST" action="{{ route('comptes.archiver', $user) }}"
                              data-confirm-submit data-confirm-danger="1" data-confirm-label="Archiver"
                              data-confirm-title="Archiver le compte"
                              data-confirm-message="Archiver le compte de {{ $user->name }} ?">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="danger">🗄 Archiver le compte</button>
                        </form>
                    @endif
                </x-action-menu>
            </td>
        </tr>
    @endforeach
</x-data-table>

<x-pagination :paginator="$users" />

<x-slide-panel id="invite" title="Inviter un utilisateur">
    <form method="POST" action="{{ route('comptes.store') }}" id="invite-form">
        @csrf
        <input type="hidden" name="_panel" value="invite">

        @error('invites')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="invite-email">Adresse e-mail</label>
            <input type="email" id="invite-email" placeholder="prenom.nom@cscmadretrinidad.bj">
            <div class="hint">Un e-mail sera envoyé pour définir le mot de passe.</div>
        </div>

        <div class="field">
            <label for="invite-nom">Nom</label>
            <input type="text" id="invite-nom" placeholder="Nom de famille">
        </div>

        <div class="field">
            <label for="invite-prenoms">Prénoms</label>
            <input type="text" id="invite-prenoms" placeholder="Prénom(s)">
        </div>

        <div class="field">
            <label for="invite-telephone">Numéro de téléphone</label>
            <input type="tel" id="invite-telephone" placeholder="+229 XX XX XX XX">
        </div>

        <div class="field">
            <label for="invite-role">Profil</label>
            <select class="role-select" id="invite-role">
                @foreach ($profils as $profil)
                    <option value="{{ $profil->value }}" @selected($profil === \App\Enums\ProfilUtilisateur::AgentScolarite)>{{ $profil->label() }}</option>
                @endforeach
            </select>
            <div class="role-desc-box">
                <div class="rtitle" id="invite-role-desc-title"></div>
                <div class="rdesc" id="invite-role-desc-text"></div>
            </div>
        </div>

        <button type="button" class="btn add-pending" id="invite-add-btn">+ Ajouter à la liste</button>

        <div id="invite-pending-section" style="display:none;">
            <div class="pending-list-title">En attente d'enregistrement (<span id="invite-pending-count">0</span>)</div>
            <div id="invite-pending-list"></div>
        </div>
    </form>

    <x-slot:footer>
        <div class="callout" id="invite-callout" style="display:none;">Cliquez sur <b>Enregistrer</b> pour envoyer les invitations</div>
        <button type="button" class="btn ghost" data-panel-close="invite">Annuler</button>
        <button type="submit" form="invite-form" class="btn dark" id="invite-save-btn" disabled>Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

<x-slide-panel id="edit-user" title="Modifier le profil">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-user-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-user">
        <input type="hidden" name="_edit_url" id="edit-user-edit-url" value="{{ old('_edit_url') }}">

        @error('name')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('telephone')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('email')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="edit-user-nom">Nom</label>
            <input type="text" id="edit-user-nom" placeholder="Nom de famille" required>
        </div>

        <div class="field">
            <label for="edit-user-prenoms">Prénoms</label>
            <input type="text" id="edit-user-prenoms" placeholder="Prénom(s)" required>
        </div>

        <input type="hidden" id="edit-user-name" name="name" value="{{ old('name') }}">

        <div class="field">
            <label for="edit-user-telephone">Numéro de téléphone</label>
            <input type="tel" id="edit-user-telephone" name="telephone" value="{{ old('telephone') }}" required>
        </div>

        <div class="field">
            <label for="edit-user-email">Adresse e-mail</label>
            <input type="email" id="edit-user-email" name="email" value="{{ old('email') }}" required>
            <div class="hint">Seul un administrateur peut modifier l'adresse e-mail d'un utilisateur.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-user">Annuler</button>
        <button type="submit" form="edit-user-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>
@endsection
