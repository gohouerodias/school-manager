@extends('layouts.enseignant')

@section('title', 'Mes classes')

@section('content')
<div class="page-head">
    <div>
        <h1>Mes classes</h1>
        @if ($anneeActive)
            <p>{{ $classes->count() }} classe(s) vous {{ $classes->count() > 1 ? 'sont' : 'est' }} attribuée(s) pour l'année académique {{ $anneeActive->libelle }}</p>
        @else
            <p>Aucune année académique active pour l'instant.</p>
        @endif
    </div>
</div>

<div class="classes-grid">
    @forelse ($classes as $ligne)
        @php
            $classe = $ligne['classe'];
            $pct = $ligne['totalCellules'] > 0 ? (int) round(($ligne['remplies'] / $ligne['totalCellules']) * 100) : 0;
        @endphp
        <a href="{{ route('enseignant.classes.show', $classe) }}" class="class-card">
            <div class="badge-year">Année {{ $anneeActive->libelle }}</div>
            <h3>{{ $classe->nom }}</h3>
            <div class="subject-chips">
                @foreach ($ligne['matieres'] as $matiere)
                    <span class="subject-chip">{{ $matiere->nom }}</span>
                @endforeach
                @if ($ligne['estTitulaire'])
                    <span class="titulaire-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        Titulaire
                    </span>
                @endif
            </div>
            <div class="class-stat-row"><span>Apprenants</span><b>{{ $ligne['nbEleves'] }}</b></div>
            <div class="class-stat-row">
                <span>Notes saisies{{ $ligne['examen'] ? ' — '.$ligne['examen']->date_examen->translatedFormat('F Y') : '' }}</span>
                <b>{{ $ligne['remplies'] }}/{{ $ligne['totalCellules'] }}</b>
            </div>
            <div class="progress-track"><div class="progress-fill @if($pct < 60) warn @endif" style="width:{{ $pct }}%;"></div></div>
            @if (! $ligne['estTitulaire'] && $ligne['titulaireNom'])
                <div class="hint" style="margin-bottom:10px;">Titulaire de la classe : <b>{{ $ligne['titulaireNom'] }}</b></div>
            @endif
            <span class="btn primary">Ouvrir la classe</span>
        </a>
    @empty
        <p class="hint">Aucune classe ne vous est encore attribuée. Contactez l'administration si cela vous semble anormal.</p>
    @endforelse
</div>
@endsection
