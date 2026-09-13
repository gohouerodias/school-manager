{{--
    Variante table-based de _papier_annuel.blade.php, réservée au PDF (voir
    eleves/bulletins/pdf-annuel.blade.php, rendu par dompdf) — même raison que
    _papier_pdf.blade.php : dompdf ne supporte pas `display:grid`/`flex`.

    Attend les mêmes variables que _papier_annuel.blade.php (dont $matieres
    pour le primaire/collège).
--}}
<table class="bulletin-head-row">
    <tr>
        <td>
            <h2>{{ $classe->estMaternelle() ? 'Bilan annuel — récapitulatif des domaines' : "Résultats de fin d'année" }}</h2>
            <div class="bulletin-sub">{{ $anneeAcademique->libelle }} · Classe {{ $classe->nom }}</div>
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

    <table class="bulletin-grid2">
        <tr>
            <td class="label">Rang annuel</td>
            <td class="val">{{ $rang ? $rang.($rang === 1 ? 'er' : 'ème').' sur '.$totalClasse : '—' }}</td>
            <td class="label">Plus forte moyenne de la classe</td>
            <td class="val">{{ $plusForte !== null ? number_format($plusForte, 2) : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plus faible moyenne de la classe</td>
            <td class="val">{{ $plusFaible !== null ? number_format($plusFaible, 2) : '—' }}</td>
            <td class="label"></td>
            <td class="val"></td>
        </tr>
    </table>
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
        <div class="bulletin-manual-zone">Observation non encore renseignée par l'enseignant.</div>
    @endif
</div>

<table class="bulletin-manual-grid">
    <tr>
        <td><div class="bulletin-manual-zone">Titulaire de la classe<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div></td>
        <td><div class="bulletin-manual-zone">Le Directeur / La Directrice<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div></td>
    </tr>
</table>
