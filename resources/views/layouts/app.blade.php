<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tableau de bord') — CSCMT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cscmt app-shell">
<div class="app-shell-inner">

    <div class="app-shell-topbar">
        <div class="brand">
            <div class="logo-slot">LOGO<br>CSCMT</div>
            <div class="name">Complexe Scolaire Catholique<br><small>Madre Trinidad — Registre numérique</small></div>
        </div>
        <div class="user-chip">
            <div class="avatar">{{ auth()->user()?->initials() }}</div>
            <span>{{ auth()->user()?->name }} · {{ auth()->user()?->profil?->label() }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn ghost logout-btn">Se déconnecter</button>
            </form>
        </div>
    </div>

    <main>
        @yield('content')
    </main>

</div>
</body>
</html>
