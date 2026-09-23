@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
<div class="content-head">
    <div>
        <h1>Bonjour, {{ auth()->user()->name }}</h1>
        <p>Vous êtes connecté(e) en tant que {{ auth()->user()->profil?->label() }}.</p>
    </div>
</div>

@if (auth()->user()->profil === \App\Enums\ProfilUtilisateur::Direction)
    <div class="rapport-summary-row">
        <div class="data-card rapport-summary-card">
            <span class="rapport-summary-label">Année académique active</span>
            <span class="rapport-summary-value" style="font-size:16px;">{{ $anneeActive?->libelle ?? 'Aucune' }}</span>
        </div>
        <div class="data-card rapport-summary-card">
            <span class="rapport-summary-label">Apprenants actifs</span>
            <span class="rapport-summary-value">{{ $totalApprenants }}</span>
        </div>
        <div class="data-card rapport-summary-card">
            <span class="rapport-summary-label">Classes (année active)</span>
            <span class="rapport-summary-value">{{ $totalClasses }}</span>
        </div>
    </div>

    <div class="data-card" style="padding:24px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('eleves.index') }}" class="btn ghost">Dossier élève et documents</a>
        <a href="{{ route('rapports.index') }}" class="btn primary">Rapports statistiques</a>
    </div>
@else
    <div class="data-card" style="padding:24px;">
        <p style="margin:0;color:var(--ink-muted);font-size:13.5px;">
            Le tableau de bord détaillé (dossiers élèves, classes, bulletins, statistiques…) sera construit dans les prochaines étapes.
        </p>
    </div>
@endif
@endsection
