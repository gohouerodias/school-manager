{{--
    Bulletin papier d'un apprenant pour un examen mensuel — balisage et
    classes CSS repris à l'identique de bulletin.html (voir
    openBulletinPreview()) : c'est ce même partial que rend l'écran d'aperçu
    (eleves/bulletins/apercu.blade.php). Le PDF dompdf (produit par
    App\Jobs\GenererBulletinsClasseJob / téléchargement individuel) utilise
    en revanche _papier_pdf.blade.php, une variante table-based — dompdf ne
    supporte pas `display:grid`.

    Attend : $classe, $examen, $eleve, $inscription, $bulletin (nullable).
    Primaire/collège : $moyenne, $rang, $totalClasse, $plusForte,
    $plusFaible, $matieres (collection de ['nom' => string, 'note' => ?float]).
    Maternelle (voir Classe::estMaternelle()) : $domaines (collection de
    ['nom' => string, 'valeur' => ?string, 'observation' => ?string]) à la
    place de $matieres/$moyenne/$rang/$plusForte/$plusFaible.
--}}
<div class="bulletin-head-row">
    <div>
        <h2>{{ $classe->estMaternelle() ? "Grille d'évaluation mensuelle" : 'Évaluation mensuelle' }}</h2>
        <div class="bulletin-sub">{{ $examen->date_examen->translatedFormat('F Y') }} · Classe {{ $classe->nom }}</div>
    </div>
    <div class="school">Complexe Scolaire Catholique<br>Madre Trinidad</div>
</div>

<div class="bulletin-grid2" style="margin-bottom:10px;">
    <div class="row"><span>Apprenant</span><b>{{ $eleve->nomComplet() }}</b></div>
    <div class="row"><span>Matricule</span><b>{{ $eleve->matricule }}</b></div>
</div>

@if ($classe->estMaternelle())
    {{-- Reprend à l'identique la grille papier "GRILLE D'ÉVALUATION MENSUELLE"
         (n°, domaine, une colonne à cocher par valeur TS/S/PS, observation)
         plutôt qu'une seule colonne "Appréciation" — voir la légende et la
         note NB juste en dessous, elles aussi reprises du document papier. --}}
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
         colonne, contrairement à la grille à 2 colonnes du primaire/collège
         ci-dessous. --}}
    <div class="bulletin-grid2 bulletin-grid2-single">
        <div class="row"><span>Assiduité</span><b>{{ $bulletin?->assiduite ?? '—' }}</b></div>
    </div>
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

    <div class="bulletin-grid2">
        <div class="row"><span>Rang</span><b>{{ $rang ? $rang.($rang === 1 ? 'er' : 'ème').' sur '.$totalClasse : '—' }}</b></div>
        <div class="row"><span>Assiduité</span><b>{{ $bulletin?->assiduite ?? '—' }}</b></div>
        <div class="row"><span>Plus forte moyenne de la classe</span><b>{{ $plusForte !== null ? number_format($plusForte, 2) : '—' }}</b></div>
        <div class="row"><span>Conduite</span><b>{{ $bulletin?->conduite ?? '—' }}</b></div>
        <div class="row"><span>Plus faible moyenne de la classe</span><b>{{ $plusFaible !== null ? number_format($plusFaible, 2) : '—' }}</b></div>
        <div class="row"><span>Défauts majeurs</span><b>{{ $bulletin?->defauts_majeurs ?: '—' }}</b></div>
        <div class="row"><span>Qualités</span><b>{{ $bulletin?->qualites ?? '—' }}</b></div>
        <div class="row"><span>Décision</span><b>{{ $bulletin?->decision_pedagogique ? 'Renforcer en '.$bulletin->decision_pedagogique : '—' }}</b></div>
    </div>
@endif

<div class="bulletin-comment-box">
    <div class="label">
        {{ $classe->estMaternelle()
            ? 'Analyse pédagogique sommaire des résultats et recommandations du Responsable des Formateurs'
            : "Commentaire de l'enseignant titulaire" }}
    </div>
    {{ $bulletin?->appreciation ?? '—' }}

    @if ($classe->estMaternelle())
        {{-- "Symboles interprétant les résultats de l'apprenant" du modèle
             papier : les 3 symboles sont toujours listés, celui du résultat
             réellement enregistré est encerclé (voir .is-actuel) — plutôt
             qu'une seule ligne symbole+libellé comme pour primaire/collège
             ci-dessous. --}}
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

<div class="bulletin-manual-grid">
    <div class="bulletin-manual-zone">Visa du Directeur<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div>
    <div class="bulletin-manual-zone">Observations des parents<br><span style="font-style:normal;">(à remplir après remise du bulletin)</span></div>
</div>
