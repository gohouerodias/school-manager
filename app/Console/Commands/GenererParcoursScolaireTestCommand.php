<?php

namespace App\Console\Commands;

use App\Enums\CycleNiveau;
use App\Enums\StatutBulletin;
use App\Enums\StatutInscription;
use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\NiveauMatiere;
use App\Models\Note;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\BulletinGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Génère (ou supprime, via --supprimer) 2 apprenants de test réels et
 * interliés sur 2 années académiques, pour vérifier de bout en bout : la
 * frise du parcours scolaire (Eleves\EleveController::fiche()), le badge
 * "année en cours", et l'affichage d'un document justificatif sur une ligne
 * "Transféré entrant" (voir DocumentNumerique::inscription()).
 *
 * Contrairement à `demo:classe-primaire` (dont les 3 années sont toutes
 * `est_active = false` — insuffisant pour exercer le badge "année en cours"
 * et le document justificatif d'un transfert), cette commande RÉUTILISE la
 * vraie année académique active de l'établissement si elle existe déjà (elle
 * n'en crée et n'en active une que si aucune n'est encore active — donc
 * jamais au prix de désactiver la vraie année en cours). Elle y ajoute
 * seulement sa propre classe dédiée ("Vérif. parcours"), pour ne polluer
 * aucun effectif réel, et ne crée un nouvel Examen que si aucun n'existe
 * encore pour cette année (sinon elle réutilise le plus récent en lecture
 * seule, pour ne jamais risquer de créer une entrée fictive dans la liste
 * des examens réels).
 *
 * L'année précédente, elle, est toujours entièrement dédiée à ce test (même
 * logique de sécurité que `demo:classe-primaire`) : libellé jamais au format
 * réel "AAAA-AAAA" (voir StoreAnneeAcademiqueRequest), pour ne jamais entrer
 * en collision avec une vraie année et pour que --supprimer puisse
 * l'effacer entièrement sans le moindre risque.
 *
 * Idempotent au niveau de la génération : si l'enseignant de test existe
 * déjà (repéré par son e-mail fixe), la commande s'arrête immédiatement.
 *
 * Usage : php artisan test-data:parcours-scolaire
 *         php artisan test-data:parcours-scolaire --supprimer
 */
class GenererParcoursScolaireTestCommand extends Command
{
    protected $signature = 'test-data:parcours-scolaire {--supprimer : Supprime les données de test précédemment générées}';

    protected $description = "Génère (ou supprime) 2 apprenants de test réels sur 2 années académiques (dont la vraie année active), pour vérifier le parcours scolaire et le document justificatif d'un transfert — pour tester en local uniquement";

    private const EMAIL_TITULAIRE = 'titulaire.test.parcours@ecole.test';

    private const MATRICULE_CONTINU = 'TEST-PARCOURS-01';

    private const MATRICULE_TRANSFERT = 'TEST-PARCOURS-02';

    private const NOM_CLASSE_TEST = 'Vérif. parcours';

    public function handle(BulletinGenerationService $service): int
    {
        if ($this->option('supprimer')) {
            return $this->supprimer();
        }

        if (User::query()->where('email', self::EMAIL_TITULAIRE)->exists()) {
            $this->components->warn('Ces données de test existent déjà (enseignant '.self::EMAIL_TITULAIRE.' trouvé). Rien à faire — lancez `php artisan test-data:parcours-scolaire --supprimer` d\'abord si vous voulez les régénérer.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($service) {
            $niveau = Niveau::query()->firstOrCreate(
                ['libelle' => 'CM1'],
                ['ordre' => (Niveau::query()->max('ordre') ?? 0) + 1, 'cycle' => CycleNiveau::Primaire, 'premiere_scolarisation' => false],
            );
            $francais = Matiere::query()->firstOrCreate(['nom' => 'Français']);
            $maths = Matiere::query()->firstOrCreate(['nom' => 'Mathématiques']);
            $matieres = [$francais, $maths];

            $titulaire = User::factory()->enseignant()->create([
                'name' => 'Vérification Titulaire CM1',
                'email' => self::EMAIL_TITULAIRE,
            ]);

            $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();
            $anneeActiveReutilisee = $anneeActive !== null;

            if (! $anneeActive) {
                $anneeDebut = (int) now()->format('n') >= 8 ? (int) now()->format('Y') : (int) now()->format('Y') - 1;
                // firstOrCreate plutôt que create() : si une vraie année
                // "{$anneeDebut}-{$anneeDebut+1}" existe déjà mais n'est
                // simplement pas encore marquée active, on la réutilise et
                // on l'active, plutôt que de créer un doublon confus (aucune
                // contrainte d'unicité sur `libelle` en base).
                $anneeActive = AnneeAcademique::query()->firstOrCreate(
                    ['libelle' => "{$anneeDebut}-".($anneeDebut + 1)],
                    [
                        'date_debut' => "{$anneeDebut}-10-01",
                        'date_fin' => ($anneeDebut + 1).'-07-31',
                        'nombre_evaluations_prevues' => 9,
                        'promouvoir_automatiquement' => true,
                        'est_active' => false,
                    ],
                );
                $anneeActive->activer();
            }

            $anneeDebutActive = (int) $anneeActive->date_debut->format('Y');
            $anneeDebutPrecedente = $anneeDebutActive - 1;

            // Toujours dédiée à ce test (jamais réutilisée) : voir le
            // docblock de la classe pour le raisonnement de sécurité.
            $anneePrecedente = AnneeAcademique::create([
                'libelle' => "Vérif. parcours {$anneeDebutPrecedente}-{$anneeDebutActive}",
                'date_debut' => "{$anneeDebutPrecedente}-10-01",
                'date_fin' => "{$anneeDebutActive}-07-31",
                'nombre_evaluations_prevues' => 3,
                'promouvoir_automatiquement' => true,
                'est_active' => false,
            ]);

            $classePrecedente = $this->creerClasseTest($anneePrecedente, $niveau, $matieres, $titulaire);
            $classeActive = $this->creerClasseTest($anneeActive, $niveau, $matieres, $titulaire);

            $eleveContinu = Eleve::factory()->create([
                'matricule' => self::MATRICULE_CONTINU,
                'nom' => 'Assogba',
                'prenom' => 'Prudence',
                'sexe' => 'F',
            ]);
            $eleveTransfert = Eleve::factory()->create([
                'matricule' => self::MATRICULE_TRANSFERT,
                'nom' => 'Kindji',
                'prenom' => 'Bertrand',
                'sexe' => 'M',
            ]);

            // Parcours normal sur 2 années consécutives : inscrit dans
            // l'année précédente (avec bulletins/moyenne/décision finalisés,
            // comme un vrai historique), puis dans l'année active en cours.
            $inscriptionPrecedente = Inscription::create([
                'eleve_id' => $eleveContinu->id,
                'classe_id' => $classePrecedente->id,
                'date_inscription' => "{$anneeDebutPrecedente}-10-01",
                'statut' => StatutInscription::Normal,
            ]);
            $this->genererCycleComplet($service, $classePrecedente, [$eleveContinu], $matieres, $titulaire, "{$anneeDebutPrecedente}-10-01");
            $inscriptionPrecedente->fresh()->determinerPassage();
            $service->genererAnnuelsPourClasse($classePrecedente);

            Inscription::create([
                'eleve_id' => $eleveContinu->id,
                'classe_id' => $classeActive->id,
                'date_inscription' => "{$anneeDebutActive}-10-01",
                'statut' => StatutInscription::Normal,
            ]);

            // Transféré entrant : jamais inscrit l'année précédente (arrivé
            // d'une autre école), inscrit seulement dans l'année active —
            // avec un document justificatif rattaché à cette inscription
            // précise (voir DocumentNumerique::inscription() et la frise).
            $inscriptionTransfert = Inscription::create([
                'eleve_id' => $eleveTransfert->id,
                'classe_id' => $classeActive->id,
                'date_inscription' => now()->toDateString(),
                'statut' => StatutInscription::TransfertEntrant,
            ]);
            $this->attacherDocumentTransfert($inscriptionTransfert, $titulaire);

            // L'année active reste en cours : on ne crée un nouvel examen
            // dessus que si aucun n'existe déjà (sinon on réutilise le plus
            // récent, en lecture seule, pour ne jamais faire apparaître un
            // examen fictif dans la vraie liste des examens de l'année).
            $examenActif = Examen::query()->where('annee_academique_id', $anneeActive->id)->latest('date_examen')->first();
            if ($examenActif) {
                $this->genererNotesEtBulletin($service, $classeActive, [$eleveContinu, $eleveTransfert], $matieres, $titulaire, $examenActif);
                // Même mécanisme que le bouton "Recalculer" de l'écran
                // Bulletins (voir BulletinAnnuelGenerationController) : sûr à
                // appeler sur une année encore active, ça ne fait que
                // remplir `moyenne_annuelle` (affichée comme "Moyenne
                // actuelle" tant qu'aucune décision n'est prise — voir
                // EleveController::fiche()), jamais de décision.
                $service->recalculerMoyennesAnnuellesPourClasse($classeActive);
            }

            $this->components->info("Année {$anneePrecedente->libelle} (dédiée au test) : classe « ".self::NOM_CLASSE_TEST." » générée, notes, bulletins mensuels validés et bulletin annuel pour {$eleveContinu->nomComplet()}.");
            $this->components->info(($anneeActiveReutilisee ? 'Année active réutilisée' : 'Année active créée et activée (aucune année active n\'existait)').' : '.$anneeActive->libelle.' — classe « '.self::NOM_CLASSE_TEST.' » avec '.$eleveContinu->nomComplet().' (normal) et '.$eleveTransfert->nomComplet().' (transféré entrant, document justificatif attaché)'.($examenActif ? ", moyenne calculée sur l'examen « {$examenActif->date_examen->translatedFormat('F Y')} » déjà existant." : ', pas encore de moyenne (aucun examen mensuel créé pour cette année pour l\'instant).'));
        });

        $this->line('');
        $this->components->info('Données de test créées avec succès.');
        $this->line('  Enseignant titulaire : '.self::EMAIL_TITULAIRE.' (mot de passe : password)');
        $this->line('  Apprenants : '.self::MATRICULE_CONTINU.' (parcours normal, 2 ans), '.self::MATRICULE_TRANSFERT.' (transféré entrant cette année, avec document justificatif)');
        $this->line('  Consultables dans Dossier élève et documents → fiche de chacun → onglet « Parcours scolaire ».');
        $this->line('');
        $this->line('Pour tout supprimer une fois les tests terminés : php artisan test-data:parcours-scolaire --supprimer');

        return self::SUCCESS;
    }

    /**
     * Supprime tout ce que la génération a créé : les 2 apprenants de test
     * (cascade sur leurs inscriptions/notes/bulletins/documents), les 2
     * classes de test (cascade sur programme/affectations — jamais les
     * autres classes de l'année active éventuellement réutilisée), l'année
     * précédente dédiée (toujours entièrement à nous, jamais réutilisée),
     * et l'enseignant de test. Ne touche JAMAIS à l'année active de
     * l'établissement elle-même (ni à son statut, ni à ses autres classes,
     * ni à un examen déjà existant qui aurait été réutilisé en lecture
     * seule) : voir le docblock de la classe.
     */
    private function supprimer(): int
    {
        $titulaire = User::query()->where('email', self::EMAIL_TITULAIRE)->first();
        $eleves = Eleve::query()->whereIn('matricule', [self::MATRICULE_CONTINU, self::MATRICULE_TRANSFERT])->get();
        $classesTest = Classe::query()->where('nom', self::NOM_CLASSE_TEST)->get();
        $anneesPrecedentesDediees = AnneeAcademique::query()->where('libelle', 'like', 'Vérif. parcours %')->get();

        if (! $titulaire && $eleves->isEmpty() && $classesTest->isEmpty() && $anneesPrecedentesDediees->isEmpty()) {
            $this->components->warn('Aucune donnée de test « parcours scolaire » trouvée — rien à supprimer.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($titulaire, $eleves, $classesTest, $anneesPrecedentesDediees) {
            foreach ($eleves as $eleve) {
                foreach ($eleve->documents as $document) {
                    Storage::disk('local')->delete($document->chemin_fichier);
                }
            }

            $classesTest->each->delete();
            $anneesPrecedentesDediees->each->delete();
            $eleves->each->delete();
            $titulaire?->delete();
        });

        $this->components->info('Données de test « parcours scolaire » supprimées (apprenants, classes de test, année précédente dédiée, enseignant de test). La vraie année active — si elle a été réutilisée — reste intacte.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Matiere>  $matieres
     */
    private function creerClasseTest(AnneeAcademique $anneeAcademique, Niveau $niveau, array $matieres, User $titulaire): Classe
    {
        // firstOrCreate : si l'année active est réutilisée (réelle), son
        // programme CM1/Français/Mathématiques existe peut-être déjà —
        // jamais dupliqué, et jamais supprimé par --supprimer (donnée de
        // programme partagée, même logique que le niveau/les matières).
        foreach ($matieres as $matiere) {
            NiveauMatiere::query()->firstOrCreate([
                'niveau_id' => $niveau->id,
                'matiere_id' => $matiere->id,
                'annee_academique_id' => $anneeAcademique->id,
            ], ['coefficient' => 4]);
        }

        $classe = Classe::create([
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => self::NOM_CLASSE_TEST,
        ]);

        $classe->matieres()->attach(collect($matieres)->mapWithKeys(fn (Matiere $m) => [$m->id => ['coefficient' => 4]]));

        foreach ($matieres as $matiere) {
            AffectationEnseignant::create([
                'enseignant_id' => $titulaire->id,
                'classe_id' => $classe->id,
                'matiere_id' => $matiere->id,
                'annee_academique_id' => $anneeAcademique->id,
                'est_professeur_principal' => true,
            ]);
        }

        return $classe;
    }

    /**
     * 3 évaluations mensuelles, notes, bulletins mensuels validés — même
     * pipeline que `demo:classe-primaire`, pour une année entièrement
     * dédiée au test (jamais un examen d'une vraie année réutilisée).
     *
     * @param  array<int, Eleve>  $eleves
     * @param  array<int, Matiere>  $matieres
     */
    private function genererCycleComplet(BulletinGenerationService $service, Classe $classe, array $eleves, array $matieres, User $titulaire, string $dateDebutAnnee): void
    {
        for ($mois = 1; $mois <= 3; $mois++) {
            $dateExamen = Carbon::parse($dateDebutAnnee)->addMonths($mois);

            $examen = Examen::create([
                'annee_academique_id' => $classe->annee_academique_id,
                'systeme' => SystemeScolaire::Primaire,
                'type' => TypeEvaluation::EvaluationMensuelle,
                'date_examen' => $dateExamen->toDateString(),
                'date_limite_saisie' => $dateExamen->clone()->addDays(10)->toDateString(),
            ]);

            $this->genererNotesEtBulletin($service, $classe, $eleves, $matieres, $titulaire, $examen);
        }

        $service->recalculerMoyennesAnnuellesPourClasse($classe);
    }

    /**
     * Notes + génération + validation du bulletin, pour un examen donné —
     * factorisé pour être appelable à la fois sur un examen dédié
     * (genererCycleComplet ci-dessus) et sur un examen déjà existant de la
     * vraie année active, réutilisé en lecture seule (voir handle()).
     *
     * @param  array<int, Eleve>  $eleves
     * @param  array<int, Matiere>  $matieres
     */
    private function genererNotesEtBulletin(BulletinGenerationService $service, Classe $classe, array $eleves, array $matieres, User $titulaire, Examen $examen): void
    {
        $classeMatiereIdsParMatiere = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->pluck('id', 'matiere_id');

        foreach ($eleves as $eleve) {
            foreach ($matieres as $matiere) {
                Note::create([
                    'eleve_id' => $eleve->id,
                    'classe_matiere_id' => $classeMatiereIdsParMatiere[$matiere->id],
                    'examen_id' => $examen->id,
                    'enseignant_id' => $titulaire->id,
                    'valeur' => random_int(80, 180) / 10,
                    'type' => TypeEvaluation::EvaluationMensuelle,
                    'numero' => 1,
                    'date_saisie' => $examen->date_examen->toDateString(),
                ]);
            }
        }

        $service->genererPourClasse($classe, $examen);

        Bulletin::query()
            ->where('examen_id', $examen->id)
            ->whereHas('inscription', fn ($q) => $q->where('classe_id', $classe->id))
            ->get()
            ->each(fn (Bulletin $bulletin) => $bulletin->update([
                'statut' => StatutBulletin::Valide,
                'valide_par_id' => $titulaire->id,
                'valide_at' => now(),
            ]));
    }

    /**
     * Attache un document justificatif à l'inscription "Transféré entrant"
     * — c'est ce document que la frise du parcours scolaire affiche
     * directement sur cette ligne (voir eleve-fiche.js's friseDocumentsHTML()
     * et DocumentController::store()). Réutilise un type de document déjà
     * configuré pour les transferts (`requis_si_transfert`) si l'établissement
     * en a un ; en crée un sinon, sans jamais le supprimer via --supprimer
     * (donnée de configuration potentiellement partagée — même logique que
     * le niveau/les matières de `demo:classe-primaire`).
     */
    private function attacherDocumentTransfert(Inscription $inscription, User $televerseur): void
    {
        $type = TypeDocument::query()->where('requis_si_transfert', true)->first()
            ?? TypeDocument::query()->firstOrCreate(
                ['libelle' => 'Certificat de transfert'],
                ['formats_acceptes' => ['PDF'], 'obligatoire' => false, 'protege' => false, 'requis_si_transfert' => true],
            );

        $chemin = 'documents-eleves/test-parcours-certificat-transfert-'.$inscription->id.'.pdf';
        Storage::disk('local')->put($chemin, "%PDF-1.4\n% Document de test — certificat de transfert (donnée de vérification, pas un vrai PDF).\n");

        DocumentNumerique::create([
            'eleve_id' => $inscription->eleve_id,
            'inscription_id' => $inscription->id,
            'type_document_id' => $type->id,
            'televerse_par' => $televerseur->id,
            'chemin_fichier' => $chemin,
            'date_ajout' => now()->toDateString(),
        ]);
    }
}
