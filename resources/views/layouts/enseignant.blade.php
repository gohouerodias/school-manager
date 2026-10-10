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

{{-- Même structure que l'espace administrateur (layouts/app.blade.php) :
     logo dans le menu et la barre du haut ; sur téléphone, le menu devient
     un tiroir ouvert par le bouton ☰ (voir resources/js/sidebar.js). --}}
<div class="shell">
    <aside class="sidebar" data-sidebar>
        <div class="sidebar-header">
            {{-- Menu latéral : logo ESSEd Internacional (séance du 07/10/2026) ;
                 celui de Madre Trinidad reste dans la barre du haut. --}}
            <div class="sidebar-brand">
                <img src="{{ asset('logo-essed.png') }}" alt="ESSEd Internacional" class="sidebar-logo">
                <span class="sidebar-wordmark">Registre CSCMT</span>
            </div>
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
    <div class="sidebar-backdrop" data-sidebar-backdrop></div>

    <div class="main">
        <div class="app">

            <div class="topbar">
                <div class="brand">
                    <button type="button" class="mobile-nav-toggle" data-sidebar-open aria-label="Ouvrir le menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <img src="{{ asset('logo-cscmt.jpg') }}" alt="Complexe Scolaire Catholique Madre Trinidad" class="logo-slot">
                    <div class="name">Complexe Scolaire Catholique<small>Madre Trinidad — Registre numérique</small></div>
                </div>
                <div class="profile-wrap">
                    <x-notifications-menu :user="auth()->user()" />
                    <div class="user-chip" title="{{ auth()->user()->name }} · Enseignant">
                        <div class="avatar">{{ auth()->user()->initials() }}</div>
                        <span class="user-chip-name">{{ auth()->user()->name }} · Enseignant</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn ghost logout-btn" title="Se déconnecter" aria-label="Se déconnecter">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                            <span class="logout-label">Se déconnecter</span>
                        </button>
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
