<?php

namespace App\Services;

use App\Enums\DecisionAnnuelle;
use App\Enums\StatutBulletin;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\ClasseMatiere;
use App\Models\DemandeGenerationBulletin;
use App\Models\DemandeGenerationBulletinAnnuel;
use App\Models\EvaluationDomaine;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\MoyenneAnnuelleMatiere;
use App\Models\Note;
use App\Models\ParametreSysteme;
use App\Support\ZipWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Building blocks for the "Bulletins" screen (Écran admin, US Rapports/
 * Dossiers — voir files/bulletin.html) : le suivi des signatures d'une
 * classe pour un examen mensuel donné, et la génération effective des
 * bulletins (moyenne + rang) une fois que la classe est prête. La
 * génération elle-même se lance immédiatement en arrière-plan (voir
 * App\Jobs\GenererBulletinsClasseJob, dispatché sur la file d'attente) —
 * genererPourClasse() met à jour la progression sur la
 * DemandeGenerationBulletin fournie au fil du traitement, afin que l'écran
 * puisse l'afficher en direct (polling, voir
 * Eleves\BulletinGenerationController::statut()).
 */
class BulletinGenerationService
{
    /**
     * @return array{
     *     lignes: Collection<int, array<string, mixed>>,
     *     total: int,
     *     signedCount: int,
     *     moyennesEnAttenteCount: int,
     *     pct: int,
     *     plusForte: ?float,
     *     plusFaible: ?float,
     * }
     */
    public function payloadPourClasse(Classe $classe, Examen $examen): array
    {
        $inscriptions = $classe->inscriptions()->with('eleve')->get()
            ->sortBy(fn (Inscription $i) => $i->eleve->nom.$i->eleve->prenom)
            ->values();

        $bulletins = Bulletin::query()
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->where('examen_id', $examen->id)
            ->get()
            ->keyBy('inscription_id');

        $estMaternelle = $classe->estMaternelle();

        $lignes = $inscriptions->map(function (Inscription $inscription) use ($bulletins, $classe, $examen, $estMaternelle) {
            $bulletin = $bulletins->get($inscription->id);
            $notesCompletes = $estMaternelle
                ? $classe->domainesCompletesPour($inscription->eleve_id, $examen)
                : $classe->notesCompletesPour($inscription->eleve_id, $examen);

            // La moyenne n'est calculée qu'une fois toutes les notes du
            // programme renseignées (voir Classe::notesCompletesPour()) —
            // pas besoin qu'un Bulletin existe déjà en base : on utilise un
            // Bulletin non sauvegardé le temps du calcul (calculerMoyenne()
            // ne lit que la relation inscription + l'examen), avec la
            // relation déjà en mémoire pour éviter une requête N+1. La
            // maternelle n'a ni moyenne ni rang (grille qualitative uniquement).
            $moyenne = null;
            if ($notesCompletes && ! $estMaternelle) {
                $bulletinPourCalcul = ($bulletin ?? new Bulletin(['examen_id' => $examen->id]))
                    ->setRelation('inscription', $inscription);
                $moyenne = $bulletinPourCalcul->calculerMoyenne();
            }

            return [
                'inscription' => $inscription,
                'bulletin' => $bulletin,
                'signed' => $bulletin?->estValide() ?? false,
                'moyenneEnAttente' => ! $notesCompletes,
                'moyenne' => $moyenne,
            ];
        });

        if ($estMaternelle) {
            $lignes = $lignes->map(function (array $ligne) {
                $ligne['rang'] = null;
                $ligne['totalClasse'] = 0;

                return $ligne;
            });

            $signedCount = $lignes->filter(fn (array $ligne) => $ligne['signed'])->count();
            $total = $lignes->count();
            $moyennesEnAttenteCount = $lignes->filter(fn (array $ligne) => $ligne['moyenneEnAttente'])->count();

            return [
                'lignes' => $lignes,
                'total' => $total,
                'signedCount' => $signedCount,
                'moyennesEnAttenteCount' => $moyennesEnAttenteCount,
                'pct' => $total > 0 ? (int) round($signedCount / $total * 100) : 0,
                'plusForte' => null,
                'plusFaible' => null,
            ];
        }

        $classement = $lignes
            ->filter(fn (array $ligne) => $ligne['moyenne'] !== null)
            ->sortByDesc('moyenne')
            ->values();

        $rangParInscription = [];
        foreach ($classement as $position => $ligne) {
            $rangParInscription[$ligne['inscription']->id] = $position + 1;
        }

        $lignes = $lignes->map(function (array $ligne) use ($rangParInscription, $classement) {
            $ligne['rang'] = $rangParInscription[$ligne['inscription']->id] ?? null;
            $ligne['totalClasse'] = $classement->count();

            return $ligne;
        });

        $moyennes = $classement->pluck('moyenne');
        $signedCount = $lignes->filter(fn (array $ligne) => $ligne['signed'])->count();
        $total = $lignes->count();
        $moyennesEnAttenteCount = $lignes->filter(fn (array $ligne) => $ligne['moyenneEnAttente'])->count();

        return [
            'lignes' => $lignes,
            'total' => $total,
            'signedCount' => $signedCount,
            'moyennesEnAttenteCount' => $moyennesEnAttenteCount,
            'pct' => $total > 0 ? (int) round($signedCount / $total * 100) : 0,
            'plusForte' => $moyennes->isNotEmpty() ? $moyennes->max() : null,
            'plusFaible' => $moyennes->isNotEmpty() ? $moyennes->min() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function papierPourInscription(Classe $classe, Examen $examen, Inscription $inscription): array
    {
        $payload = $this->payloadPourClasse($classe, $examen);
        $ligne = $payload['lignes']->firstWhere(fn (array $l) => $l['inscription']->is($inscription));

        abort_unless($ligne, 404);

        $fiche = [
            'classe' => $classe,
            'examen' => $examen,
            'inscription' => $inscription,
            'eleve' => $inscription->eleve,
            'bulletin' => $ligne['bulletin'],
            'moyenne' => $ligne['moyenne'],
            'rang' => $ligne['rang'],
            'totalClasse' => $ligne['totalClasse'],
            'plusForte' => $payload['plusForte'],
            'plusFaible' => $payload['plusFaible'],
        ];

        if ($classe->estMaternelle()) {
            $fiche['domaines'] = $this->domainesAvecEvaluations($classe, $examen, $inscription);
        } else {
            $fiche['matieres'] = $this->matieresAvecNotes($classe, $examen, $inscription);
        }

        return $fiche;
    }

    /**
     * Génère à la volée le PDF d'un seul bulletin, sans rien figer en base
     * (contrairement à genererPourClasse()) — utilisé pour le téléchargement
     * individuel depuis l'écran d'aperçu (voir
     * Eleves\BulletinGenerationController::telechargerIndividuel()), à
     * n'importe quel moment, même avant la génération groupée de la classe.
     *
     * @return array{chemin: string, nomFichier: string}
     */
    public function pdfIndividuel(Classe $classe, Examen $examen, Inscription $inscription): array
    {
        $fiche = $this->papierPourInscription($classe, $examen, $inscription);

        return [
            'chemin' => $this->rendrePdfFiche($fiche),
            'nomFichier' => Str::slug($fiche['eleve']->nomComplet()).'.pdf',
        ];
    }

    /**
     * Rend le PDF d'une fiche (même gabarit que l'aperçu écran, voir
     * eleves/bulletins/_papier.blade.php) sur un chemin temporaire — appelé
     * aussi bien par pdfIndividuel() que par genererPourClasse() pour chaque
     * entrée du ZIP produit.
     *
     * @param  array<string, mixed>  $fiche
     */
    private function rendrePdfFiche(array $fiche): string
    {
        Storage::makeDirectory('bulletins/tmp');

        $chemin = 'bulletins/tmp/'.Str::random(16).'.pdf';

        Pdf::loadView('eleves.bulletins.pdf', $fiche)->save(Storage::path($chemin));

        return $chemin;
    }

    /**
     * @return Collection<int, array{nom: string, note: ?float}>
     */
    private function matieresAvecNotes(Classe $classe, Examen $examen, Inscription $inscription): Collection
    {
        $classeMatieres = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->with('matiere')
            ->get();

        $notes = Note::query()
            ->whereIn('classe_matiere_id', $classeMatieres->pluck('id'))
            ->where('examen_id', $examen->id)
            ->where('eleve_id', $inscription->eleve_id)
            ->get()
            ->keyBy('classe_matiere_id');

        return $classeMatieres
            ->sortBy(fn (ClasseMatiere $cm) => $cm->matiere->nom)
            ->map(fn (ClasseMatiere $cm) => [
                'nom' => $cm->matiere->nom,
                'note' => $notes->get($cm->id)?->valeur,
                // Primaire : détail critère minimal (/18) + perfectionnement (/2).
                'critere_minimal' => $notes->get($cm->id)?->critere_minimal,
                'critere_perfectionnement' => $notes->get($cm->id)?->critere_perfectionnement,
            ])
            ->values();
    }

    /**
     * Équivalent maternelle de matieresAvecNotes() : une ligne par domaine
     * d'évaluation au programme de la classe, avec la valeur qualitative
     * (NiveauQualitatif) et l'observation éventuelle de l'enseignant pour
     * cet apprenant et cet examen mensuel.
     *
     * @return Collection<int, array{nom: string, valeur: ?string, observation: ?string}>
     */
    private function domainesAvecEvaluations(Classe $classe, Examen $examen, Inscription $inscription): Collection
    {
        $classeDomaines = ClasseDomaine::query()
            ->where('classe_id', $classe->id)
            ->with('domaineEvaluation')
            ->get();

        $evaluations = EvaluationDomaine::query()
            ->whereIn('classe_domaine_id', $classeDomaines->pluck('id'))
            ->where('examen_id', $examen->id)
            ->where('eleve_id', $inscription->eleve_id)
            ->get()
            ->keyBy('classe_domaine_id');

        return $classeDomaines
            ->sortBy(fn (ClasseDomaine $cd) => $cd->domaineEvaluation->nom)
            ->map(fn (ClasseDomaine $cd) => [
                'nom' => $cd->domaineEvaluation->nom,
                'valeur' => $evaluations->get($cd->id)?->valeur?->value,
                'observation' => $evaluations->get($cd->id)?->observation,
            ])
            ->values();
    }

    /**
     * Génère (ou régénère) les bulletins de toute la classe pour cet
     * examen : fige moyenne_generale/rang/date_generation sur chaque
     * apprenant dont la moyenne est prête (voir Classe::notesCompletesPour()
     * — la signature du titulaire n'est plus une condition), en créant le
     * Bulletin s'il n'existait pas encore, produit le PDF individuel de
     * chacun, puis les regroupe dans une unique archive ZIP (un fichier par
     * apprenant, facile à redistribuer un par un) stockée sur le disque
     * `local`. Traite les apprenants un par un et incrémente
     * `$demande->traites` à chaque étape (si fourni) — c'est ce qui permet
     * à l'écran Bulletins d'afficher une progression en temps réel pendant
     * que App\Jobs\GenererBulletinsClasseJob tourne sur la file d'attente,
     * sans jamais bloquer une requête HTTP.
     *
     * N'est jamais appelée si au moins une moyenne de la classe est encore
     * en attente — voir BulletinGenerationController::demanderGeneration(),
     * qui revérifie ce critère avant de dispatcher le job.
     *
     * @return array{count: int, path: ?string}
     */
    public function genererPourClasse(Classe $classe, Examen $examen, ?DemandeGenerationBulletin $demande = null): array
    {
        $estMaternelle = $classe->estMaternelle();
        $payload = $this->payloadPourClasse($classe, $examen);
        $lignesPretes = $payload['lignes']->filter(fn (array $l) => ! $l['moyenneEnAttente'])->values();

        $demande?->update(['total' => $lignesPretes->count(), 'traites' => 0]);

        $entrees = [];

        foreach ($lignesPretes as $ligne) {
            $inscription = $ligne['inscription'];

            $bulletin = Bulletin::query()->updateOrCreate(
                ['inscription_id' => $inscription->id, 'examen_id' => $examen->id],
                [
                    'moyenne_generale' => $ligne['moyenne'],
                    'rang' => $ligne['rang'],
                    'date_generation' => now()->toDateString(),
                ]
            );

            $fiche = [
                'classe' => $classe,
                'examen' => $examen,
                'inscription' => $inscription,
                'eleve' => $inscription->eleve,
                'bulletin' => $bulletin,
                'moyenne' => $ligne['moyenne'],
                'rang' => $ligne['rang'],
                'totalClasse' => $ligne['totalClasse'],
                'plusForte' => $payload['plusForte'],
                'plusFaible' => $payload['plusFaible'],
            ];

            $fiche[$estMaternelle ? 'domaines' : 'matieres'] = $estMaternelle
                ? $this->domainesAvecEvaluations($classe, $examen, $inscription)
                : $this->matieresAvecNotes($classe, $examen, $inscription);

            $entrees[] = [
                'nom' => Str::slug($inscription->eleve->nomComplet().'-'.$inscription->eleve->matricule).'.pdf',
                'chemin' => $this->rendrePdfFiche($fiche),
            ];

            $demande?->increment('traites');
        }

        if ($entrees === []) {
            return ['count' => 0, 'path' => null];
        }

        $cheminZip = $this->zipperEntrees($classe, $examen, $entrees);

        return ['count' => count($entrees), 'path' => $cheminZip];
    }

    /**
     * Regroupe les PDF individuels déjà rendus (voir rendrePdfFiche()) dans
     * une archive ZIP, puis supprime les fichiers temporaires — l'archive
     * seule est conservée (DemandeGenerationBulletin::chemin_pdf). Utilise
     * App\Support\ZipWriter (implémentation maison, sans dépendre de la
     * classe ZipArchive/extension `zip`) pour que la génération fonctionne
     * quelle que soit la configuration PHP du serveur.
     *
     * @param  array<int, array{nom: string, chemin: string}>  $entrees
     */
    private function zipperEntrees(Classe $classe, Examen $examen, array $entrees): string
    {
        Storage::makeDirectory('bulletins');

        $cheminZip = 'bulletins/'.$classe->id.'-'.$examen->id.'-'.Str::random(8).'.zip';

        $zip = new ZipWriter;

        foreach ($entrees as $entree) {
            $zip->ajouterFichier($entree['nom'], Storage::get($entree['chemin']));
        }

        $zip->enregistrer(Storage::path($cheminZip));

        foreach ($entrees as $entree) {
            Storage::delete($entree['chemin']);
        }

        return $cheminZip;
    }

    /**
     * Équivalent annuel de payloadPourClasse() : plus de notion d'examen —
     * porte sur toute l'année académique de la classe. Le bulletin annuel
     * n'est proposé qu'une fois que l'admin a créé au moins
     * `nombre_evaluations_prevues` Examen pour le cycle de cette classe (voir
     * Examen::pourClasse()) ; `seuilAtteint` reflète ce garde-fou et doit être
     * revérifié côté contrôleur avant toute génération, comme
     * `moyennesEnAttenteCount` pour le mensuel.
     *
     * Primaire/collège : la moyenne annuelle de chaque apprenant réutilise
     * Inscription::calculerMoyenneAnnuelle() (moyenne simple de ses bulletins
     * mensuels déjà Validés) — la même valeur que l'écran Décisions de
     * passage (voir Academique\DecisionPassageController), pour que les deux
     * écrans restent cohérents entre eux. Un apprenant sans aucun bulletin
     * mensuel Validé n'a pas de moyenne annuelle exploitable : sa moyenne
     * reste `null` (`moyenneEnAttente` à true) plutôt que le 0.0 que
     * calculerMoyenneAnnuelle() renverrait techniquement, pour ne pas fausser
     * le rang ni bloquer silencieusement sur une donnée creuse.
     *
     * Maternelle : pas de moyenne chiffrée possible (grille qualitative) —
     * `moyenne`/`rang` restent `null`, aucun apprenant n'est en attente
     * (`moyenneEnAttente` toujours false : un récapitulatif TS/S/PS annuel
     * reste valide même à zéro occurrence d'une valeur). Pas de `proposition`
     * non plus (absente de la ligne, pas juste `null`).
     *
     * Chaque ligne primaire/collège porte aussi `proposition` (Admis/
     * Redouble selon `ParametreSysteme::seuil_passage`, `null` si `moyenne`
     * l'est) — permet d'enregistrer la décision finale directement depuis
     * l'écran Bulletins (voir eleves/bulletins/index.blade.php), en plus de
     * l'écran dédié Décisions de passage.
     *
     * @return array{
     *     lignes: Collection<int, array<string, mixed>>,
     *     total: int,
     *     moyennesEnAttenteCount: int,
     *     plusForte: ?float,
     *     plusFaible: ?float,
     *     seuilAtteint: bool,
     *     nombreEvaluationsRequis: int,
     *     nombreEvaluationsCreees: int,
     * }
     */
    public function payloadAnnuelPourClasse(Classe $classe): array
    {
        $classe->loadMissing('anneeAcademique');

        $nombreEvaluationsCreees = Examen::pourClasse($classe)->count();
        $nombreEvaluationsRequis = $classe->anneeAcademique->nombre_evaluations_prevues;
        $seuilAtteint = $nombreEvaluationsCreees >= $nombreEvaluationsRequis;

        $inscriptions = $classe->inscriptions()->with('eleve')->get()
            ->sortBy(fn (Inscription $i) => $i->eleve->nom.$i->eleve->prenom)
            ->values();

        $estMaternelle = $classe->estMaternelle();

        // Même seuil/proposition automatique que Academique\
        // DecisionPassageController::index() — affichée ici aussi (voir
        // eleves/bulletins/index.blade.php) pour permettre d'enregistrer la
        // décision finale directement depuis l'écran Bulletins, sans devoir
        // passer par Décisions de passage.
        $seuil = ParametreSysteme::query()->value('seuil_passage') ?? 10;

        $lignes = $inscriptions->map(function (Inscription $inscription) use ($estMaternelle, $seuil) {
            if ($estMaternelle) {
                return ['inscription' => $inscription, 'moyenne' => null, 'moyenneEnAttente' => false];
            }

            $auMoinsUnBulletinValide = $inscription->bulletins()->where('statut', StatutBulletin::Valide)->exists();
            $moyenne = $auMoinsUnBulletinValide ? $inscription->calculerMoyenneAnnuelle() : null;

            return [
                'inscription' => $inscription,
                'moyenne' => $moyenne,
                'moyenneEnAttente' => ! $auMoinsUnBulletinValide,
                'proposition' => $moyenne !== null
                    ? ($moyenne >= $seuil ? DecisionAnnuelle::Admis : DecisionAnnuelle::Redouble)
                    : null,
            ];
        });

        if ($estMaternelle) {
            $lignes = $lignes->map(function (array $ligne) {
                $ligne['rang'] = null;
                $ligne['totalClasse'] = 0;

                return $ligne;
            });

            return [
                'lignes' => $lignes,
                'total' => $lignes->count(),
                'moyennesEnAttenteCount' => 0,
                'plusForte' => null,
                'plusFaible' => null,
                'seuilAtteint' => $seuilAtteint,
                'nombreEvaluationsRequis' => $nombreEvaluationsRequis,
                'nombreEvaluationsCreees' => $nombreEvaluationsCreees,
            ];
        }

        $classement = $lignes
            ->filter(fn (array $ligne) => $ligne['moyenne'] !== null)
            ->sortByDesc('moyenne')
            ->values();

        $rangParInscription = [];
        foreach ($classement as $position => $ligne) {
            $rangParInscription[$ligne['inscription']->id] = $position + 1;
        }

        $lignes = $lignes->map(function (array $ligne) use ($rangParInscription, $classement) {
            $ligne['rang'] = $rangParInscription[$ligne['inscription']->id] ?? null;
            $ligne['totalClasse'] = $classement->count();

            return $ligne;
        });

        $moyennes = $classement->pluck('moyenne');

        return [
            'lignes' => $lignes,
            'total' => $lignes->count(),
            'moyennesEnAttenteCount' => $lignes->filter(fn (array $ligne) => $ligne['moyenneEnAttente'])->count(),
            'plusForte' => $moyennes->isNotEmpty() ? $moyennes->max() : null,
            'plusFaible' => $moyennes->isNotEmpty() ? $moyennes->min() : null,
            'seuilAtteint' => $seuilAtteint,
            'nombreEvaluationsRequis' => $nombreEvaluationsRequis,
            'nombreEvaluationsCreees' => $nombreEvaluationsCreees,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function papierAnnuelPourInscription(Classe $classe, Inscription $inscription): array
    {
        $payload = $this->payloadAnnuelPourClasse($classe);
        $ligne = $payload['lignes']->firstWhere(fn (array $l) => $l['inscription']->is($inscription));

        abort_unless($ligne, 404);

        $fiche = [
            'classe' => $classe,
            'anneeAcademique' => $classe->anneeAcademique,
            'inscription' => $inscription,
            'eleve' => $inscription->eleve,
            'moyenne' => $ligne['moyenne'],
            'rang' => $ligne['rang'],
            'totalClasse' => $ligne['totalClasse'],
            'plusForte' => $payload['plusForte'],
            'plusFaible' => $payload['plusFaible'],
            'decision' => $inscription->decision,
            'observationAnnuelle' => $inscription->observation_annuelle,
        ];

        if ($classe->estMaternelle()) {
            $fiche['domaines'] = $this->domainesRecapitulatifAnnuel($classe, $inscription);
        } else {
            $fiche['matieres'] = $this->matieresAnnuellesPourInscription($classe, $inscription);
        }

        return $fiche;
    }

    /**
     * Moyenne annuelle de chaque matière du programme de la classe, pour le
     * bulletin annuel — lue depuis les valeurs déjà persistées par
     * recalculerMoyennesAnnuellesPourClasse() (voir MoyenneAnnuelleMatiere) :
     * contrairement à Inscription::calculerMoyenneAnnuelle() (une simple
     * moyenne de bulletins déjà calculés), ce calcul par matière croise notes
     * × examens × matières et resterait trop coûteux à refaire en direct à
     * chaque affichage sur une grande classe. Une matière jamais recalculée
     * (bouton pas encore utilisé) apparaît avec `note` à `null`, comme une
     * matière sans note sur le bulletin mensuel.
     *
     * @return Collection<int, array{nom: string, note: ?float}>
     */
    private function matieresAnnuellesPourInscription(Classe $classe, Inscription $inscription): Collection
    {
        $classeMatieres = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->with('matiere')
            ->get();

        $moyennes = MoyenneAnnuelleMatiere::query()
            ->where('inscription_id', $inscription->id)
            ->whereIn('classe_matiere_id', $classeMatieres->pluck('id'))
            ->get()
            ->keyBy('classe_matiere_id');

        return $classeMatieres
            ->sortBy(fn (ClasseMatiere $cm) => $cm->matiere->nom)
            ->map(fn (ClasseMatiere $cm) => [
                'nom' => $cm->matiere->nom,
                'note' => $moyennes->get($cm->id)?->moyenne,
            ])
            ->values();
    }

    /**
     * Recalcule et persiste la moyenne annuelle générale
     * (Inscription::calculerMoyenneAnnuelle(), écrite dans
     * inscriptions.moyenne_annuelle) et la moyenne annuelle de chaque matière
     * (Inscription::moyennesAnnuellesParMatiere(), écrite dans
     * MoyenneAnnuelleMatiere) de chaque inscription de la classe — déclenché
     * manuellement par le bouton "Calculer les moyennes annuelles" (voir
     * Eleves\BulletinAnnuelGenerationController::recalculer() et
     * Academique\DecisionPassageController::recalculer(), qui appelle
     * recalculerMoyennesAnnuellesPourAnnee() ci-dessous pour toutes les
     * classes non-maternelle de l'année d'un coup). N'a pas de sens pour une
     * classe de maternelle (grille qualitative, pas de matières chiffrées) —
     * l'appelant doit filtrer en amont (voir estMaternelle()).
     */
    public function recalculerMoyennesAnnuellesPourClasse(Classe $classe): int
    {
        $inscriptions = $classe->inscriptions()->get();

        foreach ($inscriptions as $inscription) {
            // Un bulletin déjà Validé mais dont moyenne_generale n'a encore
            // jamais été calculée (avant le correctif de Enseignant\
            // EspaceEnseignantController::validerBulletin(), qui ne la
            // renseignait pas à la validation) reste `null` pour toujours,
            // sans que rien ne le recalcule jamais tout seul — avg() ignore
            // ces valeurs NULL en SQL, ce qui rendait la moyenne annuelle
            // vide/« — » même pour un mois réellement validé. On la
            // (re)calcule ici avant la moyenne annuelle, pour rattraper les
            // bulletins déjà validés avant ce correctif.
            $inscription->bulletins()
                ->where('statut', StatutBulletin::Valide)
                ->whereNull('moyenne_generale')
                ->get()
                ->each(function (Bulletin $bulletin) use ($inscription) {
                    $bulletin->setRelation('inscription', $inscription);
                    $bulletin->update(['moyenne_generale' => $bulletin->calculerMoyenne()]);
                });

            $inscription->update(['moyenne_annuelle' => $inscription->calculerMoyenneAnnuelle()]);

            foreach ($inscription->moyennesAnnuellesParMatiere() as $ligne) {
                MoyenneAnnuelleMatiere::query()->updateOrCreate(
                    ['inscription_id' => $inscription->id, 'classe_matiere_id' => $ligne['classe_matiere_id']],
                    ['moyenne' => $ligne['moyenne'], 'calculee_at' => now()],
                );
            }
        }

        return $inscriptions->count();
    }

    /**
     * Équivalent de recalculerMoyennesAnnuellesPourClasse() pour toutes les
     * classes non-maternelle d'une année académique d'un coup — utilisé par
     * l'écran Décisions de passage, qui porte sur toute l'année et non une
     * seule classe.
     */
    public function recalculerMoyennesAnnuellesPourAnnee(AnneeAcademique $anneeAcademique): int
    {
        return $anneeAcademique->classes()
            ->get()
            ->reject(fn (Classe $classe) => $classe->estMaternelle())
            ->sum(fn (Classe $classe) => $this->recalculerMoyennesAnnuellesPourClasse($classe));
    }

    /**
     * Génère à la volée le PDF annuel d'un seul bulletin, sans rien figer en
     * base — équivalent annuel de pdfIndividuel().
     *
     * @return array{chemin: string, nomFichier: string}
     */
    public function pdfAnnuelIndividuel(Classe $classe, Inscription $inscription): array
    {
        $fiche = $this->papierAnnuelPourInscription($classe, $inscription);

        return [
            'chemin' => $this->rendrePdfFicheAnnuelle($fiche),
            'nomFichier' => Str::slug($fiche['eleve']->nomComplet()).'-annuel.pdf',
        ];
    }

    /**
     * Équivalent maternelle de matieresAvecNotes()/domainesAvecEvaluations()
     * pour le bulletin annuel : pas de valeur unique par domaine, mais un
     * décompte du nombre de fois où l'apprenant a obtenu chaque niveau
     * (TS/S/PS) sur l'ensemble des examens de l'année (voir
     * Examen::pourClasse()) — voir le choix retenu pour le récapitulatif
     * annuel maternelle (grille papier "Nombre d'épreuves évaluées").
     *
     * @return Collection<int, array{nom: string, ts: int, s: int, ps: int}>
     */
    private function domainesRecapitulatifAnnuel(Classe $classe, Inscription $inscription): Collection
    {
        $classeDomaines = ClasseDomaine::query()
            ->where('classe_id', $classe->id)
            ->with('domaineEvaluation')
            ->get();

        $examenIds = Examen::pourClasse($classe)->pluck('id');

        $evaluations = EvaluationDomaine::query()
            ->whereIn('classe_domaine_id', $classeDomaines->pluck('id'))
            ->whereIn('examen_id', $examenIds)
            ->where('eleve_id', $inscription->eleve_id)
            ->get()
            ->groupBy('classe_domaine_id');

        return $classeDomaines
            ->sortBy(fn (ClasseDomaine $cd) => $cd->domaineEvaluation->nom)
            ->map(function (ClasseDomaine $cd) use ($evaluations) {
                $valeurs = $evaluations->get($cd->id, collect())->map(fn (EvaluationDomaine $e) => $e->valeur?->value);

                return [
                    'nom' => $cd->domaineEvaluation->nom,
                    'ts' => $valeurs->filter(fn (?string $v) => $v === 'ts')->count(),
                    's' => $valeurs->filter(fn (?string $v) => $v === 's')->count(),
                    'ps' => $valeurs->filter(fn (?string $v) => $v === 'ps')->count(),
                ];
            })
            ->values();
    }

    /**
     * Rend le PDF annuel d'une fiche sur un chemin temporaire — équivalent
     * annuel de rendrePdfFiche(), utilise le gabarit
     * eleves/bulletins/pdf-annuel.blade.php.
     *
     * @param  array<string, mixed>  $fiche
     */
    private function rendrePdfFicheAnnuelle(array $fiche): string
    {
        Storage::makeDirectory('bulletins-annuels/tmp');

        $chemin = 'bulletins-annuels/tmp/'.Str::random(16).'.pdf';

        Pdf::loadView('eleves.bulletins.pdf-annuel', $fiche)->save(Storage::path($chemin));

        return $chemin;
    }

    /**
     * Génère (ou régénère) les bulletins annuels de toute la classe —
     * équivalent annuel de genererPourClasse(). Ne fige rien en base (il n'y
     * a pas de "BulletinAnnuel" persisté : la moyenne annuelle est déjà
     * figée sur chaque Bulletin mensuel Validé, voir
     * Inscription::calculerMoyenneAnnuelle()) — chaque appel recalcule à
     * partir des données actuelles, donc « régénérer » reflète toujours
     * l'état le plus récent (nouvelles validations, décision de passage
     * changée...).
     *
     * Primaire/collège : n'inclut que les apprenants ayant au moins un
     * bulletin mensuel Validé (voir payloadAnnuelPourClasse()) — un
     * apprenant sans aucune moyenne mensuelle validée n'a rien à présenter
     * dans un bulletin annuel. Maternelle : tous les apprenants inscrits,
     * le récapitulatif TS/S/PS restant valide même à zéro occurrence.
     *
     * @return array{count: int, path: ?string}
     */
    public function genererAnnuelsPourClasse(Classe $classe, ?DemandeGenerationBulletinAnnuel $demande = null): array
    {
        $estMaternelle = $classe->estMaternelle();
        $payload = $this->payloadAnnuelPourClasse($classe);
        $lignesPretes = $estMaternelle
            ? $payload['lignes']
            : $payload['lignes']->filter(fn (array $l) => ! $l['moyenneEnAttente'])->values();

        $demande?->update(['total' => $lignesPretes->count(), 'traites' => 0]);

        $entrees = [];

        foreach ($lignesPretes as $ligne) {
            $inscription = $ligne['inscription'];

            $fiche = [
                'classe' => $classe,
                'anneeAcademique' => $classe->anneeAcademique,
                'inscription' => $inscription,
                'eleve' => $inscription->eleve,
                'moyenne' => $ligne['moyenne'],
                'rang' => $ligne['rang'],
                'totalClasse' => $ligne['totalClasse'],
                'plusForte' => $payload['plusForte'],
                'plusFaible' => $payload['plusFaible'],
                'decision' => $inscription->decision,
                'observationAnnuelle' => $inscription->observation_annuelle,
            ];

            if ($estMaternelle) {
                $fiche['domaines'] = $this->domainesRecapitulatifAnnuel($classe, $inscription);
            } else {
                $fiche['matieres'] = $this->matieresAnnuellesPourInscription($classe, $inscription);
            }

            $entrees[] = [
                'nom' => Str::slug($inscription->eleve->nomComplet().'-'.$inscription->eleve->matricule).'.pdf',
                'chemin' => $this->rendrePdfFicheAnnuelle($fiche),
            ];

            $demande?->increment('traites');
        }

        if ($entrees === []) {
            return ['count' => 0, 'path' => null];
        }

        $cheminZip = $this->zipperEntreesAnnuelles($classe, $entrees);

        return ['count' => count($entrees), 'path' => $cheminZip];
    }

    /**
     * Équivalent annuel de zipperEntrees() — mêmes garanties (App\Support\
     * ZipWriter, nettoyage des PDF temporaires), rangé dans son propre
     * dossier `bulletins-annuels` pour ne jamais se mélanger aux archives
     * mensuelles.
     *
     * @param  array<int, array{nom: string, chemin: string}>  $entrees
     */
    private function zipperEntreesAnnuelles(Classe $classe, array $entrees): string
    {
        Storage::makeDirectory('bulletins-annuels');

        $cheminZip = 'bulletins-annuels/'.$classe->id.'-'.Str::random(8).'.zip';

        $zip = new ZipWriter;

        foreach ($entrees as $entree) {
            $zip->ajouterFichier($entree['nom'], Storage::get($entree['chemin']));
        }

        $zip->enregistrer(Storage::path($cheminZip));

        foreach ($entrees as $entree) {
            Storage::delete($entree['chemin']);
        }

        return $cheminZip;
    }
}
