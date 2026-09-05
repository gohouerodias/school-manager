<?php

namespace App\Http\Controllers\Enseignant;

use App\Enums\StatutBulletin;
use App\Enums\TypeEvaluation;
use App\Exports\NotesClasseExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulletinMensuelRequest;
use App\Http\Requests\StoreCommentaireMatiereRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\StoreNotesBatchRequest;
use App\Http\Requests\ValiderBulletinRequest;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\CommentaireMatiere;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The teacher-facing "espace enseignant" — a teacher's own classes and the
 * monthly note-entry sheet for each, built from
 * files/tableau-bord-enseignant_1.html (the provided mockup). Only teachers
 * actually affected to a classe (see AffectationEnseignant) can see/edit its
 * sheet, only the classe's titulaire can write the monthly bulletin
 * comment/rating, and edits are rejected past the examen's
 * `date_limite_saisie` — none of this is enforced client-side alone.
 */
class EspaceEnseignantController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

        $classes = collect();

        if ($anneeActive) {
            $classeIds = AffectationEnseignant::query()
                ->where('enseignant_id', $user->id)
                ->where('annee_academique_id', $anneeActive->id)
                ->pluck('classe_id')
                ->unique();

            $classes = Classe::query()
                ->whereIn('id', $classeIds)
                ->with(['niveau', 'inscriptions'])
                ->get()
                ->map(function (Classe $classe) use ($user, $anneeActive) {
                    $matieres = $classe->matieresPourEnseignant($user, $anneeActive);
                    $titulaire = $classe->titulairePour($anneeActive);
                    $examen = $this->examensPour($classe, $anneeActive)->first();

                    $classeMatiereIds = ClasseMatiere::query()
                        ->where('classe_id', $classe->id)
                        ->whereIn('matiere_id', $matieres->pluck('id'))
                        ->pluck('id');

                    $totalCellules = $classe->inscriptions->count() * $matieres->count();
                    $remplies = $examen
                        ? Note::query()->whereIn('classe_matiere_id', $classeMatiereIds)->where('examen_id', $examen->id)->count()
                        : 0;

                    return [
                        'classe' => $classe,
                        'matieres' => $matieres,
                        'estTitulaire' => $titulaire?->is($user) ?? false,
                        'titulaireNom' => $titulaire?->name,
                        'nbEleves' => $classe->inscriptions->count(),
                        'examen' => $examen,
                        'totalCellules' => $totalCellules,
                        'remplies' => $remplies,
                    ];
                });
        }

        return view('enseignant.classes', [
            'classes' => $classes,
            'anneeActive' => $anneeActive,
        ]);
    }

    public function show(Request $request, Classe $classe): View
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);
        $this->assureAffectation($classe, $user, null, $anneeActive);

        $matieresEnseignant = $classe->matieresPourEnseignant($user, $anneeActive);
        $titulaire = $classe->titulairePour($anneeActive);
        $isTitulaire = $titulaire?->is($user) ?? false;

        // Le titulaire voit toutes les matières du programme de la classe
        // (pour suivre l'ensemble des résultats de ses apprenants), mais ne
        // peut modifier que celles qui lui sont affectées — voir
        // $matiereIdsEditables, utilisé par la vue pour verrouiller les
        // cases des autres matières, et par assureAffectation() côté
        // sauvegarde qui refuse de toute façon toute écriture hors
        // affectation.
        $matieres = $isTitulaire ? $classe->matieres()->orderBy('nom')->get() : $matieresEnseignant;
        $matiereIdsEditables = $matieresEnseignant->pluck('id');

        $examens = $this->examensPour($classe, $anneeActive);
        $examenId = (int) $request->query('examen_id', (string) $examens->first()?->id);
        $examenActif = $examens->firstWhere('id', $examenId) ?? $examens->first();

        $studentsPayload = $this->studentsPayloadPour($classe, $matieres, $examenActif);

        return view('enseignant.saisie-notes', [
            'classe' => $classe,
            'matieres' => $matieres,
            'matiereIdsEditables' => $matiereIdsEditables,
            'titulaire' => $titulaire,
            'isTitulaire' => $isTitulaire,
            'examens' => $examens,
            'examenActif' => $examenActif,
            'students' => $studentsPayload,
            'saisieFermee' => $this->saisieEstFermee($examenActif),
        ]);
    }

    /**
     * US B.4 — exporte en Excel la feuille de saisie telle qu'affichée à
     * l'écran (mêmes matières visibles pour cet enseignant, même examen/
     * période sélectionné).
     */
    public function exportNotes(Request $request, Classe $classe): Response
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);
        $this->assureAffectation($classe, $user, null, $anneeActive);

        $titulaire = $classe->titulairePour($anneeActive);
        $isTitulaire = $titulaire?->is($user) ?? false;
        $matieres = $isTitulaire
            ? $classe->matieres()->orderBy('nom')->get()
            : $classe->matieresPourEnseignant($user, $anneeActive);

        $examens = $this->examensPour($classe, $anneeActive);
        $examenId = (int) $request->query('examen_id', (string) $examens->first()?->id);
        $examenActif = $examens->firstWhere('id', $examenId) ?? $examens->first();

        $studentsPayload = $this->studentsPayloadPour($classe, $matieres, $examenActif);

        $nomFichier = 'notes-'.Str::slug($classe->nom).($examenActif ? '-'.$examenActif->date_examen->format('Y-m') : '').'.xlsx';

        return Excel::download(
            new NotesClasseExport($matieres, $studentsPayload),
            $nomFichier
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function studentsPayloadPour(Classe $classe, Collection $matieres, ?Examen $examenActif): \Illuminate\Support\Collection
    {
        $classeMatieres = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->whereIn('matiere_id', $matieres->pluck('id'))
            ->get()
            ->keyBy('matiere_id');

        $inscriptions = $classe->inscriptions()->with('eleve')->get()
            ->sortBy(fn (Inscription $i) => $i->eleve->nom.$i->eleve->prenom)
            ->values();

        $notes = collect();
        $commentaires = collect();
        $bulletins = collect();

        if ($examenActif) {
            $classeMatiereIds = $classeMatieres->pluck('id');

            $notes = Note::query()
                ->whereIn('classe_matiere_id', $classeMatiereIds)
                ->where('examen_id', $examenActif->id)
                ->get()
                ->groupBy(fn (Note $n) => "{$n->eleve_id}-{$n->classe_matiere_id}");

            $commentaires = CommentaireMatiere::query()
                ->whereIn('classe_matiere_id', $classeMatiereIds)
                ->where('examen_id', $examenActif->id)
                ->get()
                ->groupBy(fn (CommentaireMatiere $c) => "{$c->eleve_id}-{$c->classe_matiere_id}");

            $bulletins = Bulletin::query()
                ->whereIn('inscription_id', $inscriptions->pluck('id'))
                ->where('examen_id', $examenActif->id)
                ->get()
                ->keyBy('inscription_id');
        }

        return $inscriptions->map(function (Inscription $inscription) use ($matieres, $classeMatieres, $notes, $commentaires, $bulletins) {
            $eleve = $inscription->eleve;
            $noteValeurs = [];
            $subjectComments = [];

            foreach ($matieres as $matiere) {
                $classeMatiere = $classeMatieres->get($matiere->id);
                $cle = "{$eleve->id}-{$classeMatiere?->id}";

                $noteValeurs[$matiere->id] = $notes->get($cle)?->first()?->valeur;

                $commentaire = $commentaires->get($cle)?->first()?->commentaire;
                if ($commentaire) {
                    $subjectComments[$matiere->id] = $commentaire;
                }
            }

            $bulletin = $bulletins->get($inscription->id);

            return [
                'inscriptionId' => $inscription->id,
                'eleveId' => $eleve->id,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'matricule' => $eleve->matricule,
                'notes' => $noteValeurs,
                'subjectComments' => $subjectComments,
                'bulletin' => $bulletin ? [
                    'resultat' => $bulletin->resultat_global?->value,
                    'appreciation' => $bulletin->appreciation,
                    'assiduite' => $bulletin->assiduite,
                    'conduite' => $bulletin->conduite,
                    'defautsMajeurs' => $bulletin->defauts_majeurs,
                    'qualites' => $bulletin->qualites,
                    'decisionPedagogique' => $bulletin->decision_pedagogique,
                    'statut' => $bulletin->statut->value,
                    'valideParNom' => $bulletin->valide_par_id ? $bulletin->valideParUtilisateur?->name : null,
                ] : null,
            ];
        });
    }

    public function saveNote(StoreNoteRequest $request, Classe $classe): JsonResponse
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);

        $matiereId = (int) $request->validated('matiere_id');
        $this->assureAffectation($classe, $user, $matiereId, $anneeActive);

        $examen = Examen::query()->where('id', $request->validated('examen_id'))->where('annee_academique_id', $anneeActive->id)->firstOrFail();
        $classeMatiere = ClasseMatiere::query()->where('classe_id', $classe->id)->where('matiere_id', $matiereId)->firstOrFail();
        $eleveId = (int) $request->validated('eleve_id');

        $inscription = Inscription::query()->where('classe_id', $classe->id)->where('eleve_id', $eleveId)->first();
        abort_unless($inscription, 404);

        if ($this->saisieEstFermee($examen)) {
            return response()->json(['message' => 'Le délai de saisie des notes pour cet examen est dépassé.'], 422);
        }

        if ($this->bulletinEstValide($inscription, $examen)) {
            return response()->json(['message' => 'Le bulletin de cet apprenant pour cette période est déjà validé — dévalidez-le pour modifier ses notes.'], 422);
        }

        $valeur = $request->validated('valeur');

        if ($valeur === null || $valeur === '') {
            Note::query()
                ->where('eleve_id', $eleveId)
                ->where('classe_matiere_id', $classeMatiere->id)
                ->where('examen_id', $examen->id)
                ->delete();

            return response()->json(['ok' => true, 'deleted' => true]);
        }

        $note = Note::query()->updateOrCreate(
            ['eleve_id' => $eleveId, 'classe_matiere_id' => $classeMatiere->id, 'examen_id' => $examen->id],
            [
                'enseignant_id' => $user->id,
                'valeur' => $valeur,
                'type' => TypeEvaluation::EvaluationMensuelle,
                'numero' => 1,
                'date_saisie' => now()->toDateString(),
            ]
        );

        return response()->json(['ok' => true, 'valeur' => $note->valeur]);
    }

    /**
     * Enregistre en un seul appel toutes les notes modifiées ou ajoutées
     * depuis la dernière sauvegarde (voir le bouton « Enregistrer les
     * modifications » de la feuille de saisie, resources/js/enseignant.js) —
     * une entrée ignorée silencieusement (matière non affectée à cet
     * enseignant, apprenant hors classe, ou bulletin déjà validé) ne fait pas
     * échouer les autres.
     */
    public function saveNotesBatch(StoreNotesBatchRequest $request, Classe $classe): JsonResponse
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);

        $examen = Examen::query()->where('id', $request->validated('examen_id'))->where('annee_academique_id', $anneeActive->id)->firstOrFail();

        if ($this->saisieEstFermee($examen)) {
            return response()->json(['message' => 'Le délai de saisie des notes pour cet examen est dépassé.'], 422);
        }

        $matiereIdsAffectes = AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('enseignant_id', $user->id)
            ->where('annee_academique_id', $anneeActive->id)
            ->pluck('matiere_id');

        $classeMatieresParMatiere = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->whereIn('matiere_id', $matiereIdsAffectes)
            ->get()
            ->keyBy('matiere_id');

        $enregistrees = 0;

        foreach ($request->validated('notes') as $entree) {
            $classeMatiere = $classeMatieresParMatiere->get((int) $entree['matiere_id']);

            if (! $classeMatiere) {
                continue;
            }

            $eleveId = (int) $entree['eleve_id'];
            $inscription = Inscription::query()->where('classe_id', $classe->id)->where('eleve_id', $eleveId)->first();

            if (! $inscription || $this->bulletinEstValide($inscription, $examen)) {
                continue;
            }

            $valeur = $entree['valeur'] ?? null;

            if ($valeur === null || $valeur === '') {
                Note::query()
                    ->where('eleve_id', $eleveId)
                    ->where('classe_matiere_id', $classeMatiere->id)
                    ->where('examen_id', $examen->id)
                    ->delete();
            } else {
                Note::query()->updateOrCreate(
                    ['eleve_id' => $eleveId, 'classe_matiere_id' => $classeMatiere->id, 'examen_id' => $examen->id],
                    [
                        'enseignant_id' => $user->id,
                        'valeur' => $valeur,
                        'type' => TypeEvaluation::EvaluationMensuelle,
                        'numero' => 1,
                        'date_saisie' => now()->toDateString(),
                    ]
                );
            }

            $enregistrees++;
        }

        return response()->json(['ok' => true, 'enregistrees' => $enregistrees]);
    }

    public function saveCommentaireMatiere(StoreCommentaireMatiereRequest $request, Classe $classe): JsonResponse
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);

        $matiereId = (int) $request->validated('matiere_id');
        $this->assureAffectation($classe, $user, $matiereId, $anneeActive);

        $examen = Examen::query()->where('id', $request->validated('examen_id'))->where('annee_academique_id', $anneeActive->id)->firstOrFail();
        $classeMatiere = ClasseMatiere::query()->where('classe_id', $classe->id)->where('matiere_id', $matiereId)->firstOrFail();
        $eleveId = (int) $request->validated('eleve_id');

        $inscription = Inscription::query()->where('classe_id', $classe->id)->where('eleve_id', $eleveId)->first();
        abort_unless($inscription, 404);

        if ($this->bulletinEstValide($inscription, $examen)) {
            return response()->json(['message' => 'Le bulletin de cet apprenant pour cette période est déjà validé — dévalidez-le pour modifier ses commentaires.'], 422);
        }

        $commentaire = $request->validated('commentaire');

        if (! $commentaire) {
            CommentaireMatiere::query()
                ->where('eleve_id', $eleveId)
                ->where('classe_matiere_id', $classeMatiere->id)
                ->where('examen_id', $examen->id)
                ->delete();

            return response()->json(['ok' => true, 'deleted' => true]);
        }

        CommentaireMatiere::query()->updateOrCreate(
            ['eleve_id' => $eleveId, 'classe_matiere_id' => $classeMatiere->id, 'examen_id' => $examen->id],
            ['enseignant_id' => $user->id, 'commentaire' => $commentaire]
        );

        return response()->json(['ok' => true]);
    }

    public function saveBulletin(StoreBulletinMensuelRequest $request, Classe $classe): JsonResponse
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);

        $titulaire = $classe->titulairePour($anneeActive);
        abort_unless($titulaire && $titulaire->is($user), 403, 'Seul le titulaire de la classe peut modifier le bulletin mensuel.');

        $examen = Examen::query()->where('id', $request->validated('examen_id'))->where('annee_academique_id', $anneeActive->id)->firstOrFail();
        $eleveId = (int) $request->validated('eleve_id');
        $inscription = Inscription::query()->where('classe_id', $classe->id)->where('eleve_id', $eleveId)->firstOrFail();

        if ($this->bulletinEstValide($inscription, $examen)) {
            return response()->json(['message' => 'Ce bulletin est déjà validé — dévalidez-le pour le modifier.'], 422);
        }

        Bulletin::query()->updateOrCreate(
            ['inscription_id' => $inscription->id, 'examen_id' => $examen->id],
            [
                'resultat_global' => $request->validated('resultat_global'),
                'appreciation' => $request->validated('appreciation'),
                'assiduite' => $request->validated('assiduite'),
                'conduite' => $request->validated('conduite'),
                'defauts_majeurs' => $request->validated('defauts_majeurs'),
                'qualites' => $request->validated('qualites'),
                'decision_pedagogique' => $request->validated('decision_pedagogique'),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * "Valider et signer le bulletin" (US C.2) — verrouille les notes et
     * commentaires de la période pour tous les enseignants jusqu'à une
     * éventuelle dévalidation. Réservé au titulaire ; nécessite qu'un
     * brouillon (appréciation/résultat) existe déjà.
     */
    public function validerBulletin(ValiderBulletinRequest $request, Classe $classe): JsonResponse
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);

        $titulaire = $classe->titulairePour($anneeActive);
        abort_unless($titulaire && $titulaire->is($user), 403, 'Seul le titulaire de la classe peut valider le bulletin mensuel.');

        $examen = Examen::query()->where('id', $request->validated('examen_id'))->where('annee_academique_id', $anneeActive->id)->firstOrFail();
        $eleveId = (int) $request->validated('eleve_id');
        $inscription = Inscription::query()->where('classe_id', $classe->id)->where('eleve_id', $eleveId)->firstOrFail();

        $bulletin = Bulletin::query()->where('inscription_id', $inscription->id)->where('examen_id', $examen->id)->first();

        if (! $bulletin) {
            return response()->json(['message' => "Renseignez d'abord le commentaire général et l'appréciation avant de valider."], 422);
        }

        if (! $this->notesCompletesPour($classe, $eleveId, $examen)) {
            return response()->json(['message' => "Impossible de valider : toutes les matières du programme n'ont pas encore été notées pour cet apprenant ce mois-ci."], 422);
        }

        $bulletin->update([
            'statut' => StatutBulletin::Valide,
            'valide_par_id' => $user->id,
            'valide_at' => now(),
        ]);

        return response()->json(['ok' => true, 'statut' => $bulletin->statut->value]);
    }

    /**
     * "Dévalider" un bulletin déjà signé (US C.3) — redonne la main aux
     * enseignants sur les notes/commentaires de la période. Réservé au
     * titulaire.
     */
    public function devaliderBulletin(ValiderBulletinRequest $request, Classe $classe): JsonResponse
    {
        $user = $request->user();
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
        abort_unless($anneeActive && $classe->annee_academique_id === $anneeActive->id, 404);

        $titulaire = $classe->titulairePour($anneeActive);
        abort_unless($titulaire && $titulaire->is($user), 403, 'Seul le titulaire de la classe peut dévalider le bulletin mensuel.');

        $examen = Examen::query()->where('id', $request->validated('examen_id'))->where('annee_academique_id', $anneeActive->id)->firstOrFail();
        $eleveId = (int) $request->validated('eleve_id');
        $inscription = Inscription::query()->where('classe_id', $classe->id)->where('eleve_id', $eleveId)->firstOrFail();

        $bulletin = Bulletin::query()->where('inscription_id', $inscription->id)->where('examen_id', $examen->id)->first();

        if ($bulletin) {
            $bulletin->update(['statut' => StatutBulletin::Brouillon, 'valide_par_id' => null, 'valide_at' => null]);
        }

        return response()->json(['ok' => true, 'statut' => StatutBulletin::Brouillon->value]);
    }

    /**
     * @return bool Whether $examen's `date_limite_saisie` has passed — once
     *              closed, the sheet becomes read-only for every enseignant
     *              (see the "Délai de saisie dépassé" lock in saisie-notes.
     *              blade.php).
     */
    private function saisieEstFermee(?Examen $examen): bool
    {
        return $examen !== null && now()->toDateString() > $examen->date_limite_saisie->format('Y-m-d');
    }

    /**
     * @return bool Whether $inscription's bulletin for $examen is already
     *              Validé (see StatutBulletin) — the note/commentaire lock.
     */
    private function bulletinEstValide(Inscription $inscription, Examen $examen): bool
    {
        return Bulletin::query()
            ->where('inscription_id', $inscription->id)
            ->where('examen_id', $examen->id)
            ->where('statut', StatutBulletin::Valide)
            ->exists();
    }

    /**
     * @return bool Whether every matière of $classe's programme has a Note
     *              recorded for $eleveId on $examen — the moyenne mensuelle
     *              n'a de sens (et n'est affichée au titulaire) qu'une fois
     *              ceci vrai, et validerBulletin() refuse de signer tant que
     *              ce n'est pas le cas (US C.2).
     */
    private function notesCompletesPour(Classe $classe, int $eleveId, Examen $examen): bool
    {
        $classeMatiereIds = ClasseMatiere::query()->where('classe_id', $classe->id)->pluck('id');

        if ($classeMatiereIds->isEmpty()) {
            return false;
        }

        $notesRenseignees = Note::query()
            ->where('eleve_id', $eleveId)
            ->where('examen_id', $examen->id)
            ->whereIn('classe_matiere_id', $classeMatiereIds)
            ->count();

        return $notesRenseignees === $classeMatiereIds->count();
    }

    /**
     * Aborts with 403 unless $user holds an AffectationEnseignant for this
     * classe (and, when given, this specific matière) for $anneeActive.
     */
    private function assureAffectation(Classe $classe, User $user, ?int $matiereId, AnneeAcademique $anneeActive): void
    {
        $query = AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('enseignant_id', $user->id)
            ->where('annee_academique_id', $anneeActive->id);

        if ($matiereId) {
            $query->where('matiere_id', $matiereId);
        }

        abort_unless($query->exists(), 403, $matiereId
            ? "Vous n'êtes pas affecté à cette matière pour cette classe."
            : "Vous n'êtes pas affecté à cette classe.");
    }

    /**
     * Examens dont le système correspond au cycle du niveau de la classe,
     * les plus récents en premier — les seules "périodes" sélectionnables
     * dans la feuille de saisie (voir Examen::pourClasse()).
     *
     * @return Collection<int, Examen>
     */
    private function examensPour(Classe $classe, AnneeAcademique $anneeActive): Collection
    {
        return Examen::pourClasse($classe);
    }
}
