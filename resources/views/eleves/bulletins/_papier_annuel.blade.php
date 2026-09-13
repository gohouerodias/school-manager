{{--
    Bulletin annuel d'un apprenant — équivalent annuel de _papier.blade.php
    (voir ce fichier pour les conventions générales) : plus de matières/notes
    du mois, seulement la synthèse de l'année (moyenne annuelle et rang pour
    le primaire/collège, récapitulatif TS/S/PS pour la maternelle) et la
    décision de passage (voir Academique\DecisionPassageController).

    Attend : $classe, $anneeAcademique, $inscription, $eleve, $decision
    (nullable DecisionAnnuelle), $observationAnnuelle (nullable string, voir
    Inscription::observation_annuelle et Enseignant\
    EspaceEnseignantController::saveObservationAnnuelle()).
    Primaire/collège : $moyenne, $rang, $totalClasse, $plusForte, $plusFaible,
    $matieres (collection de ['nom' => string, 'note' => ?float] — moyenne
    annuelle par matière, voir BulletinGenerationService::
    matieresAnnuellesPourInscription()).
    Maternelle (voir Classe::estMaternelle()) : $domaines (collection de
    ['nom' => string, 'ts' => int, 's' => int, 'ps' => int]) à la place des
    champs ci-dessus.
--}}
<div class="bulletin-head-row">
    <div>
        <h2>{{ $classe->estMaternelle() ? 'Bilan annuel — récapitulatif des domaines' : "Résultats de fin d'année" }}</h2>
        <div class="bulletin-sub">{{ $anneeAcademique->libelle }} · Classe {{ $classe->nom }}</div>
    </div>
    <div class="school">Complexe Scolaire Catholique<br>Madre Trinidad</div>
</div>

<div class="bulletin-grid2" style="margin-bottom:10px;">
    <div class="row"><span>Apprenant</span><b>{{ $eleve->nomComplet() }}</b></div>
    <div class="row"><span>Matricule</span><b>{{ $eleve->matricule }}</b></div>
</div>

@if ($classe->estMaternelle())
    <table class="bulletin-table bulletin-table-domaines">
        <thead>
            <tr>
                <th rowspan="2" class="num">N°</th>
                <th rowspan="2">Domaine d'évaluation</th>
                <th colspan="3" style="text-align:center;">Nombre d'évaluations sur l'année</th>
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
                    <td class="num">{{ $domaine['ts'] }}</td>
                    <td class="num">{{ $domaine['s'] }}</td>
                    <td class="num">{{ $domaine['ps'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bulletin-legende">
        <b>Légende</b> — TS : très satisfaisant · S : satisfaisant · PS : peu satisfaisant
    </div>
@else
    <table class="bulletin-table">
        <thead>
            <tr><th>Matière</th><th style="text-align:center;">Moyenne annuelle / 20</th></tr>
        </thead>
        <tbody>
            @foreach ($matieres as $matiere)
                <tr><td>{{ $matiere['nom'] }}</td><td class="num">{{ $matiere['note'] !== null ? number_format($matiere['note'], 2) : '—' }}</td></tr>
            @endforeach
            <tr class="total-row"><td>Moyenne générale annuelle</td><td class="num">{{ $moyenne !== null ? number_format($moyenne, 2) : '—' }}</td></tr>
        </tbody>
    </table>
    @if ($matieres->contains(fn ($m) => $m['note'] === null))
        <p class="bulletin-nb">NB : les matières sans moyenne annuelle n'ont pas encore été (re)calculées — voir le bouton « Calculer les moyennes annuelles ».</p>
    @endif

    <div class="bulletin-grid2">
        <div class="row"><span>Rang annuel</span><b>{{ $rang ? $rang.($rang === 1 ? 'er' : 'ème').' sur '.$totalClasse : '—' }}</b></div>
        <div class="row"><span>Plus forte moyenne de la classe</span><b>{{ $plusForte !== null ? number_format($plusForte, 2) : '—' }}</b></div>
        <div class="row"><span>Plus faible moyenne de la classe</span><b>{{ $plusFaible !== null ? number_format($plusFaible, 2) : '—' }}</b></div>
    </div>
@endif

<div class="bulletin-comment-box">
    <div class="label">Décision du conseil des maîtres</div>
    {{ $decision?->label() ?? 'Pas encore décidée — voir Académique → Décisions de passage.' }}
</div>

<div class="bulletin-comment-box">
    <div class="label">
        {{ $classe->estMaternelle()
            ? 'Analyse pédagogique sommaire des résultats et recommandations du Responsable des Formateurs'
            : "Observations de l'enseignant(e)" }}
    </div>
    @if ($observationAnnuelle)
        {{ $observationAnnuelle }}
    @else
        <div class="bulletin-manual-zone" style="text-align:left;">Observation non encore renseignée par l'enseignant.</div>
    @endif
</div>

<div class="bulletin-manual-grid">
    <div class="bulletin-manual-zone">Titulaire de la classe<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div>
    <div class="bulletin-manual-zone">Le Directeur / La Directrice<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div>
</div>
