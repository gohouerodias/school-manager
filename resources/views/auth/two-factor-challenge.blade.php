@extends('layouts.guest')

@section('title', 'Vérification en deux étapes')

@section('content')
<div class="auth-card">
    <div class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7l-9-5Z"/></svg>
    </div>
    <h1>Vérification en deux étapes</h1>
    <p class="sub">Entrez le code à 6 chiffres généré par votre application d'authentification</p>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('2fa.verify') }}" novalidate>
        @csrf

        <div class="field">
            <label for="code">Code de vérification</label>
            <div class="input-wrap">
                <input
                    type="text"
                    id="code"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="000000"
                    style="text-align:center;letter-spacing:.35em;font-weight:700;"
                    required
                    autofocus
                >
            </div>
            @error('code')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <button class="btn-submit" type="submit">
            Vérifier
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    <div class="divider"><div class="line"></div><span>BESOIN D'AIDE</span><div class="line"></div></div>

    <p class="no-account">
        Code introuvable ou expiré ? Contactez l'administration pour réinitialiser votre double authentification.
    </p>
</div>

<form method="POST" action="{{ route('logout') }}" class="auth-logout-link">
    @csrf
    <button type="submit">Se déconnecter</button>
</form>
@endsection
