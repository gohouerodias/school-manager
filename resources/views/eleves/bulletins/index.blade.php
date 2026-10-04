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
@else
    @if (! $examenActif)
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
                    <a href="{{ route('eleves.bulletins.telecharger', $demande) }}" data-no-loader>Télécharger le PDF groupé</a>
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
            @unless ($classe->estMaternelle())
                <th>Moyenne du mois</th>
            @endunless
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
                @unless ($classe->estMaternelle())
                    <td>{{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2).' / 20' : '—' }}</td>
                @endunless
                <td>
                    <a href="{{ route('eleves.bulletins.apercu', ['classe' => $classe, 'examen' => $examenActif, 'inscription' => $inscription]) }}" target="_blank" class="btn primary sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Aperçu
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $classe->estMaternelle() ? 3 : 4 }}" class="table-empty-state">Aucun apprenant inscrit dans cette classe.</td>
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
        @elseif ($classe->estMaternelle())
            <p>En attente : <b>{{ $payload['moyennesEnAttenteCount'] }} grille(s) d'évaluation</b> pas encore complète(s). La génération est bloquée tant qu'il en reste.</p>
        @else
            <p>En attente : <b>{{ $payload['moyennesEnAttenteCount'] }} moyenne(s)</b> pas encore calculée(s) (notes incomplètes). La génération est bloquée tant qu'il en reste.</p>
        @endif

        <button id="generate-bulletins-btn" type="submit" class="btn primary" @disabled($payload['total'] === 0 || $payload['moyennesEnAttenteCount'] > 0 || $demande?->bloqueUneNouvelleGeneration())>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/></svg>
            {{ $dejaGeneree ? 'Régénérer les bulletins de la classe' : 'Générer les bulletins de la classe' }}
        </button>
    </form>
    @endif

    {{--
        Bulletin annuel : indépendant de l'examen mensuel sélectionné plus
        haut (il porte sur toute l'année académique de la classe, voir
        BulletinGenerationService::payloadAnnuelPourClasse()) — reste affiché
        même si aucun examen mensuel n'est encore créé pour la classe, tant
        qu'une classe est sélectionnée.
    --}}
    <div style="margin-top:36px;padding-top:28px;border-top:1px solid var(--line);">
        <h2 style="font-family:'Fraunces',serif;color:var(--green-deep);font-size:19px;margin:0 0 4px;">Bulletin annuel</h2>
        <p class="hint" style="margin-bottom:18px;">
            {{ $classe->estMaternelle()
                ? 'Récapitule, pour chaque apprenant, le nombre de TS/S/PS obtenus sur l’ensemble des évaluations de l’année.'
                : "Calcule la moyenne annuelle de chaque apprenant (moyenne de ses bulletins mensuels déjà signés) et son rang dans la classe." }}
        </p>

        @unless ($payloadAnnuel['seuilAtteint'])
            <div class="alert-error">
                Le bulletin annuel sera proposé une fois <b>{{ $payloadAnnuel['nombreEvaluationsRequis'] }} évaluation(s)</b> créée(s) pour cette classe
                ({{ $payloadAnnuel['nombreEvaluationsCreees'] }} / {{ $payloadAnnuel['nombreEvaluationsRequis'] }} pour l'instant — voir Académique → Examens,
                et Académique → Années académiques pour changer ce seuil).
            </div>
        @else
            @if ($demandeAnnuel)
                <div
                    id="generation-status-annuel"
                    class="demande-info {{ $demandeAnnuel->aEchoue() || $demandeAnnuel->estCoinceeSansWorker() ? 'error' : '' }}"
                    data-statut-url="{{ route('eleves.bulletins.annuel.statut', $demandeAnnuel) }}"
                    data-statut-initial="{{ $demandeAnnuel->statut?->value }}"
                    data-bloquant-initial="{{ $demandeAnnuel->bloqueUneNouvelleGeneration() ? '1' : '0' }}"
                >
                    @if ($demandeAnnuel->estGeneree())
                        ✓ Bulletins générés le {{ $demandeAnnuel->genere_at->format('d/m/Y à H:i') }} ({{ $demandeAnnuel->nb_bulletins_generes }} bulletin(s)).
                        @if ($demandeAnnuel->chemin_pdf)
                            <a href="{{ route('eleves.bulletins.annuel.telecharger', $demandeAnnuel) }}" data-no-loader>Télécharger le PDF groupé</a>
                        @endif
                    @elseif ($demandeAnnuel->aEchoue())
                        ✕ La génération a échoué : {{ $demandeAnnuel->erreur }}
                    @elseif ($demandeAnnuel->estCoinceeSansWorker())
                        ⚠ La génération semble bloquée depuis plus de 2 minutes — aucun worker de file d'attente ne semble actif (voir <code>php artisan queue:work</code> ou <code>composer run dev</code>). Vous pouvez relancer la génération ci-dessous.
                    @else
                        <div class="generation-progress-row">
                            <span class="spinner" aria-hidden="true"></span>
                            <span class="generation-progress-text">
                                {{ $demandeAnnuel->estEnCours() ? 'Génération en cours' : 'Génération en attente de démarrage' }}
                                — {{ $demandeAnnuel->traites }} / {{ $demandeAnnuel->total }} bulletin(s) traité(s)
                            </span>
                        </div>
                        <div class="progress-track"><div class="progress-fill" style="width:{{ $demandeAnnuel->pourcentage() }}%;"></div></div>
                    @endif
                </div>
            @endif

            @unless ($classe->estMaternelle())
                <form method="POST" action="{{ route('eleves.bulletins.annuel.recalculer') }}" style="margin-bottom:14px;"
                      data-confirm-submit data-confirm-label="Calculer"
                      data-confirm-title="Calculer les moyennes annuelles"
                      data-confirm-message="Recalcule et enregistre la moyenne annuelle générale et par matière de chaque apprenant de {{ $classe->nom }}, à partir des bulletins mensuels déjà validés. Continuer ?">
                    @csrf
                    <input type="hidden" name="classe_id" value="{{ $classe->id }}">
                    <button type="submit" class="btn ghost">🔄 Calculer les moyennes annuelles</button>
                </form>
            @endunless

            @php $peutDecider = auth()->user()->profil === \App\Enums\ProfilUtilisateur::Administrateur; @endphp
            <x-data-table id="bulletins-annuel-table">
                <x-slot:head>
                    <th>Apprenant</th>
                    @unless ($classe->estMaternelle())
                        <th>Moyenne annuelle</th>
                        <th>Rang</th>
                        <th>Décision finale</th>
                    @endunless
                    <th></th>
                </x-slot:head>

                @forelse ($payloadAnnuel['lignes'] as $ligne)
                    @php $inscriptionAnnuelle = $ligne['inscription']; @endphp
                    <tr>
                        <td><b>{{ $inscriptionAnnuelle->eleve->nomComplet() }}</b> <span class="hint">— {{ $inscriptionAnnuelle->eleve->matricule }}</span></td>
                        @unless ($classe->estMaternelle())
                            <td>{{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2).' / 20' : '—' }}</td>
                            <td>{{ $ligne['rang'] ? $ligne['rang'].' / '.$ligne['totalClasse'] : '—' }}</td>
                            <td>
                                @if ($inscriptionAnnuelle->decision)
                                    <span class="chip {{ $inscriptionAnnuelle->decision->value === 'exclu' ? 'chip-danger' : '' }}">{{ $inscriptionAnnuelle->decision->label() }}</span>
                                @else
                                    <span class="chip">En attente</span>
                                @endif
                                @if ($peutDecider)
                                    <button
                                        type="button"
                                        class="row-edit"
                                        title="Décider"
                                        data-panel-open="edit-decision"
                                        data-edit-decision-trigger
                                        data-edit-url="{{ route('academique.inscriptions.decision.update', $inscriptionAnnuelle) }}"
                                        data-edit-eleve="{{ $inscriptionAnnuelle->eleve->nomComplet() }}"
                                        data-edit-moyenne="{{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2) : '—' }}"
                                        data-edit-proposition="{{ $ligne['proposition']?->value }}"
                                        data-edit-decision="{{ $inscriptionAnnuelle->decision?->value }}"
                                        data-edit-motif="{{ $inscriptionAnnuelle->motif_decision }}"
                                    >✎</button>
                                @endif
                            </td>
                        @endunless
                        <td>
                            <a href="{{ route('eleves.bulletins.annuel.apercu', ['classe' => $classe, 'inscription' => $inscriptionAnnuelle]) }}" target="_blank" class="btn primary sm">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                Aperçu
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $classe->estMaternelle() ? 2 : 5 }}" class="table-empty-state">Aucun apprenant inscrit dans cette classe.</td>
                    </tr>
                @endforelse
            </x-data-table>

            @if ($peutDecider && ! $classe->estMaternelle())
                {{-- Même panneau que academique/annees/decisions.blade.php,
                     réutilisé tel quel ici (mêmes ids, voir resources/js/
                     decision-passage.js's initDecisionPassage(), déjà
                     appelée globalement depuis app.js) pour permettre
                     d'enregistrer la décision finale sans quitter l'écran
                     Bulletins. --}}
                <x-slide-panel id="edit-decision" title="Décision de passage">
                    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-decision-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_panel" value="edit-decision">
                        <input type="hidden" name="_edit_url" id="edit-decision-edit-url" value="{{ old('_edit_url') }}">

                        @error('decision')
                            <div class="alert-error">{{ $message }}</div>
                        @enderror
                        @error('motif')
                            <div class="alert-error">{{ $message }}</div>
                        @enderror

                        <div class="field">
                            <label>Apprenant</label>
                            <div class="field-static" id="edit-decision-eleve">—</div>
                        </div>

                        <div class="field">
                            <label>Moyenne annuelle / proposition automatique</label>
                            <div class="field-static" id="edit-decision-proposition">—</div>
                        </div>

                        <div class="field">
                            <label for="edit-decision-select">Décision</label>
                            <select class="role-select" id="edit-decision-select" name="decision" required>
                                @foreach (\App\Enums\DecisionAnnuelle::cases() as $decision)
                                    <option value="{{ $decision->value }}" @selected(old('decision') === $decision->value)>{{ $decision->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="edit-decision-motif">Motif (obligatoire si vous modifiez la proposition)</label>
                            <textarea id="edit-decision-motif" name="motif" placeholder="Ex : redoublement demandé par la famille malgré une moyenne suffisante.">{{ old('motif') }}</textarea>
                        </div>
                    </form>

                    <x-slot:footer>
                        <button type="button" class="btn ghost" data-panel-close="edit-decision">Annuler</button>
                        <button type="submit" form="edit-decision-form" class="btn dark">Enregistrer</button>
                    </x-slot:footer>
                </x-slide-panel>
            @endif

            @php $dejaGenereeAnnuel = $demandeAnnuel?->estGeneree() ?? false; @endphp
            <form method="POST" action="{{ route('eleves.bulletins.annuel.demander') }}" class="generate-bar"
                @if ($dejaGenereeAnnuel)
                    data-confirm-submit
                    data-confirm-title="Régénérer les bulletins annuels"
                    data-confirm-message="Cette action va remplacer l'archive déjà générée pour {{ $classe->nom }} par une nouvelle, à partir des données actuelles. Continuer ?"
                    data-confirm-label="Régénérer"
                @endif
            >
                @csrf
                <input type="hidden" name="classe_id" value="{{ $classe->id }}">

                @if ($demandeAnnuel && $demandeAnnuel->bloqueUneNouvelleGeneration())
                    <p>Génération en cours pour <b>{{ $classe->nom }}</b> — suivez la progression ci-dessus.</p>
                @elseif ($dejaGenereeAnnuel)
                    <p>Le bulletin annuel de <b>{{ $classe->nom }}</b> a déjà été généré — vous pouvez le régénérer si des données ont changé depuis.</p>
                @elseif ($payloadAnnuel['total'] === 0)
                    <p>Aucun apprenant inscrit dans <b>{{ $classe->nom }}</b>.</p>
                @elseif (! $classe->estMaternelle() && $payloadAnnuel['moyennesEnAttenteCount'] > 0)
                    <p>En attente : <b>{{ $payloadAnnuel['moyennesEnAttenteCount'] }} apprenant(s)</b> sans aucun bulletin mensuel signé pour l'instant. La génération est bloquée tant qu'il en reste — voir l'écran Bulletins mensuels ci-dessus.</p>
                @else
                    <p>Prêt à générer le bulletin annuel de <b>{{ $classe->nom }}</b>.</p>
                @endif

                <button id="generate-bulletins-annuel-btn" type="submit" class="btn primary" @disabled($payloadAnnuel['total'] === 0 || (! $classe->estMaternelle() && $payloadAnnuel['moyennesEnAttenteCount'] > 0) || $demandeAnnuel?->bloqueUneNouvelleGeneration())>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/></svg>
                    {{ $dejaGenereeAnnuel ? 'Régénérer les bulletins annuels de la classe' : 'Générer les bulletins annuels de la classe' }}
                </button>
            </form>
        @endunless
    </div>
@endif
@endsection
