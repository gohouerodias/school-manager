@props(['user'])

{{--
    Topbar "Mon profil" popover: lets any authenticated user edit their own
    name/telephone. Deliberately has NO e-mail field — only an
    administrator can change a user's e-mail, from Gestion des comptes
    (see UpdateAccountRequest / UserAccountController::update).
--}}
<div class="profile-wrap" data-profile-menu>
    <button type="button" class="user-chip" data-profile-menu-trigger>
        <x-avatar :name="$user->name" :profil="$user->profil" />
        <span>{{ $user->name }} · {{ $user->profil?->label() }}</span>
    </button>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn ghost logout-btn">Se déconnecter</button>
    </form>

    <div class="profile-menu" data-profile-menu-panel>
        <div class="profile-menu-head">Mon profil</div>

        <form method="POST" action="{{ route('profil.update') }}">
            @csrf
            @method('PATCH')

            <div class="field">
                <label for="profile-menu-name">Nom complet</label>
                <input type="text" id="profile-menu-name" name="name" value="{{ old('name', $user->name) }}">
            </div>

            <div class="field">
                <label for="profile-menu-telephone">Numéro de téléphone</label>
                <input type="tel" id="profile-menu-telephone" name="telephone" value="{{ old('telephone', $user->telephone) }}">
            </div>

            <button type="submit" class="btn primary">Enregistrer les modifications</button>
        </form>
    </div>
</div>
