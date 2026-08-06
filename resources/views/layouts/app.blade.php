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
    @include('partials.page-loader')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cscmt app-shell">
<div id="page-loader" aria-hidden="true">
    <div class="page-loader-ring"><span></span><span></span><span></span></div>
    <p class="page-loader-text">Chargement…</p>
</div>
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

{{-- Singleton confirmation dialog, reusable from any page: JS calls
     askConfirmation({ message, onConfirm, onCancel }) (see
     resources/js/confirm-modal.js) to repopulate + open it, instead of
     each feature building its own modal. --}}
<x-confirm-modal id="confirm-action" />

{{-- Singleton image viewer, reusable from any page: JS calls
     openImageLightbox(url) (see resources/js/image-lightbox.js). --}}
<x-image-lightbox id="image-lightbox" />

<x-flash-toast />
</body>
</html>
