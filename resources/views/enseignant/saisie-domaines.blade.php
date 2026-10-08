@extends('layouts.enseignant')

@section('title', $classe->nom)

@section('content')
<a href="{{ route('enseignant.classes.index') }}" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="m15 18-6-6 6-6"/></svg>
    Retour à mes classes
</a>

<div class="grade-topbar">
    <div class="grade-title-row">
        <h1>{{ $classe->nom }}</h1>
        @if ($isTitulaire)
            <span class="titulaire-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Vous êtes titulaire
            </span>
        @elseif ($titulaire)
            <span class="hint">Titulaire de la classe : <b>{{ $titulaire->name }}</b></span>
        @endif

        @if ($examens->isNotEmpty())
            <form method="GET" action="{{ route('enseignant.classes.show', $classe) }}">
                <select class="period-select" name="examen_id" onchange="this.form.submit()">
                    @foreach ($examens as $examen)
                        <option value="{{ $examen->id }}" @selected($examenActif && $examenActif->id === $examen->id)>
                            Évaluation mensuelle — {{ $examen->date_examen->translatedFormat('F Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>
</div>

@if ($examens->isEmpty())
    <div class="hint">Aucune évaluation mensuelle disponible pour cette classe pour l'instant — elles sont créées depuis Académique &gt; Examens.</div>
@elseif ($domaines->isEmpty())
    <div class="hint">Aucun domaine d'évaluation au programme de cette classe — configurez-le depuis Académique &gt; Niveaux &amp; matières et la fiche de l'année académique.</div>
@else
    <div class="grade-toolbar">
        <div class="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="studentSearch" placeholder="Rechercher un apprenant...">
        </div>
        <div class="legend">
            <span><span class="qual-pill-legend" data-valeur="ts">TS</span> = Très satisfaisant</span>
            <span><span class="qual-pill-legend" data-valeur="s">S</span> = Satisfaisant</span>
            <span><span class="qual-pill-legend" data-valeur="ps">PS</span> = Peu satisfaisant</span>
        </div>
    </div>

    @if ($lectureSeuleTitulaire ?? false)
        <div class="titulaire-lock" style="margin-bottom:12px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Consultation uniquement : dans cette classe, seul le titulaire{{ $titulaire ? ' ('.$titulaire->name.')' : '' }} peut saisir les notes.</span>
        </div>
    @elseif ($saisieFermee)
        <div class="titulaire-lock" style="margin-bottom:12px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Délai de saisie dépassé ({{ $examenActif->dateLimiteSaisieLibelle() }}) — cette période est en lecture seule.</span>
        </div>
    @endif

    <div class="sheet-wrap @if($saisieFermee) sheet-locked @endif">
        <table class="sheet" id="sheetTable">
            <thead>
                <tr>
                    <th class="col-student">Apprenant</th>
                    @foreach ($domaines as $domaine)
                        <th>{{ $domaine->nom }}</th>
                    @endforeach
                    <th class="col-comment-h">Appréciation</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php
                        $aUneObservation = collect($student['observations'])->filter()->isNotEmpty()
                            || ! empty($student['bulletin']['appreciation'] ?? null)
                            || ! empty($student['bulletin']['resultat'] ?? null);
                    @endphp
                    <tr data-student-id="{{ $student['eleveId'] }}" data-search="{{ \Illuminate\Support\Str::lower($student['nom'].' '.$student['prenom']) }}">
                        <th class="row-student">
                            <div class="student-cell">
                                <div class="av">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student['prenom'], 0, 1).\Illuminate\Support\Str::substr($student['nom'], 0, 1)) }}</div>
                                <div class="txt"><b>{{ \Illuminate\Support\Str::upper($student['nom']) }} {{ $student['prenom'] }}</b><span>{{ $student['matricule'] }}</span></div>
                            </div>
                        </th>
                        @foreach ($domaines as $domaine)
                            @php $val = $student['valeurs'][$domaine->id] ?? null; @endphp
                            <td class="domaine-cell" data-domaine-id="{{ $domaine->id }}">
                                <div class="qual-pills" @if($saisieFermee) data-locked="1" @endif>
                                    @foreach (['ts' => 'Très satisfaisant', 's' => 'Satisfaisant', 'ps' => 'Peu satisfaisant'] as $code => $libelle)
                                        <button type="button" class="qual-pill @if($val === $code) selected @endif" data-valeur="{{ $code }}" title="{{ $libelle }}" @disabled($saisieFermee)>{{ \Illuminate\Support\Str::upper($code) }}</button>
                                    @endforeach
                                </div>
                            </td>
                        @endforeach
                        <td class="col-comment">
                            <button type="button" class="comment-btn @if($aUneObservation) filled @endif" data-student-id="{{ $student['eleveId'] }}" title="Observations">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $domaines->count() + 2 }}" class="hint">Aucun apprenant inscrit dans cette classe.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="hint" style="margin-top:12px;">
        @if ($lectureSeuleTitulaire ?? false)
            Consultation uniquement — seul le titulaire de la classe saisit les évaluations.
        @elseif ($saisieFermee)
            Cette période n'accepte plus de nouvelles évaluations.
        @else
            Cliquez sur TS / S / PS pour évaluer un domaine (TS = très satisfaisant, S = satisfaisant, PS = peu satisfaisant), puis sur « Enregistrer les modifications » pour sauvegarder. L'observation par domaine est facultative — cliquez sur l'icône « Appréciation » pour l'ajouter.
            @if ($examenActif)
                Délai de saisie : {{ $examenActif->dateLimiteSaisieLibelle() }}.
            @endif
        @endif
    </p>
@endif

<div class="save-bar" id="saveBar">
    <span id="saveBarCount"></span>
    <button type="button" class="btn ghost" id="saveBarCancel">Annuler</button>
    <button type="button" class="btn dark" id="saveBarBtn">Enregistrer les modifications</button>
</div>

@include('enseignant.partials.observations-annuelles')

<div class="overlay" id="overlay"></div>

<div class="panel" id="commentPanel">
    <div class="panel-head">
        <div>
            <h2 id="commentStudentName">Appréciation</h2>
            <p id="commentPeriodLabel"></p>
        </div>
        <button type="button" class="panel-close" id="commentPanelClose">✕</button>
    </div>
    <div class="panel-body">
        <div class="comment-section-label">Observation par domaine (facultatif)</div>
        <div id="subjectCommentsWrap"></div>

        <div class="comment-section-label" style="margin-top:8px;">Bulletin mensuel (appréciation générale)</div>
        <div id="titulaireLockNote" class="titulaire-lock" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Réservé au titulaire de la classe — <b id="titulaireLockName"></b>. Vous pouvez consulter cette appréciation, mais seul le titulaire peut la modifier.</span>
        </div>
        <div id="bulletinValideLock" class="titulaire-lock" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
            <span>Bulletin validé — dévalidez-le pour modifier cette appréciation ou les évaluations de cette période.</span>
        </div>
        <div class="field">
            <label>Résultat global du mois</label>
            <div class="rating-options" id="ratingOptions">
                @foreach (\App\Enums\ResultatMensuel::cases() as $resultat)
                    <div class="rating-option" data-rating="{{ $resultat->value }}">{{ $resultat->label() }}</div>
                @endforeach
            </div>
        </div>
        <div class="field-grid-2">
            <div class="field">
                <label>Assiduité</label>
                <input type="text" id="bulletinAssiduite" placeholder="Ex : Régulière">
            </div>
            <div class="field">
                <label>Conduite</label>
                <input type="text" id="bulletinConduite" placeholder="Ex : Propre et disciplinée">
            </div>
        </div>
        <div class="field">
            <label>Commentaire sur les résultats et recommandations aux parents</label>
            <textarea id="commentText" placeholder="Ex : Travail très satisfaisant. Doit renforcer la lecture et l'écriture pendant les vacances."></textarea>
        </div>
        <div class="field" id="bulletinStatutWrap" style="display:none;">
            <span class="chip" id="bulletinStatutBadge"></span>
        </div>
    </div>
    <div class="panel-foot">
        <button type="button" class="btn ghost" id="commentPanelCancel">Annuler</button>
        <button type="button" class="btn ghost" id="bulletinDevaliderBtn" style="display:none;">Dévalider</button>
        <button type="button" class="btn dark" id="bulletinValiderBtn" style="display:none;">Valider et signer</button>
        <button type="button" class="btn dark" id="commentPanelSave">Enregistrer</button>
    </div>
</div>

<script type="application/json" id="espace-maternelle-data">
    {!! json_encode([
        'classeId' => $classe->id,
        'examenId' => $examenActif?->id,
        'isTitulaire' => $isTitulaire,
        'titulaireNom' => $titulaire?->name,
        'domaines' => $domaines->map(fn ($d) => ['id' => $d->id, 'nom' => $d->nom])->values(),
        'students' => $students,
        'saisieFermee' => $saisieFermee,
        'urls' => [
            'domainesBatch' => route('enseignant.classes.domaines.batch-update', $classe),
            'bulletin' => route('enseignant.classes.bulletins.update', $classe),
            'bulletinValider' => route('enseignant.classes.bulletins.valider', $classe),
            'bulletinDevalider' => route('enseignant.classes.bulletins.devalider', $classe),
        ],
    ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>
@endsection
