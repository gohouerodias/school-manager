@extends('layouts.app')

@section('title', 'Bulletins')

@section('content')
<x-page-header
    title="Bulletins"
    subtitle="Suivi des signatures et planification de la génération des bulletins mensuels, classe par classe."
/>

<form method="GET" action="{{ route('eleves.bulletins.index') }}" class="bulletins-toolbar">
    <select class="filter-select" name="classe_id" onchange="this.form.submit()">
        @forelse ($classes as $c)
            <option value="{{ $c->id }}" @selected($classe?->id === $c->id)>{{ $c->niveau->libelle }} — {{ $c->nom }}</option>
        @empty
            <option value="">Aucune classe</option>
        @endforelse
    </select>

    @if ($classe)
        <select class="filter-select" name="examen_id" onchange="this.form.submit()">
            @forelse ($examens as $e)
                <option value="{{ $e->id }}" @selected($examenActif?->id === $e->id)>Évaluation mensuelle — {{ $e->date_examen->translatedFormat('F Y') }}</option>
            @empty
                <option value="">Aucun examen pour cette classe</option>
            @endforelse
        </select>
    @endif
</form>

@if (! $classe)
    <div class="alert-error">Aucune classe créée pour l'année académique active — créez d'abord une classe et des inscriptions.</div>
@elseif (! $examenActif)
    <div class="alert-error">Aucun examen mensuel créé pour cette classe — créez-en un depuis Académique → Examens.</div>
@else
    @if ($demande)
        <div
            id="generation-status"
            class="demande-info {{ $demande->aEchoue() || $demande->estCoinceeSansWorker() ? 'error' : '' }}"
            data-statut-url="{{ route('eleves.bulletins.statut', $demande) }}"
            data-statut-initial="{{ $demande->statut?->value }}"
            data-bloquant-initial="{{ $demande->bloqueUneNouvelleGeneration() ? '1' : '0' }}"
        >
            @if ($demande->estGeneree())
                ✓ Bulletins générés le {{ $demande->genere_at->format('d/m/Y à H:i') }} ({{ $demande->nb_bulletins_generes }} bulletin(s)).
                @if ($demande->chemin_pdf)
                    <a href="{{ route('eleves.bulletins.telecharger', $demande) }}">Télécharger le PDF groupé</a>
                @endif
            @elseif ($demande->aEchoue())
                ✕ La génération a échoué : {{ $demande->erreur }}
            @elseif ($demande->estCoinceeSansWorker())
                ⚠ La génération semble bloquée depuis plus de 2 minutes — aucun worker de file d'attente ne semble actif (voir <code>php artisan queue:work</code> ou <code>composer run dev</code>). Vous pouvez relancer la génération ci-dessous.
            @else
                <div class="generation-progress-row">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="generation-progress-text">
                        {{ $demande->estEnCours() ? 'Génération en cours' : 'Génération en attente de démarrage' }}
                        — {{ $demande->traites }} / {{ $demande->total }} bulletin(s) traité(s)
                    </span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:{{ $demande->pourcentage() }}%;"></div></div>
            @endif
        </div>
    @endif

    <div class="progress-summary">
        <div class="txt">
            <b>{{ $payload['signedCount'] }} / {{ $payload['total'] }} bulletins signés par le titulaire</b>
            <span>Suivi informatif des signatures — la génération des bulletins ne dépend que du calcul des moyennes, pas de la signature.</span>
        </div>
        <div class="progress-track"><div class="progress-fill {{ $payload['pct'] < 100 ? 'warn' : '' }}" style="width:{{ $payload['pct'] }}%;"></div></div>
    </div>

    <x-data-table id="bulletins-table">
        <x-slot:head>
            <th>Apprenant</th>
            <th>Statut de signature</th>
            <th>Moyenne du mois</th>
            <th></th>
        </x-slot:head>

        @forelse ($payload['lignes'] as $ligne)
            @php $inscription = $ligne['inscription']; @endphp
            <tr>
                <td><b>{{ $inscription->eleve->nomComplet() }}</b> <span class="hint">— {{ $inscription->eleve->matricule }}</span></td>
                <td>
                    <span class="status-sign-badge {{ $ligne['signed'] ? 'ok' : 'pending' }}">
                        {{ $ligne['signed'] ? '✓ Signé' : '⏳ En attente' }}
                    </span>
                </td>
                <td>{{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2).' / 20' : '—' }}</td>
                <td>
                    <a href="{{ route('eleves.bulletins.apercu', ['classe' => $classe, 'examen' => $examenActif, 'inscription' => $inscription]) }}" target="_blank" class="btn primary sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Aperçu
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="table-empty-state">Aucun apprenant inscrit dans cette classe.</td>
            </tr>
        @endforelse
    </x-data-table>

    @php $dejaGeneree = $demande?->estGeneree() ?? false; @endphp
    <form method="POST" action="{{ route('eleves.bulletins.demander') }}" class="generate-bar"
        @if ($dejaGeneree)
            data-confirm-submit
            data-confirm-title="Régénérer les bulletins"
            data-confirm-message="Cette action va remplacer l'archive déjà générée pour {{ $classe->nom }} par une nouvelle, à partir des notes actuelles. Continuer ?"
            data-confirm-label="Régénérer"
        @endif
    >
        @csrf
        <input type="hidden" name="classe_id" value="{{ $classe->id }}">
        <input type="hidden" name="examen_id" value="{{ $examenActif->id }}">

        @if ($demande && $demande->bloqueUneNouvelleGeneration())
            <p>Génération en cours pour <b>{{ $classe->nom }}</b> — suivez la progression ci-dessus.</p>
        @elseif ($dejaGeneree && $payload['moyennesEnAttenteCount'] === 0 && $payload['total'] > 0)
            <p>Les bulletins de <b>{{ $classe->nom }}</b> ont déjà été générés — vous pouvez les régénérer si des notes ont changé depuis.</p>
        @elseif ($payload['moyennesEnAttenteCount'] === 0 && $payload['total'] > 0)
            <p>Toutes les moyennes de <b>{{ $classe->nom }}</b> sont prêtes — vous pouvez lancer la génération pour l'ensemble de la classe.</p>
        @else
            <p>En attente : <b>{{ $payload['moyennesEnAttenteCount'] }} moyenne(s)</b> pas encore calculée(s) (notes incomplètes). La génération est bloquée tant qu'il en reste.</p>
        @endif

        <button id="generate-bulletins-btn" type="submit" class="btn primary" @disabled($payload['total'] === 0 || $payload['moyennesEnAttenteCount'] > 0 || $demande?->bloqueUneNouvelleGeneration())>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/></svg>
            {{ $dejaGeneree ? 'Régénérer les bulletins de la classe' : 'Générer les bulletins de la classe' }}
        </button>
    </form>
@endif
@endsection
