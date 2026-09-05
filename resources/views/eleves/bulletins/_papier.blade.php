{{--
    Bulletin papier d'un apprenant pour un examen mensuel — balisage et
    classes CSS repris à l'identique de bulletin.html (voir
    openBulletinPreview()) : c'est ce même partial que rend l'écran d'aperçu
    (eleves/bulletins/apercu.blade.php). Le PDF dompdf (produit par
    App\Jobs\GenererBulletinsClasseJob / téléchargement individuel) utilise
    en revanche _papier_pdf.blade.php, une variante table-based — dompdf ne
    supporte pas `display:grid`.

    Attend : $classe, $examen, $eleve, $inscription, $bulletin (nullable),
    $moyenne, $rang, $totalClasse, $plusForte, $plusFaible, $matieres
    (collection de ['nom' => string, 'note' => ?float]).
--}}
<div class="bulletin-head-row">
    <div>
        <h2>Évaluation mensuelle</h2>
        <div class="bulletin-sub">{{ $examen->date_examen->translatedFormat('F Y') }} · Classe {{ $classe->nom }}</div>
    </div>
    <div class="school">Complexe Scolaire Catholique<br>Madre Trinidad</div>
</div>

<div class="bulletin-grid2" style="margin-bottom:10px;">
    <div class="row"><span>Apprenant</span><b>{{ $eleve->nomComplet() }}</b></div>
    <div class="row"><span>Matricule</span><b>{{ $eleve->matricule }}</b></div>
</div>

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

<div class="bulletin-comment-box">
    <div class="label">Commentaire de l'enseignant titulaire</div>
    {{ $bulletin?->appreciation ?? '—' }}
    @if ($bulletin?->resultat_global)
        <div class="bulletin-symbol-row"><span class="circle">{{ $bulletin->resultat_global->symbole() }}</span> {{ $bulletin->resultat_global->label() }}</div>
    @endif
</div>

<div class="bulletin-manual-grid">
    <div class="bulletin-manual-zone">Visa du Directeur<br><span style="font-style:normal;">(signature manuscrite à l'impression)</span></div>
    <div class="bulletin-manual-zone">Observations des parents<br><span style="font-style:normal;">(à remplir après remise du bulletin)</span></div>
</div>
