{{--
    Onglet "Bulletin annuel" — une observation par apprenant pour tout
    l'année (voir Inscription::observation_annuelle), indépendante du mois
    sélectionné plus haut. Partagé entre saisie-notes.blade.php (primaire/
    collège) et saisie-domaines.blade.php (maternelle) puisqu'il n'y a ni
    matière ni domaine ici, juste un texte libre par apprenant.

    Attend : $classe, $isTitulaire, $titulaire, $observationsAnnuelles
    (collection de ['eleveId' => int, 'nom' => string, 'prenom' => string,
    'matricule' => string, 'observation' => ?string]).
--}}
<div class="grade-topbar" style="margin-top:36px;">
    <div class="grade-title-row">
        <h2 style="margin:0;font-size:19px;">Bulletin annuel — Observations</h2>
    </div>
</div>
<p class="hint" style="margin-bottom:14px;">
    Cette observation apparaît sur le bulletin annuel de fin d'année de chaque apprenant, indépendamment du mois sélectionné ci-dessus.
    @unless ($isTitulaire)
        Réservée au titulaire de la classe{{ $titulaire ? " — {$titulaire->name}" : '' }} : vous pouvez la consulter, mais pas la modifier.
    @endunless
</p>

<div class="sheet-wrap">
    <table
        class="sheet"
        id="observationsAnnuellesTable"
        data-url="{{ route('enseignant.classes.observation-annuelle.update', $classe) }}"
        data-editable="{{ $isTitulaire ? '1' : '0' }}"
    >
        <thead>
            <tr>
                <th class="col-student">Apprenant</th>
                <th>Observation annuelle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($observationsAnnuelles as $obs)
                <tr data-eleve-id="{{ $obs['eleveId'] }}">
                    <th class="row-student">
                        <div class="student-cell">
                            <div class="av">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($obs['prenom'], 0, 1).\Illuminate\Support\Str::substr($obs['nom'], 0, 1)) }}</div>
                            <div class="txt"><b>{{ \Illuminate\Support\Str::upper($obs['nom']) }} {{ $obs['prenom'] }}</b><span>{{ $obs['matricule'] }}</span></div>
                        </div>
                    </th>
                    <td>
                        <textarea
                            class="observation-annuelle-input"
                            data-eleve-id="{{ $obs['eleveId'] }}"
                            @disabled(! $isTitulaire)
                            placeholder="Ex : Bonne année scolaire, apprenant sérieux et appliqué."
                        >{{ $obs['observation'] }}</textarea>
                        <span class="observation-annuelle-status" data-eleve-id="{{ $obs['eleveId'] }}"></span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="hint">Aucun apprenant inscrit dans cette classe.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
