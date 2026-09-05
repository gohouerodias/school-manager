{{--
    Variante table-based de _papier.blade.php, réservée au PDF (voir
    eleves/bulletins/pdf.blade.php, rendu par dompdf) — dompdf 3.x ne
    supporte pas `display:grid`/`flex` (voir vendor/dompdf/dompdf/src/Css/
    Style.php, où "grid"/"inline-grid" sont explicitement désactivés), donc
    ce même bulletin doit être remis en page avec des tables/blocs simples
    pour s'imprimer correctement. L'aperçu écran (_papier.blade.php) reste
    la référence visuelle — voir bulletin.html pour le format d'origine —
    ce partial en est la traduction fidèle en balisage compatible dompdf.

    Attend : $classe, $examen, $eleve, $inscription, $bulletin (nullable),
    $moyenne, $rang, $totalClasse, $plusForte, $plusFaible, $matieres
    (collection de ['nom' => string, 'note' => ?float]).
--}}
<table class="bulletin-head-row">
    <tr>
        <td>
            <h2>Évaluation mensuelle</h2>
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

<table class="bulletin-table">
    <thead>
        <tr><th>Matière</th><th style="text-align:center;">Note / 20</th></tr>
    </thead>
    <tbody>
        @foreach ($matieres as $matiere)
            <tr><td>{{ $matiere['nom'] }}</td><td class="num">{{ $matiere['note'] !== null ? number_format($matiere['note'], 2) : '—' }}</td></tr>
        @endforeach
        <tr class="total-row"><td>Moyenne</td><td class="num">{{ $moyenne !== null ? number_format($moyenne, 2) : '—' }}</td></tr>
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

<div class="bulletin-comment-box">
    <div class="label">Commentaire de l'enseignant titulaire</div>
    {{ $bulletin?->appreciation ?? '—' }}
    @if ($bulletin?->resultat_global)
        <div class="bulletin-symbol-row"><span class="circle">{{ $bulletin->resultat_global->symbole() }}</span> {{ $bulletin->resultat_global->label() }}</div>
    @endif
</div>

<table class="bulletin-manual-grid">
    <tr>
        <td><div class="bulletin-manual-zone">Visa du Directeur<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div></td>
        <td><div class="bulletin-manual-zone">Observations des parents<br><span style="font-style:normal;">(à remplir après remise du bulletin)</span></div></td>
    </tr>
</table>
