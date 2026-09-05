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
                            Examen mensuel — {{ $examen->date_examen->translatedFormat('F Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        @if ($examenActif && $matieres->isNotEmpty())
            <a class="btn ghost" href="{{ route('enseignant.classes.notes.export', ['classe' => $classe, 'examen_id' => $examenActif->id]) }}">Exporter en Excel</a>
        @endif
    </div>
</div>

@if ($examens->isEmpty())
    <div class="hint">Aucun examen disponible pour cette classe pour l'instant — ils sont créés depuis Académique &gt; Examens.</div>
@elseif ($matieres->isEmpty())
    <div class="hint">Vous n'êtes affecté à aucune matière pour cette classe.</div>
@else
    @if ($isTitulaire && $matiereIdsEditables->isEmpty())
        <div class="hint" style="margin-bottom:12px;">Vous êtes titulaire de cette classe mais n'y enseignez aucune matière — vous pouvez consulter les notes de toutes les matières, mais seuls les enseignants affectés peuvent les modifier.</div>
    @endif
    <div class="grade-toolbar">
        <div class="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="studentSearch" placeholder="Rechercher un apprenant...">
        </div>
        <div class="legend">
            <span><span class="dot" style="background:var(--red);"></span> Note &lt; 10</span>
            <span><span class="dot" style="background:var(--green);"></span> Note ≥ 16</span>
            <span><span class="dot" style="background:var(--gold);"></span> Commentaire ajouté</span>
        </div>
    </div>

    @if ($saisieFermee)
        <div class="titulaire-lock" style="margin-bottom:12px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Délai de saisie dépassé ({{ $examenActif->date_limite_saisie->format('d/m/Y') }}) — cette période est en lecture seule.</span>
        </div>
    @endif

    <div class="sheet-wrap @if($saisieFermee) sheet-locked @endif">
        <table class="sheet" id="sheetTable">
            <thead>
                <tr>
                    <th class="col-student">Apprenant</th>
                    @foreach ($matieres as $matiere)
                        @php $editable = $matiereIdsEditables->contains($matiere->id); @endphp
                        <th @class(['col-readonly' => ! $editable])>
                            {{ $matiere->nom }}<span class="sub">/20 · Coef {{ rtrim(rtrim(number_format($matiere->pivot->coefficient, 1), '0'), '.') }}</span>
                            @unless ($editable)
                                <span class="sub" title="Lecture seule — vous n'enseignez pas cette matière">🔒</span>
                            @endunless
                        </th>
                    @endforeach
                    @if ($isTitulaire)
                        <th class="col-moyenne-h">Moyenne<span class="sub">/20</span></th>
                    @endif
                    <th class="col-comment-h">Commentaire</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php
                        // La moyenne n'a de sens qu'une fois toutes les matières du
                        // programme notées pour cet apprenant — un professeur d'une
                        // seule matière ne la voit d'ailleurs jamais (voir plus haut) et
                        // le titulaire ne peut valider/signer que si elle est complète
                        // (voir EspaceEnseignantController::validerBulletin()).
                        $valeurs = collect($student['notes'])->filter(fn ($v) => $v !== null);
                        $notesCompletes = $matieres->isNotEmpty() && $valeurs->count() === $matieres->count();
                        $moyenne = $notesCompletes ? round($valeurs->avg(), 2) : null;
                        $aUnCommentaire = ! empty($student['subjectComments']) || ! empty($student['bulletin']['appreciation'] ?? null) || ! empty($student['bulletin']['resultat'] ?? null);
                    @endphp
                    <tr data-student-id="{{ $student['eleveId'] }}" data-search="{{ \Illuminate\Support\Str::lower($student['nom'].' '.$student['prenom']) }}">
                        <th class="row-student">
                            <div class="student-cell">
                                <div class="av">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student['prenom'], 0, 1).\Illuminate\Support\Str::substr($student['nom'], 0, 1)) }}</div>
                                <div class="txt"><b>{{ \Illuminate\Support\Str::upper($student['nom']) }} {{ $student['prenom'] }}</b><span>{{ $student['matricule'] }}</span></div>
                            </div>
                        </th>
                        @foreach ($matieres as $matiere)
                            @php
                                $val = $student['notes'][$matiere->id] ?? null;
                                $editable = $matiereIdsEditables->contains($matiere->id);
                            @endphp
                            <td class="note-cell @if($val !== null) @if($val < 10) low @elseif($val >= 16) high @endif @endif @unless($editable) readonly-cell @endunless" data-matiere-id="{{ $matiere->id }}" data-editable="{{ $editable ? '1' : '0' }}">
                                <input type="number" min="0" max="20" step="0.5" value="{{ $val ?? '' }}" placeholder="—" @disabled($saisieFermee || ! $editable) title="{{ $editable ? '' : "Lecture seule — vous n'enseignez pas cette matière" }}">
                            </td>
                        @endforeach
                        @if ($isTitulaire)
                            <td class="col-moyenne" data-role="moyenne" title="{{ $notesCompletes ? '' : "En attente — toutes les matières n'ont pas encore été notées" }}">
                                {{ $notesCompletes ? number_format($moyenne, 2) : '—' }}
                            </td>
                        @endif
                        <td class="col-comment">
                            <button type="button" class="comment-btn @if($aUnCommentaire) filled @endif" data-student-id="{{ $student['eleveId'] }}" title="Commentaire mensuel">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $matieres->count() + ($isTitulaire ? 3 : 2) }}" class="hint">Aucun apprenant inscrit dans cette classe.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="hint" style="margin-top:12px;">
        @if ($saisieFermee)
            Cette période n'accepte plus de nouvelles notes.
        @else
            Cliquez dans une case pour saisir ou modifier une note, videz-la pour la supprimer, puis cliquez sur « Enregistrer les modifications » pour sauvegarder.
            @if ($examenActif)
                Délai de saisie : {{ $examenActif->date_limite_saisie->format('d/m/Y') }}.
            @endif
        @endif
    </p>
@endif

<div class="save-bar" id="saveBar">
    <span id="saveBarCount"></span>
    <button type="button" class="btn ghost" id="saveBarCancel">Annuler</button>
    <button type="button" class="btn dark" id="saveBarBtn">Enregistrer les modifications</button>
</div>

<div class="overlay" id="overlay"></div>

<div class="panel" id="commentPanel">
    <div class="panel-head">
        <div>
            <h2 id="commentStudentName">Commentaire</h2>
            <p id="commentPeriodLabel"></p>
        </div>
        <button type="button" class="panel-close" id="commentPanelClose">✕</button>
    </div>
    <div class="panel-body">
        <div class="comment-section-label">Commentaire par matière</div>
        <div id="subjectCommentsWrap"></div>

        <div class="comment-section-label" style="margin-top:8px;">Bulletin mensuel (commentaire général)</div>
        <div id="titulaireLockNote" class="titulaire-lock" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Réservé au titulaire de la classe — <b id="titulaireLockName"></b>. Vous pouvez consulter ce commentaire, mais seul le titulaire peut le modifier.</span>
        </div>
        <div id="bulletinValideLock" class="titulaire-lock" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
            <span>Bulletin validé — dévalidez-le pour modifier ce commentaire ou les notes de cette période.</span>
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
                <input type="text" id="bulletinAssiduite" placeholder="Ex : Bonne">
            </div>
            <div class="field">
                <label>Conduite</label>
                <input type="text" id="bulletinConduite" placeholder="Ex : Bonne">
            </div>
            <div class="field">
                <label>Qualités</label>
                <input type="text" id="bulletinQualites" placeholder="Ex : Sérieux, ponctuel">
            </div>
            <div class="field">
                <label>Défauts majeurs</label>
                <input type="text" id="bulletinDefautsMajeurs" placeholder="Ex : Bavardage en classe">
            </div>
        </div>
        <div class="field">
            <label>Décision (à renforcer en...)</label>
            <input type="text" id="bulletinDecisionPedagogique" placeholder="Ex : lecture et en écriture">
        </div>
        <div class="field">
            <label>Commentaire sur les résultats et recommandations aux parents</label>
            <textarea id="commentText" placeholder="Ex : Travail très satisfaisant. Doit renforcer sa capacité en lecture-écriture."></textarea>
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

<script type="application/json" id="espace-enseignant-data">
    {!! json_encode([
        'classeId' => $classe->id,
        'examenId' => $examenActif?->id,
        'isTitulaire' => $isTitulaire,
        'titulaireNom' => $titulaire?->name,
        'matieres' => $matieres->map(fn ($m) => ['id' => $m->id, 'nom' => $m->nom])->values(),
        'matiereIdsEditables' => $matiereIdsEditables->values(),
        'students' => $students,
        'saisieFermee' => $saisieFermee,
        'urls' => [
            'note' => route('enseignant.classes.notes.update', $classe),
            'notesBatch' => route('enseignant.classes.notes.batch-update', $classe),
            'commentaireMatiere' => route('enseignant.classes.commentaires-matiere.update', $classe),
            'bulletin' => route('enseignant.classes.bulletins.update', $classe),
            'bulletinValider' => route('enseignant.classes.bulletins.valider', $classe),
            'bulletinDevalider' => route('enseignant.classes.bulletins.devalider', $classe),
        ],
    ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>
@endsection
