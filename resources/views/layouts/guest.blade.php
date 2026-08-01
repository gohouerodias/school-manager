<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Connexion') — CSCMT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cscmt auth-shell">

<svg class="deco tl" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M100 100C100 100 60 70 30 75C10 78 0 60 5 45" stroke="#3D8B75" stroke-width="6" stroke-linecap="round"/>
  <path d="M100 100C100 100 55 90 25 105C6 115 -8 100 -8 85" stroke="#3D8B75" stroke-width="6" stroke-linecap="round"/>
  <path d="M100 100C100 100 65 115 45 145C32 165 10 160 2 145" stroke="#3D8B75" stroke-width="6" stroke-linecap="round"/>
  <path d="M100 100C100 100 75 60 80 25C82 5 65 -5 50 2" stroke="#3D8B75" stroke-width="6" stroke-linecap="round"/>
  <rect x="96" y="95" width="10" height="110" rx="4" fill="#3D8B75"/>
</svg>
<svg class="deco br" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M100 100C100 100 60 70 30 75C10 78 0 60 5 45" stroke="#EFA03B" stroke-width="6" stroke-linecap="round"/>
  <path d="M100 100C100 100 55 90 25 105C6 115 -8 100 -8 85" stroke="#EFA03B" stroke-width="6" stroke-linecap="round"/>
  <path d="M100 100C100 100 65 115 45 145C32 165 10 160 2 145" stroke="#EFA03B" stroke-width="6" stroke-linecap="round"/>
  <path d="M100 100C100 100 75 60 80 25C82 5 65 -5 50 2" stroke="#EFA03B" stroke-width="6" stroke-linecap="round"/>
  <rect x="96" y="95" width="10" height="110" rx="4" fill="#EFA03B"/>
</svg>

<div class="auth-topbar">
    <div class="brand">
        <img src="{{ asset('logo-cscmt.jpg') }}" alt="Complexe Scolaire Catholique Madre Trinidad" class="logo-slot">
        <div class="name">Complexe Scolaire Catholique<br><small>Madre Trinidad</small></div>
    </div>
    <a class="help-link" href="mailto:admin@cscmadretrinidad.bj">Besoin d'aide ?</a>
</div>

<div class="auth-stage">
    @yield('content')
</div>

<div class="auth-footer">
    <div class="stripe"></div>
    © {{ now()->year }} Complexe Scolaire Catholique Madre Trinidad — Registre numérique
</div>

</body>
</html>
