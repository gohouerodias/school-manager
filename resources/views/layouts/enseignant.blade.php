<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Espace enseignant') — CSCMT</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('logo-cscmt.jpg') }}">
    @vite(['resources/css/enseignant.css', 'resources/js/enseignant.js'])
</head>
<body>

<div class="shell">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">CSCMT</div>
            <span class="sidebar-wordmark">Registre CSCMT</span>
        </div>
        <div class="sidebar-inner">
            <div class="sidebar-label">Navigation</div>
            <a href="{{ route('enseignant.classes.index') }}" class="nav-item @if(request()->routeIs('enseignant.classes.*')) active @endif">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
                Mes classes
            </a>
        </div>
        <div class="teacher-card">
            <div class="name">{{ auth()->user()->name }}</div>
            <div class="role">Enseignant</div>
        </div>
    </aside>

    <div class="main">
        <div class="app">

            <div class="topbar">
                <span></span>
                <div class="profile-wrap">
                    <div class="user-chip">
                        <div class="avatar">{{ auth()->user()->initials() }}</div>
                        {{ auth()->user()->name }} · Enseignant
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn ghost">Se déconnecter</button>
                    </form>
                </div>
            </div>

            @yield('content')

        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

</body>
</html>
