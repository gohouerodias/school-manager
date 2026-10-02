@extends('layouts.guest')

@section('title', 'Mot de passe oublié')

@section('content')
<div class="auth-card">
    <div class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
    </div>
    <h1>Mot de passe oublié</h1>

    @if (session('status'))
        {{-- Une fois le lien envoyé, on retire le formulaire plutôt que de le
             laisser affiché à côté du message de succès : certains
             utilisateurs retapaient leur e-mail et renvoyaient la demande en
             pensant que la première n'avait pas marché (voir le message de
             l'école). --}}
        <p class="sub">Vérifiez votre boîte de réception (et vos spams) pour la suite.</p>

        <div class="status-ok">{{ session('status') }}</div>

        <div class="divider"><div class="line"></div><span>INFORMATION</span><div class="line"></div></div>

        <p class="no-account">
            <a href="{{ route('login') }}">← Retour à la connexion</a>
        </p>
        <p class="no-account" style="margin-top:8px;">
            <a href="{{ route('password.request') }}">Vous n'avez rien reçu ? Réessayer avec une autre adresse</a>
        </p>
    @else
        <p class="sub">Indiquez votre adresse e-mail : si un compte lui est associé, vous recevrez un lien pour réinitialiser votre mot de passe</p>

        @if ($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <div class="input-wrap">
                    <svg class="left-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
                    <input class="with-icon" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="prenom.nom@cscmadretrinidad.bj" required autofocus>
                </div>
                @error('email')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <button class="btn-submit" type="submit">
                Envoyer le lien de réinitialisation
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <div class="divider"><div class="line"></div><span>INFORMATION</span><div class="line"></div></div>

        <p class="no-account">
            <a href="{{ route('login') }}">← Retour à la connexion</a>
        </p>
    @endif
</div>
@endsection
