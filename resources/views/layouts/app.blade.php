<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                    <div class="logo-slot">LOGO<br>CSCMT</div>
                    <div class="name">Complexe Scolaire Catholique<br><small>Madre Trinidad — Registre numérique</small></div>
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
