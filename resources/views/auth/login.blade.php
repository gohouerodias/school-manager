@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
<div class="auth-card">
    <div class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h1>Connexion</h1>
    <p class="sub">Accédez à votre espace numérique de gestion des dossiers élèves</p>

    @if (session('status'))
        <div class="status-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
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

        <div class="field">
            <label for="password">Mot de passe</label>
            <div class="input-wrap">
                <svg class="left-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input class="with-icon" style="padding-right:42px;" id="password" name="password" type="password" placeholder="••••••••" required>
                <button class="toggle-pw" type="button" data-password-toggle="password" aria-label="Afficher / masquer le mot de passe">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.6 21.6 0 0 1 5.06-5.94M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a21.7 21.7 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                </button>
            </div>
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="row-between">
            <label class="remember">
                <input type="checkbox" name="remember"> Se souvenir de moi
            </label>
            <a class="forgot" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>

        <button class="btn-submit" type="submit">
            Se connecter
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>

        <div class="security-hint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7l-9-5Z"/></svg>
            <span>Un code de vérification vous sera demandé après cette étape si la double authentification est active sur votre compte. Lors de votre toute première connexion, vous devrez également définir un nouveau mot de passe.</span>
        </div>
    </form>

    <div class="divider"><div class="line"></div><span>INFORMATION</span><div class="line"></div></div>

    <p class="no-account">
        <b>Vous n'avez pas encore de compte ?</b><br>
        Les accès sont créés par l'administration de l'établissement.<br>
        Contactez le secrétariat ou écrivez à <a href="mailto:admin@cscmadretrinidad.bj">admin@cscmadretrinidad.bj</a>
    </p>
</div>
@endsection
