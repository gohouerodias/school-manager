@extends('layouts.app')

@section('title', $anneeAcademique->libelle)

@section('content')
<x-page-header
    :title="$anneeAcademique->libelle"
    :subtitle="'Du '.$anneeAcademique->date_debut->format('d/m/Y').' au '.$anneeAcademique->date_fin->format('d/m/Y')"
>
    <x-slot:actions>
        <a href="{{ route('academique.annees.index') }}" class="btn ghost">← Années académiques</a>
        <a href="{{ route('academique.annees.decisions.index', $anneeAcademique) }}" class="btn ghost">Décisions de passage</a>
        @if ($anneeAcademique->est_active)
            <span class="doc-status-badge complet">Année active</span>
        @else
            <form method="POST" action="{{ route('academique.annees.demarrer', $anneeAcademique) }}"
                  data-confirm-submit data-confirm-danger="1" data-confirm-label="Démarrer cette année"
                  data-confirm-title="Démarrer cette année académique"
                  data-confirm-message="Démarrer « {{ $anneeAcademique->libelle }} » ?{{ $anneeActive && $anneeAcademique->promouvoir_automatiquement ? ' Les élèves admis ou redoublants de « '.$anneeActive->libelle.' » seront automatiquement inscrits dans une classe de cette nouvelle année, selon leur décision de fin d\'année.' : '' }}{{ $anneeActive && ! $anneeAcademique->promouvoir_automatiquement ? ' La promotion automatique est désactivée pour cette année : aucun élève ne sera inscrit automatiquement.' : '' }} Cette action ne peut pas être annulée simplement.">
                @csrf
                <button type="submit" class="btn danger">Démarrer cette année</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@if (session('nonResolus') && count(session('nonResolus')))
    <div class="alert-error wizard-error-summary">
        <b>{{ count(session('nonResolus')) }} élève(s) n'ont pas pu être affecté(s) automatiquement à une classe :</b>
        <ul>
            @foreach (session('nonResolus') as $cas)
                <li>{{ $cas['raison'] }}</li>
            @endforeach
        </ul>
        <div class="hint">Utilisez le menu déroulant « Classe » depuis la liste des apprenants pour les affecter manuellement.</div>
    </div>
@endif

@php
    // Lettres déjà utilisées par niveau (dernier mot du nom de chaque classe,
    // ex : "CM1 A" -> "A") — sert à filtrer le <select> "Lettre de la classe"
    // (voir initClasseLettreFilter()/initClasseEdit() dans
    // annee-academique-show.js) pour qu'une même lettre ne puisse pas être
    // choisie deux fois pour un même niveau, cette année.
    $lettresParNiveau = $anneeAcademique->classes->groupBy('niveau_id')->map(
        fn ($classes) => $classes->map(fn ($c) => \Illuminate\Support\Str::of($c->nom)->afterLast(' ')->upper()->toString())->values()->all()
    );
    $lettresDisponibles = range('A', 'Z');
@endphp

{{-- Chaque section (programme, classes, affectations) vit dans son propre
     onglet — sa table ET son bouton « Ajouter » lui appartiennent en propre,
     invisibles tant que l'onglet n'est pas actif (voir initTabs(), et
     panel-error-reopen.js qui bascule automatiquement sur le bon onglet si
     une erreur de validation ramène ici depuis l'un de ces formulaires). --}}
<div class="tabs-nav" data-tabs>
    <button type="button" class="tab-btn active" data-tab-btn="programme">Programme par niveau</button>
    <button type="button" class="tab-btn" data-tab-btn="classes">Classes</button>
    <button type="button" class="tab-btn" data-tab-btn="affectations">Affectations enseignants</button>
    <button type="button" class="tab-btn" data-tab-btn="examens">Examens</button>
    <button type="button" class="tab-btn" data-tab-btn="bulletins">Bulletins</button>
</div>

<div data-tab-panel="programme">
    <section class="config-section">
        <div class="config-section-head">
            <h2>Programme par niveau</h2>
            <div class="content-head-actions">
                <button type="button" class="btn ghost" data-panel-open="new-niveau-domaine">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    Ajouter des domaines (maternelle)
                </button>
                <button type="button" class="btn primary" data-panel-open="new-niveau-matiere">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    Ajouter une matière au programme
                </button>
            </div>
        </div>

        <x-data-table id="niveau-matieres-table">
            <x-slot:head>
                <th>Niveau</th>
                <th>Matières (coefficient)</th>
                <th>Domaines d'évaluation (maternelle)</th>
            </x-slot:head>

            @forelse ($niveaux as $niveau)
                @php
                    $lignes = $anneeAcademique->niveauMatieres->where('niveau_id', $niveau->id);
                    $lignesDomaines = $anneeAcademique->niveauDomaines->where('niveau_id', $niveau->id);
                @endphp
                <tr>
                    <td><b>{{ $niveau->libelle }}</b></td>
                    <td>
                        <div class="chips">
                            @forelse ($lignes as $ligne)
                                <span class="chip">
                                    {{ $ligne->matiere->nom }} ({{ rtrim(rtrim(number_format($ligne->coefficient, 1), '0'), '.') }})
                                    <button
                                        type="button"
                                        class="chip-edit"
                                        title="Modifier le coefficient"
                                        data-panel-open="edit-niveau-matiere"
                                        data-edit-niveau-matiere-trigger
                                        data-edit-url="{{ route('academique.niveau-matieres.update', $ligne) }}"
                                        data-edit-matiere-nom="{{ $ligne->matiere->nom }}"
                                        data-edit-niveau-libelle="{{ $niveau->libelle }}"
                                        data-edit-coefficient="{{ $ligne->coefficient }}"
                                    >✎</button>
                                    <form method="POST" action="{{ route('academique.niveau-matieres.destroy', $ligne) }}" style="display:inline;"
                                          data-confirm-submit data-confirm-danger="1" data-confirm-label="Retirer"
                                          data-confirm-title="Retirer cette matière du programme"
                                          data-confirm-message="Retirer « {{ $ligne->matiere->nom }} » du programme de « {{ $niveau->libelle }} » pour « {{ $anneeAcademique->libelle }} » ? Les classes déjà créées pour ce niveau garderont cette matière tant qu'elles ne sont pas modifiées.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="chip-remove" title="Retirer">✕</button>
                                    </form>
                                </span>
                            @empty
                                <span class="table-empty-state">Aucune matière au programme.</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div class="chips">
                            @forelse ($lignesDomaines as $ligneDomaine)
                                <span class="chip">
                                    {{ $ligneDomaine->domaineEvaluation->nom }}
                                    <form method="POST" action="{{ route('academique.niveau-domaines.destroy', $ligneDomaine) }}" style="display:inline;"
                                          data-confirm-submit data-confirm-danger="1" data-confirm-label="Retirer"
                                          data-confirm-title="Retirer ce domaine du programme"
                                          data-confirm-message="Retirer « {{ $ligneDomaine->domaineEvaluation->nom }} » du programme de « {{ $niveau->libelle }} » pour « {{ $anneeAcademique->libelle }} » ? Les classes déjà créées pour ce niveau garderont ce domaine tant qu'elles ne sont pas modifiées.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="chip-remove" title="Retirer">✕</button>
                                    </form>
                                </span>
                            @empty
                                <span class="table-empty-state">—</span>
                            @endforelse
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="table-empty-state">Aucun niveau — créez-en depuis « Niveaux &amp; matières ».</td>
                </tr>
            @endforelse
        </x-data-table>
    </section>
</div>

<div data-tab-panel="classes" style="display:none;">
    <section class="config-section">
        <div class="config-section-head">
            <h2>Classes</h2>
            <button type="button" class="btn primary" data-panel-open="new-classe">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Ajouter une classe
            </button>
        </div>

        <x-data-table id="classes-table">
            <x-slot:head>
                <th>Nom</th>
                <th>Niveau</th>
                <th>Matières / domaines</th>
                <th></th>
            </x-slot:head>

            @forelse ($anneeAcademique->classes as $classe)
                @php
                    $lettreActuelle = \Illuminate\Support\Str::of($classe->nom)->afterLast(' ')->upper()->toString();
                    $autresLettres = collect($lettresParNiveau[$classe->niveau_id] ?? [])->reject(fn ($l) => $l === $lettreActuelle)->implode(',');
                    // Maternelle utilise des "domaines d'évaluation" qualitatifs,
                    // pas des matières chiffrées (voir Classe::estMaternelle()) —
                    // compter matieres() pour ce cycle donnerait toujours 0 même
                    // quand le programme est correctement configuré.
                    $estMaternelleClasse = $classe->niveau->cycle === \App\Enums\CycleNiveau::Maternelle;
                @endphp
                <tr>
                    <td><b>{{ $classe->nom }}</b></td>
                    <td>{{ $classe->niveau->libelle }}</td>
                    <td>{{ $estMaternelleClasse ? $classe->domaines->count() : $classe->matieres->count() }}</td>
                    <td>
                        <div class="row-actions-group">
                            <button
                                type="button"
                                class="row-edit"
                                title="Modifier"
                                data-panel-open="edit-classe"
                                data-edit-classe-trigger
                                data-edit-url="{{ route('academique.classes.update', $classe) }}"
                                data-edit-niveau-libelle="{{ $classe->niveau->libelle }}"
                                data-edit-lettre="{{ $lettreActuelle }}"
                                data-edit-lettres-utilisees="{{ $autresLettres }}"
                            >✎</button>
                            <form method="POST" action="{{ route('academique.classes.destroy', $classe) }}"
                                  data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
                                  data-confirm-title="Supprimer cette classe"
                                  data-confirm-message="Supprimer la classe « {{ $classe->nom }} » ?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="table-empty-state">Aucune classe pour cette année pour l'instant.</td>
                </tr>
            @endforelse
        </x-data-table>
    </section>
</div>

<div data-tab-panel="affectations" style="display:none;">
    <section class="config-section">
        <div class="config-section-head">
            <div>
                <h2>Affectation des enseignants</h2>
                <p>Pour chaque classe : quels enseignants, pour quelles matières, et qui est le titulaire (seul habilité à valider le bulletin mensuel).</p>
            </div>
        </div>

        <div class="affectation-cards-grid">
            @forelse ($anneeAcademique->classes as $classe)
                @php
                    $estMaternelle = $classe->niveau->cycle === \App\Enums\CycleNiveau::Maternelle;
                    $estClasseEntiere = in_array($classe->niveau->cycle, [\App\Enums\CycleNiveau::Maternelle, \App\Enums\CycleNiveau::Primaire], true);
                    $affectationsClasse = $anneeAcademique->affectations->where('classe_id', $classe->id);
                    $parEnseignant = $affectationsClasse->groupBy('enseignant_id');
                    $titulaireAffectation = $affectationsClasse->firstWhere('est_professeur_principal', true);
                    $enseignantsPayload = $parEnseignant->map(fn ($groupe) => [
                        'id' => $groupe->first()->enseignant_id,
                        'nom' => $groupe->first()->enseignant->name,
                        'matieres' => $estClasseEntiere ? 'Toutes les matières' : $groupe->pluck('matiere.nom')->implode(', '),
                        'estTitulaire' => (bool) $groupe->first()->est_professeur_principal,
                        'destroyUrl' => route('academique.classes.enseignants.destroy', [$classe, $groupe->first()->enseignant_id]),
                    ])->values();

                    // Programme de cette classe (matières pour primaire/
                    // collège, domaines pour maternelle) avec, pour chacun,
                    // l'enseignant qui le couvre actuellement s'il y en a
                    // un — le détail matière par matière que l'ancien
                    // tableau (une ligne "Enseignants affectés" globale par
                    // classe) ne montrait pas.
                    $itemsProgramme = $estMaternelle ? $classe->domaines : $classe->matieres;
                    $itemsPayload = $itemsProgramme->map(function ($item) use ($estClasseEntiere, $affectationsClasse, $titulaireAffectation) {
                        $enseignant = $estClasseEntiere
                            ? $titulaireAffectation?->enseignant
                            : $affectationsClasse->firstWhere('matiere_id', $item->id)?->enseignant;

                        return ['nom' => $item->nom, 'enseignantNom' => $enseignant?->name];
                    })->values();
                    $nbAssignes = $itemsPayload->filter(fn (array $i) => $i['enseignantNom'])->count();
                    $nbTotal = $itemsPayload->count();
                @endphp
                <div class="affectation-card">
                    <div class="affectation-card-head">
                        <div>
                            <h3>{{ $classe->nom }}</h3>
                            <span class="affectation-card-niveau">{{ $classe->niveau->libelle }}</span>
                        </div>
                        <button
                            type="button"
                            class="row-action-btn"
                            title="Gérer l'affectation de cette classe"
                            data-panel-open="gerer-affectation"
                            data-gerer-affectation-trigger
                            data-classe-id="{{ $classe->id }}"
                            data-classe-nom="{{ $classe->nom }}"
                            data-classe-entiere="{{ $estClasseEntiere ? '1' : '0' }}"
                            data-titulaire-url="{{ route('academique.classes.titulaire.update', $classe) }}"
                            data-matiere-ids="{{ $classe->matieres->pluck('id')->implode(',') }}"
                            data-enseignants="{{ $enseignantsPayload->toJson() }}"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Gérer
                        </button>
                    </div>

                    @if ($estClasseEntiere)
                        <div class="affectation-card-titulaire">
                            @forelse ($enseignantsPayload as $ens)
                                <div class="affectation-card-enseignant-row">
                                    <x-avatar :name="$ens['nom']" :profil="\App\Enums\ProfilUtilisateur::Enseignant" />
                                    <span>{{ $ens['nom'] }} @if ($ens['estTitulaire']) <i>— titulaire</i> @endif</span>
                                </div>
                            @empty
                                <span class="affectation-card-empty">Aucun enseignant affecté à cette classe.</span>
                            @endforelse
                        </div>
                    @else
                        <div class="affectation-card-progress">
                            <span @class(['is-complete' => $nbTotal > 0 && $nbAssignes === $nbTotal])>{{ $nbAssignes }} / {{ $nbTotal }} matières assignées</span>
                            @if ($titulaireAffectation)
                                <span class="titulaire-pill">
                                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2 2 8.5l1.7 9.5h16.6L22 8.5 12 2Zm0 15.5a1.4 1.4 0 1 1 0-2.8 1.4 1.4 0 0 1 0 2.8Z"/></svg>
                                    {{ $titulaireAffectation->enseignant->name }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="affectation-card-items">
                        @forelse ($itemsPayload as $item)
                            <span @class(['matiere-badge', 'is-assigned' => $item['enseignantNom'], 'is-empty' => ! $item['enseignantNom']])>
                                <b>{{ $item['nom'] }}</b>
                                @unless ($estClasseEntiere)
                                    <i>{{ $item['enseignantNom'] ?? 'Non assigné' }}</i>
                                @endunless
                            </span>
                        @empty
                            <span class="table-empty-state" style="padding:0;">{{ $estMaternelle ? 'Aucun domaine' : 'Aucune matière' }} au programme de cette classe.</span>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="table-empty-state">Aucune classe pour cette année pour l'instant.</div>
            @endforelse
        </div>
    </section>
</div>

{{-- La création d'un examen n'a de sens que pour l'année active (voir
     ExamenController::store(), qui cible toujours l'année active quelle que
     soit l'origine du formulaire) — sur une année passée ou pas encore
     démarrée, cet onglet reste consultable (historique/à venir) mais sans
     bouton « Créer ». Voir aussi Académique > Examens (academique.examens.index)
     pour une vue filtrable toutes années confondues. --}}
<div data-tab-panel="examens" style="display:none;">
    <section class="config-section">
        <div class="config-section-head">
            <h2>Examens</h2>
            @if ($anneeAcademique->est_active)
                <div class="content-head-actions">
                    <button type="button" class="btn ghost" data-panel-open="new-examen">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                        Créer un examen
                    </button>
                </div>
            @endif
        </div>

        @unless ($anneeAcademique->est_active)
            <p class="hint">Cette année n'est pas active : ces examens sont affichés à titre indicatif. Pour créer un nouvel examen, démarrez d'abord cette année.</p>
        @endunless

        {{-- Taux de complétion des notes + moyenne de classe, un point par
             évaluation mensuelle — voir RapportService::statistiquesEvaluations().
             Maternelle exclue (pas de notes chiffrées) : si l'année n'a
             aucun examen non-maternelle pour l'instant, rien à tracer. --}}
        @if ($statistiquesEvaluations->isNotEmpty())
            <div class="chart-card" style="margin-bottom:18px;">
                <canvas id="chart-examens-statistiques" data-stats="{{ json_encode($statistiquesEvaluations) }}"></canvas>
            </div>
        @endif

        <x-data-table id="examens-annee-table">
            <x-slot:head>
                <th>Système</th>
                <th>Type</th>
                <th>Date de l'examen</th>
                <th>Date limite de saisie</th>
                <th></th>
            </x-slot:head>

            @forelse ($examens as $examen)
                <tr>
                    <td><span class="chip">{{ $examen->systeme->label() }}</span></td>
                    <td>{{ $examen->type->label() }}</td>
                    <td>{{ $examen->date_examen->format('d/m/Y') }}</td>
                    <td>{{ $examen->dateLimiteSaisieLibelle() }}</td>
                    <td>
                        <div class="row-actions-group">
                            <button
                                type="button"
                                class="row-edit"
                                title="Modifier"
                                data-panel-open="edit-examen"
                                data-edit-examen-trigger
                                data-edit-url="{{ route('academique.examens.update', $examen) }}"
                                data-edit-systeme="{{ $examen->systeme->label() }}"
                                data-edit-annee="{{ $anneeAcademique->libelle }}"
                                data-edit-date-examen="{{ $examen->date_examen->format('Y-m-d') }}"
                                data-edit-date-limite="{{ $examen->dateLimiteSaisiePourChamp() }}"
                                data-edit-min="{{ $anneeAcademique->date_debut->format('Y-m-d') }}"
                                data-edit-max="{{ $anneeAcademique->date_fin->format('Y-m-d') }}"
                            >✎</button>
                            <form method="POST" action="{{ route('academique.examens.destroy', $examen) }}"
                                  data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
                                  data-confirm-title="Supprimer cet examen"
                                  data-confirm-message="Supprimer cet examen supprimera aussi toutes les notes, commentaires et bulletins déjà saisis pour cet examen. Continuer ?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="row-delete" title="Supprimer">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="table-empty-state">Aucun examen pour cette année pour l'instant.</td>
                </tr>
            @endforelse
        </x-data-table>
    </section>
</div>

{{-- Pour l'année active, l'écran Bulletins (eleves/bulletins) fonctionne
     directement — c'est le seul cas qu'il reconnaît (voir
     BulletinGenerationController::index(), qui ne liste que les classes de
     l'année active, quel que soit le classe_id passé en paramètre). Pour une
     année passée, ce même écran serait donc trompeur ; on affiche ici, à la
     place, la moyenne/décision déjà calculées de chaque apprenant avec un
     lien direct vers l'aperçu/téléchargement de son bulletin annuel — ces
     routes-là n'ont aucune restriction d'année (voir Eleves\
     BulletinAnnuelGenerationController::apercu()/telechargerIndividuel()). --}}
<div data-tab-panel="bulletins" style="display:none;">
    <section class="config-section">
        <div class="config-section-head">
            <h2>Bulletins</h2>
        </div>

        @if ($anneeAcademique->est_active)
            <p class="hint">Cette année est active : gérez la génération des bulletins mensuels et annuels depuis l'écran Bulletins, classe par classe.</p>

            <div class="affectation-cards-grid">
                @forelse ($anneeAcademique->classes as $classe)
                    <div class="affectation-card">
                        <div class="affectation-card-head">
                            <div>
                                <h3>{{ $classe->nom }}</h3>
                                <span class="affectation-card-niveau">{{ $classe->niveau->libelle }}</span>
                            </div>
                        </div>
                        <a href="{{ route('eleves.bulletins.index', ['classe_id' => $classe->id]) }}" class="btn ghost">Gérer les bulletins →</a>
                    </div>
                @empty
                    <div class="table-empty-state">Aucune classe pour cette année pour l'instant.</div>
                @endforelse
            </div>
        @else
            <p class="hint">Cette année n'est pas active : la génération de bulletins n'est possible que pour l'année active. Voici, à titre indicatif, le bulletin annuel déjà calculé de chaque apprenant de cette année.</p>

            @forelse ($anneeAcademique->classes as $classe)
                <div class="config-section" style="margin-top:18px;">
                    <div class="config-section-head">
                        <h3 style="margin:0;">{{ $classe->nom }} <span class="affectation-card-niveau">{{ $classe->niveau->libelle }}</span></h3>
                    </div>

                    <x-data-table :id="'bulletins-annee-classe-'.$classe->id">
                        <x-slot:head>
                            <th>Apprenant</th>
                            <th>Moyenne annuelle</th>
                            <th>Décision</th>
                            <th></th>
                        </x-slot:head>

                        @forelse ($classe->inscriptions as $inscription)
                            <tr>
                                <td>{{ $inscription->eleve->nomComplet() }}</td>
                                <td>{{ $inscription->moyenne_annuelle !== null ? number_format($inscription->moyenne_annuelle, 2).'/20' : '—' }}</td>
                                <td>{{ $inscription->decision?->label() ?? '—' }}</td>
                                <td>
                                    <div class="row-actions-group">
                                        <a href="{{ route('eleves.bulletins.annuel.apercu', ['classe' => $classe, 'inscription' => $inscription]) }}" class="btn ghost" target="_blank" rel="noopener">Aperçu</a>
                                        <a href="{{ route('eleves.bulletins.annuel.apercu.telecharger', ['classe' => $classe, 'inscription' => $inscription]) }}" class="btn ghost" data-no-loader>Télécharger</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-empty-state">Aucun apprenant inscrit dans cette classe.</td>
                            </tr>
                        @endforelse
                    </x-data-table>
                </div>
            @empty
                <div class="table-empty-state">Aucune classe pour cette année pour l'instant.</div>
            @endforelse
        @endif
    </section>
</div>

{{-- Créer un examen : mêmes panneaux (mêmes IDs) que academique/examens/
     index.blade.php — resources/js/examens.js les pilote déjà sans
     modification, exactement comme decision-passage.js réutilisé sur
     eleves/bulletins/index.blade.php. --}}
<x-slide-panel id="new-examen" title="Créer un examen">
    <form method="POST" action="{{ route('academique.examens.store') }}" id="new-examen-form">
        @csrf
        <input type="hidden" name="_panel" value="new-examen">

        @error('systeme')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_examen')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_limite_saisie')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-examen-systeme">Système scolaire</label>
            <select class="role-select" id="new-examen-systeme" name="systeme" required>
                <option value="">— Sélectionner —</option>
                <option value="maternelle" @selected(old('systeme') === 'maternelle')>Maternelle</option>
                <option value="primaire" @selected(old('systeme', 'primaire') === 'primaire')>Primaire</option>
                <option value="secondaire" @selected(old('systeme') === 'secondaire')>Secondaire</option>
            </select>
        </div>

        <div id="new-examen-secondaire-hint" class="hint" style="display:none;">
            Le système secondaire est en cours de développement et n'est pas encore disponible. Choisir « Créer » ici affichera simplement un message d'indisponibilité, sans créer d'examen.
        </div>

        <div id="new-examen-primaire-fields">
            <div class="field">
                <label>Année académique</label>
                <div class="field-static">{{ $anneeAcademique->libelle }}</div>
                <div class="hint">L'examen est toujours créé pour l'année académique actuellement active.</div>
            </div>

            <div class="hint">
                Un examen mensuel unique sera créé, portant sur toutes les classes et tous les élèves du système
                choisi pour cette année académique — chaque élève étant évalué dans les matières de son programme.
            </div>

            <div class="field">
                <label for="new-examen-date">Date de l'examen</label>
                <input type="date" id="new-examen-date" name="date_examen" value="{{ old('date_examen') }}"
                    min="{{ $anneeAcademique->date_debut->format('Y-m-d') }}" max="{{ $anneeAcademique->date_fin->format('Y-m-d') }}">
            </div>

            <div class="field">
                <label for="new-examen-date-limite">Date et heure limites de saisie des notes</label>
                <input type="datetime-local" id="new-examen-date-limite" name="date_limite_saisie" value="{{ old('date_limite_saisie') }}"
                    min="{{ $anneeAcademique->date_debut->format('Y-m-d') }}T00:00" max="{{ $anneeAcademique->date_fin->format('Y-m-d') }}T23:59">
                <div class="hint">Délai laissé aux enseignants pour saisir les notes de cet examen.</div>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-examen">Annuler</button>
        <button type="submit" form="new-examen-form" class="btn dark">Créer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier un examen : seules les dates se modifient. --}}
<x-slide-panel id="edit-examen" title="Modifier l'examen">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-examen-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-examen">
        <input type="hidden" name="_edit_url" id="edit-examen-edit-url" value="{{ old('_edit_url') }}">

        @error('date_examen')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('date_limite_saisie')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label>Système / Année académique</label>
            <div class="field-static" id="edit-examen-systeme-annee">—</div>
        </div>

        <div class="field">
            <label for="edit-examen-date">Date de l'examen</label>
            <input type="date" id="edit-examen-date" name="date_examen" value="{{ old('date_examen') }}" required>
        </div>

        <div class="field">
            <label for="edit-examen-date-limite">Date et heure limites de saisie des notes</label>
            <input type="datetime-local" id="edit-examen-date-limite" name="date_limite_saisie" value="{{ old('date_limite_saisie') }}" required>
            <div class="hint">Délai laissé aux enseignants pour saisir les notes de cet examen.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-examen">Annuler</button>
        <button type="submit" form="edit-examen-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter des matières au programme d'un niveau : plusieurs à la fois,
     via la même liste "en attente" que l'étape 3 du wizard élève (voir
     initNiveauMatierePendingList() dans annee-academique-show.js). --}}
<x-slide-panel id="new-niveau-matiere" title="Ajouter des matières au programme">
    <form method="POST" action="{{ route('academique.annees.niveau-matieres.store', $anneeAcademique) }}" id="new-niveau-matiere-form">
        @csrf
        <input type="hidden" name="_panel" value="new-niveau-matiere">

        @error('niveau_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('matieres')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-niveau-matiere-niveau">Niveau</label>
            <select class="role-select" id="new-niveau-matiere-niveau" name="niveau_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected((string) old('niveau_id') === (string) $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-niveau-matiere-matiere">Matière</label>
            <select class="role-select" id="new-niveau-matiere-matiere">
                <option value="">— Sélectionner —</option>
                @foreach ($matieres as $matiere)
                    <option value="{{ $matiere->id }}" data-nom="{{ $matiere->nom }}">{{ $matiere->nom }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-niveau-matiere-coefficient">Coefficient</label>
            <input type="number" id="new-niveau-matiere-coefficient" step="0.5" min="0.5" max="20" value="1">
        </div>

        <button type="button" class="btn add-pending" id="new-niveau-matiere-add-btn">+ Ajouter à la liste</button>

        <div id="new-niveau-matiere-pending-section" style="display:none;">
            <div class="pending-list-title">Matières à ajouter (<span id="new-niveau-matiere-pending-count">0</span>)</div>
            <div id="new-niveau-matiere-pending-list"></div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-niveau-matiere">Annuler</button>
        <button type="submit" form="new-niveau-matiere-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter des domaines d'évaluation au programme d'un niveau de
     maternelle : sélection multiple directe (pas de coefficient à saisir,
     contrairement aux matières). --}}
<x-slide-panel id="new-niveau-domaine" title="Ajouter des domaines au programme">
    <form method="POST" action="{{ route('academique.annees.niveau-domaines.store', $anneeAcademique) }}" id="new-niveau-domaine-form">
        @csrf
        <input type="hidden" name="_panel" value="new-niveau-domaine">

        @error('niveau_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('domaines')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-niveau-domaine-niveau">Niveau (maternelle)</label>
            <select class="role-select" id="new-niveau-domaine-niveau" name="niveau_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($niveaux as $niveau)
                    <option value="{{ $niveau->id }}" @selected((string) old('niveau_id') === (string) $niveau->id)>{{ $niveau->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-niveau-domaine-domaines">Domaines (Ctrl/Cmd + clic pour en choisir plusieurs)</label>
            <select id="new-niveau-domaine-domaines" name="domaines[]" multiple size="8" style="width:100%; padding:8px; border:1px solid var(--line); border-radius:8px;">
                @foreach ($domaines as $domaine)
                    <option value="{{ $domaine->id }}">{{ $domaine->nom }}</option>
                @endforeach
            </select>
            <div class="hint">Aucun domaine ? Créez-en d'abord depuis « Niveaux &amp; matières ».</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-niveau-domaine">Annuler</button>
        <button type="submit" form="new-niveau-domaine-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier le coefficient d'une matière déjà au programme --}}
<x-slide-panel id="edit-niveau-matiere" title="Modifier le coefficient">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-niveau-matiere-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-niveau-matiere">
        <input type="hidden" name="_edit_url" id="edit-niveau-matiere-edit-url" value="{{ old('_edit_url') }}">

        @error('coefficient')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label>Matière</label>
            <div class="field-static" id="edit-niveau-matiere-label">—</div>
        </div>

        <div class="field">
            <label for="edit-niveau-matiere-coefficient">Coefficient</label>
            <input type="number" id="edit-niveau-matiere-coefficient" name="coefficient" step="0.5" min="0.5" max="20" value="{{ old('coefficient') }}" required>
            <div class="hint">Les classes déjà créées pour ce niveau et cette année sont mises à jour automatiquement.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-niveau-matiere">Annuler</button>
        <button type="submit" form="edit-niveau-matiere-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Ajouter une classe : la lettre choisie (une seule fois par niveau,
     cette année) est concaténée au libellé du niveau pour former le nom —
     voir initClasseLettreFilter() dans annee-academique-show.js. --}}
<x-slide-panel id="new-classe" title="Ajouter une classe">
    <form method="POST" action="{{ route('academique.annees.classes.store', $anneeAcademique) }}" id="new-classe-form">
        @csrf
        <input type="hidden" name="_panel" value="new-classe">

        @error('niveau_id')
            <div class="alert-error">{{ $message }}</div>
        @enderror
        @error('lettre')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label for="new-classe-niveau">Niveau</label>
            <select class="role-select" id="new-classe-niveau" name="niveau_id" required>
                <option value="">— Sélectionner —</option>
                @foreach ($niveaux as $niveau)
                    <option
                        value="{{ $niveau->id }}"
                        data-lettres-utilisees="{{ implode(',', $lettresParNiveau[$niveau->id] ?? []) }}"
                        @selected((string) old('niveau_id') === (string) $niveau->id)
                    >{{ $niveau->libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="new-classe-lettre">Lettre de la classe</label>
            <select class="role-select" id="new-classe-lettre" name="lettre" required>
                <option value="">— Sélectionnez d'abord un niveau —</option>
                @foreach ($lettresDisponibles as $lettre)
                    <option value="{{ $lettre }}" @selected(old('lettre') === $lettre)>{{ $lettre }}</option>
                @endforeach
            </select>
            <div class="hint">Le nom final est « Niveau + Lettre », ex : « CM1 A ». La classe hérite automatiquement du programme de matières défini ci-dessus pour ce niveau.</div>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="new-classe">Annuler</button>
        <button type="submit" form="new-classe-form" class="btn dark">Ajouter</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Modifier une classe : seule la lettre peut changer, le niveau reste fixe
     (voir ClasseController::update()) — le programme de matières est
     resynchronisé depuis le niveau à chaque enregistrement. --}}
<x-slide-panel id="edit-classe" title="Modifier la classe">
    <form method="POST" action="{{ old('_edit_url', '') }}" id="edit-classe-form">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_panel" value="edit-classe">
        <input type="hidden" name="_edit_url" id="edit-classe-edit-url" value="{{ old('_edit_url') }}">

        @error('lettre')
            <div class="alert-error">{{ $message }}</div>
        @enderror

        <div class="field">
            <label>Niveau</label>
            <div class="field-static" id="edit-classe-niveau-libelle">—</div>
            <div class="hint">Le niveau d'une classe ne se change pas après coup — supprimez-la et recréez-la dans le bon niveau si besoin.</div>
        </div>

        <div class="field">
            <label for="edit-classe-lettre">Lettre de la classe</label>
            <select class="role-select" id="edit-classe-lettre" name="lettre" required>
                @foreach ($lettresDisponibles as $lettre)
                    <option value="{{ $lettre }}" @selected(old('lettre') === $lettre)>{{ $lettre }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="edit-classe">Annuler</button>
        <button type="submit" form="edit-classe-form" class="btn dark">Enregistrer</button>
    </x-slot:footer>
</x-slide-panel>

{{-- Gérer l'affectation d'une classe : un seul panneau partagé, repeuplé par
     JS depuis les data-attributes du bouton « Gérer » de la ligne cliquée
     (voir initGererAffectationPanel() dans annee-academique-show.js) —
     inspiré de la section « Affectation des enseignants » de
     files/gestion-comptes_1.html. Liste les enseignants déjà affectés (avec
     un retrait immédiat par enseignant, toutes ses matières à la fois),
     permet d'en ajouter un nouveau avec une ou plusieurs matières en une
     seule action (US A.3), et de désigner le titulaire parmi les enseignants
     déjà affectés (US A.4). --}}
<x-slide-panel id="gerer-affectation" title="Affectation">
    <div class="pending-list-title">Enseignants affectés à cette classe</div>
    <div id="gerer-affectation-list" style="margin-bottom:22px;"></div>

    <div id="gerer-affectation-add-section">
        <div class="pending-list-title">Ajouter un enseignant à cette classe</div>
        <form method="POST" action="{{ route('academique.annees.affectations.store', $anneeAcademique) }}" id="gerer-affectation-add-form">
            @csrf
            <input type="hidden" name="_panel" value="gerer-affectation">
            <input type="hidden" name="classe_id" id="gerer-affectation-classe-id" value="{{ old('classe_id') }}">

            @error('classe_id')
                <div class="alert-error">{{ $message }}</div>
            @enderror
            @error('enseignant_id')
                <div class="alert-error">{{ $message }}</div>
            @enderror
            @error('matiere_ids')
                <div class="alert-error">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="gerer-affectation-enseignant">Enseignant</label>
                <select class="role-select" id="gerer-affectation-enseignant" name="enseignant_id" required>
                    <option value="">— Sélectionner —</option>
                    @foreach ($enseignants as $enseignant)
                        <option value="{{ $enseignant->id }}" @selected((string) old('enseignant_id') === (string) $enseignant->id)>{{ $enseignant->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" id="gerer-affectation-matieres-field">
                <label>Matière(s) enseignée(s) dans cette classe</label>
                <div id="gerer-affectation-matieres-check" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:6px;"></div>
                <div class="hint">L'enseignant sera affecté à chaque matière cochée en une seule fois.</div>
            </div>

            <div id="gerer-affectation-classe-entiere-hint" class="hint" style="display:none;">
                Maternelle/Primaire : cet enseignant sera affecté à <b>toutes les matières</b> de cette classe, avec
                les mêmes droits que les autres enseignants déjà en place. Le premier enseignant ajouté à une classe
                encore vide en devient automatiquement titulaire — désignez-en un autre ci-dessous si besoin.
            </div>

            <button type="submit" class="btn add-pending">+ Ajouter à la classe</button>
        </form>
    </div>

    <div id="gerer-affectation-titulaire-section" style="margin-top:26px; display:none;">
        <div class="pending-list-title">Titulaire de la classe</div>
        <div id="gerer-affectation-titulaire-entiere-note" class="hint" style="display:none;">
            Maternelle/Primaire : tous les enseignants affectés ont les mêmes droits sur toutes les matières — seul le titulaire peut valider le bulletin mensuel de la classe.
        </div>
        <form method="POST" id="gerer-affectation-titulaire-form">
            @csrf
            @method('PATCH')
            <div class="field">
                <select class="role-select" name="enseignant_id" id="gerer-affectation-titulaire-select"></select>
                <div class="hint">Seuls les enseignants déjà affectés à cette classe peuvent être désignés titulaire.</div>
            </div>
            <button type="submit" class="btn ghost">Désigner titulaire</button>
        </form>
    </div>

    <script type="application/json" id="gerer-affectation-matieres-map">{!! $matieres->pluck('nom', 'id')->toJson() !!}</script>
    <script type="application/json" id="gerer-affectation-old-matiere-ids">{!! json_encode(array_map('strval', (array) old('matiere_ids', []))) !!}</script>

    <x-slot:footer>
        <button type="button" class="btn ghost" data-panel-close="gerer-affectation">Fermer</button>
    </x-slot:footer>
</x-slide-panel>

@endsection
