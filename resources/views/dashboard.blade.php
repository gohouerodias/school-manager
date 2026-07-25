@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
<div class="content-head">
    <div>
        <h1>Bonjour, {{ auth()->user()->name }}</h1>
        <p>Vous êtes connecté(e) en tant que {{ auth()->user()->profil?->label() }}.</p>
    </div>
</div>

<div class="data-card" style="padding:24px;">
    <p style="margin:0;color:var(--ink-muted);font-size:13.5px;">
        Le tableau de bord détaillé (dossiers élèves, classes, bulletins, statistiques…) sera construit dans les prochaines étapes.
    </p>
</div>
@endsection
