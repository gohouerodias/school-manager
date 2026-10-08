{{--
    Variante table-based de _papier.blade.php, réservée au PDF (voir
    eleves/bulletins/pdf.blade.php, rendu par dompdf) — dompdf 3.x ne
    supporte pas `display:grid`/`flex` (voir vendor/dompdf/dompdf/src/Css/
    Style.php, où "grid"/"inline-grid" sont explicitement désactivés), donc
    ce même bulletin doit être remis en page avec des tables/blocs simples
    pour s'imprimer correctement. L'aperçu écran (_papier.blade.php) reste
    la référence visuelle — voir bulletin.html pour le format d'origine —
    ce partial en est la traduction fidèle en balisage compatible dompdf.

    Attend : $classe, $examen, $eleve, $inscription, $bulletin (nullable).
    Primaire/collège : $moyenne, $rang, $totalClasse, $plusForte,
    $plusFaible, $matieres (collection de ['nom' => string, 'note' => ?float]).
    Maternelle (voir Classe::estMaternelle()) : $domaines (collection de
    ['nom' => string, 'valeur' => ?string, 'observation' => ?string]) à la
    place de $matieres/$moyenne/$rang/$plusForte/$plusFaible.
--}}
<table class="bulletin-head-row">
    <tr>
        <td>
            <h2>{{ $classe->estMaternelle() ? "Grille d'évaluation mensuelle" : 'Évaluation mensuelle' }}</h2>
            <div class="bulletin-sub">{{ $examen->date_examen->translatedFormat('F Y') }} · Classe {{ $classe->nom }}</div>
        </td>
        <td class="school">Complexe Scolaire Catholique<br>Madre Trinidad</td>
    </tr>
</table>

<table class="bulletin-grid2">
    <tr>
        <td class="label">Apprenant</td>
        <td class="val">{{ $eleve->nomComplet() }}</td>
        <td class="label">Matricule</td>
        <td class="val">{{ $eleve->matricule }}</td>
    </tr>
</table>

@if ($classe->estMaternelle())
    {{-- Reprend à l'identique la grille papier "GRILLE D'ÉVALUATION MENSUELLE"
         (n°, domaine, une colonne à cocher par valeur TS/S/PS, observation)
         plutôt qu'une seule colonne "Appréciation" — voir _papier.blade.php
         pour la même structure côté aperçu écran. --}}
    <table class="bulletin-table bulletin-table-domaines">
        <thead>
            <tr>
                <th rowspan="2" class="num">N°</th>
                <th rowspan="2">Domaine d'évaluation</th>
                <th colspan="3" style="text-align:center;">Échelle des valeurs</th>
                <th rowspan="2">Observation</th>
            </tr>
            <tr>
                <th class="num">TS</th>
                <th class="num">S</th>
                <th class="num">PS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($domaines as $domaine)
                <tr>
                    <td class="num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $domaine['nom'] }}</td>
                    <td class="num check-cell">{{ $domaine['valeur'] === 'ts' ? '✕' : '' }}</td>
                    <td class="num check-cell">{{ $domaine['valeur'] === 's' ? '✕' : '' }}</td>
                    <td class="num check-cell">{{ $domaine['valeur'] === 'ps' ? '✕' : '' }}</td>
                    <td>{{ $domaine['observation'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bulletin-legende">
        <b>Légende</b> — TS : très satisfaisant · S : satisfaisant · PS : peu satisfaisant
    </div>
    <p class="bulletin-nb">NB : l'évaluation se base sur l'observation permanente de l'apprenant en classe. Il est évalué par rapport à lui-même, non par rapport à ses camarades.</p>

    {{-- Pas de "Conduite" en maternelle (voir le modèle papier) : une seule
         paire label/valeur, contrairement à la grille 2x2 du primaire/collège
         ci-dessous. --}}
    <table class="bulletin-grid2">
        <tr>
            <td class="label">Assiduité</td>
            <td class="val">{{ $bulletin?->assiduite ?? '—' }}</td>
        </tr>
    </table>
@else
    @php
        // Primaire (séance du 07/10/2026) : détail critère minimal /18 +
        // critère de perfectionnement /2 = total /20, comme sur le carnet.
        $avecCriteres = collect($matieres)->contains(fn ($m) => ($m['critere_minimal'] ?? null) !== null || ($m['critere_perfectionnement'] ?? null) !== null);
        $formatCritere = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 2), '0'), '.') : '—';
    @endphp
    <table class="bulletin-table">
        <thead>
            @if ($avecCriteres)
                <tr><th>Matière</th><th style="text-align:center;">Crit. minimal / 18</th><th style="text-align:center;">Crit. perf. / 2</th><th style="text-align:center;">Total / 20</th></tr>
            @else
                <tr><th>Matière</th><th style="text-align:center;">Note / 20</th></tr>
            @endif
        </thead>
        <tbody>
            @foreach ($matieres as $matiere)
                @if ($avecCriteres)
                    <tr>
                        <td>{{ $matiere['nom'] }}</td>
                        <td class="num">{{ $formatCritere($matiere['critere_minimal'] ?? null) }}</td>
                        <td class="num">{{ $formatCritere($matiere['critere_perfectionnement'] ?? null) }}</td>
                        <td class="num">{{ $matiere['note'] !== null ? number_format($matiere['note'], 2) : '—' }}</td>
                    </tr>
                @else
                    <tr><td>{{ $matiere['nom'] }}</td><td class="num">{{ $matiere['note'] !== null ? number_format($matiere['note'], 2) : '—' }}</td></tr>
                @endif
            @endforeach
            <tr class="total-row"><td @if ($avecCriteres) colspan="3" @endif>Moyenne</td><td class="num">{{ $moyenne !== null ? number_format($moyenne, 2) : '—' }}</td></tr>
        </tbody>
    </table>

    <table class="bulletin-grid2">
        <tr>
            <td class="label">Rang</td>
            <td class="val">{{ $rang ? $rang.($rang === 1 ? 'er' : 'ème').' sur '.$totalClasse : '—' }}</td>
            <td class="label">Assiduité</td>
            <td class="val">{{ $bulletin?->assiduite ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plus forte moyenne de la classe</td>
            <td class="val">{{ $plusForte !== null ? number_format($plusForte, 2) : '—' }}</td>
            <td class="label">Conduite</td>
            <td class="val">{{ $bulletin?->conduite ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plus faible moyenne de la classe</td>
            <td class="val">{{ $plusFaible !== null ? number_format($plusFaible, 2) : '—' }}</td>
            <td class="label">Défauts majeurs</td>
            <td class="val">{{ $bulletin?->defauts_majeurs ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Qualités</td>
            <td class="val">{{ $bulletin?->qualites ?? '—' }}</td>
            <td class="label">Décision</td>
            <td class="val">{{ $bulletin?->decision_pedagogique ? 'Renforcer en '.$bulletin->decision_pedagogique : '—' }}</td>
        </tr>
    </table>
@endif

<div class="bulletin-comment-box">
    <div class="label">
        {{ $classe->estMaternelle()
            ? 'Analyse pédagogique sommaire des résultats et recommandations du Responsable des Formateurs'
            : "Commentaire de l'enseignant titulaire" }}
    </div>
    {{ $bulletin?->appreciation ?? '—' }}

    @if ($classe->estMaternelle())
        {{-- Voir _papier.blade.php pour le même bloc côté aperçu écran. --}}
        <div class="bulletin-symboles-legende">
            <div class="titre">Symboles interprétant les résultats de l'apprenant</div>
            @foreach (\App\Enums\ResultatMensuel::groupesSymboles() as $groupe)
                <div class="symbole-row {{ $bulletin?->resultat_global && in_array($bulletin->resultat_global, $groupe['valeurs'], true) ? 'is-actuel' : '' }}">
                    <span class="circle">{{ $groupe['symbole'] }}</span>
                    <span>{{ $groupe['label'] }}</span>
                </div>
            @endforeach
        </div>
    @elseif ($bulletin?->resultat_global)
        <div class="bulletin-symbol-row"><span class="circle">{{ $bulletin->resultat_global->symbole() }}</span> {{ $bulletin->resultat_global->label() }}</div>
    @endif
</div>

<table class="bulletin-manual-grid">
    <tr>
        <td><div class="bulletin-manual-zone">Visa du Directeur<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div></td>
        <td><div class="bulletin-manual-zone">Observations des parents<br><span style="font-style:normal;">(à remplir après remise du bulletin)</span></div></td>
    </tr>
</table>
