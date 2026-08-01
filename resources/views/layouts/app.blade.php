<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- When a POST fails validation, the submitting form flashes its own
         "_panel" id (see the hidden input in each <x-slide-panel> form); this
         lets JS reopen that exact panel on reload so the @error messages
         inside it (otherwise off-screen, panels are translateX(100%) until
         .show) are actually visible to the user. --}}
    <meta name="reopen-panel" content="{{ old('_panel') }}">
    <title>@yield('title', 'Tableau de bord') — CSCMT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cscmt app-shell">
<div class="shell">
    <x-sidebar-nav />

    <div class="main">
        <div class="app-shell-inner">

            <div class="app-shell-topbar">
                <div class="brand">
                    <img src="{{ asset('logo-cscmt.jpg') }}" alt="Complexe Scolaire Catholique Madre Trinidad" class="logo-slot">
                    <div class="name">Complexe Scolaire Catholique<br><small>Madre Trinidad — Registre numérique</small></div>
                    <x-guide-menu />
                </div>
                <x-profile-menu :user="auth()->user()" />
            </div>

            <x-breadcrumbs :items="$breadcrumbs ?? []" />

            <main>
                @yield('content')
            </main>

        </div>
    </div>
</div>

<x-flash-toast />
</body>
</html>
