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
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\NiveauMatiere;
use App\Models\Note;
use App\Models\User;
use App\Services\BulletinGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Génère (ou supprime, via --supprimer) un jeu de données de démonstration
 * complet, sur trois années académiques historiques, pour une seule classe
 * de primaire — pensé pour tester à l'écran, en local : la frise du parcours
 * scolaire (voir Eleves\EleveController::fiche()), les bulletins mensuels et
 * annuels (BulletinGenerationService), et les décisions de passage. Purement
 * un outil de test manuel — pas destiné à un usage en production.
 *
 * Les 3 années créées ont un libellé (« Démo 2022-2023 », etc.) délibérément
 * impossible à confondre avec un vrai libellé d'année (toujours "AAAA-AAAA"
 * dans cette appli — voir StoreAnneeAcademiqueRequest) : ça garantit qu'elles
 * sont toujours créées de zéro (jamais de collision avec une vraie année déjà
 * en base) et que --supprimer peut les effacer entièrement sans le moindre
 * risque de toucher à de vraies données de l'établissement. Elles restent
 * "en préparation" (est_active = false) : rien ici ne perturbe l'année en
 * cours réelle. Le niveau "CM1" et les matières "Français"/"Mathématiques",
 * eux, sont des données de référence potentiellement partagées avec le reste
 * de l'application (voir DatabaseSeeder) : on les réutilise (firstOrCreate)
 * mais --supprimer ne les touche jamais.
 *
 * Idempotent au niveau de la génération : si l'enseignant de démonstration
 * existe déjà (repéré par son e-mail fixe), la commande s'arrête
 * immédiatement sans rien recréer.
 *
 * Usage : php artisan demo:classe-primaire
 *         php artisan demo:classe-primaire --supprimer
 */
class GenererDemoClassePrimaireCommand extends Command
{
    protected $signature = 'demo:classe-primaire {--supprimer : Supprime le jeu de données de démonstration précédemment généré}';

    protected $description = "Génère (ou supprime) une classe de primaire d'exemple sur 3 années académiques, avec notes, bulletins mensuels validés et bulletin annuel — pour tester en local uniquement";

    private const EMAIL_TITULAIRE = 'titulaire.demo.cm1a@ecole.test';

    private const LIBELLES_ANNEES = ['Démo 2022-2023', 'Démo 2023-2024', 'Démo 2024-2025'];

    public function handle(BulletinGenerationService $service): int
    {
        if ($this->option('supprimer')) {
            return $this->supprimer();
        }

        if (User::query()->where('email', self::EMAIL_TITULAIRE)->exists()) {
            $this->components->warn('Ce jeu de données de démonstration existe déjà (enseignant '.self::EMAIL_TITULAIRE.' trouvé). Rien à faire — lancez `php artisan demo:classe-primaire --supprimer` d\'abord si vous voulez le régénérer.');

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
                'name' => 'Démonstration Titulaire CM1',
                'email' => self::EMAIL_TITULAIRE,
            ]);

            $eleves = collect([
                ['matricule' => 'DEMO-CM1-01', 'nom' => 'Ahouansou', 'prenom' => 'Emmanuel', 'sexe' => 'M'],
                ['matricule' => 'DEMO-CM1-02', 'nom' => 'Biaou', 'prenom' => 'Grace', 'sexe' => 'F'],
                ['matricule' => 'DEMO-CM1-03', 'nom' => 'Dossou', 'prenom' => 'Marcel', 'sexe' => 'M'],
            ])->map(fn (array $d) => Eleve::factory()->create($d));

            // Trois années historiques ; la 2e année rejoue volontairement le
            // même niveau (statut Redoublant) pour donner à la frise du
            // parcours scolaire un cas de figure notable à afficher, en plus
            // du cas normal.
            $annees = [
                ['libelle' => self::LIBELLES_ANNEES[0], 'anneeDebut' => 2022, 'statut' => StatutInscription::Normal],
                ['libelle' => self::LIBELLES_ANNEES[1], 'anneeDebut' => 2023, 'statut' => StatutInscription::Redoublant],
                ['libelle' => self::LIBELLES_ANNEES[2], 'anneeDebut' => 2024, 'statut' => StatutInscription::Normal],
            ];

            foreach ($annees as $definition) {
                $this->genererAnnee($service, $definition['libelle'], $definition['anneeDebut'], $definition['statut'], $niveau, $matieres, $titulaire, $eleves);
                $this->components->info("Année {$definition['libelle']} : classe CM1 A générée (notes, 3 bulletins mensuels validés, bulletin annuel).");
            }
        });

        $this->line('');
        $this->components->info('Jeu de données de démonstration créé avec succès.');
        $this->line('  Enseignant titulaire : '.self::EMAIL_TITULAIRE.' (mot de passe : password)');
        $this->line('  Apprenants : DEMO-CM1-01, DEMO-CM1-02, DEMO-CM1-03 (fiches consultables dans Dossier élève et documents)');
        $this->line('');
        $this->components->warn("Aucune des 3 années créées n'a été marquée active, pour ne pas perturber l'année en cours de l'établissement.");
        $this->line('Pour tout supprimer une fois les tests terminés : php artisan demo:classe-primaire --supprimer');

        return self::SUCCESS;
    }

    /**
     * Supprime tout ce que la génération a créé, à l'exception du niveau
     * "CM1" et des matières "Français"/"Mathématiques" (données de référence
     * potentiellement partagées — voir le docblock de la classe). Supprimer
     * les 3 AnneeAcademique de démonstration suffit : elles cascadent déjà,
     * au niveau des migrations, vers leurs classes, inscriptions, bulletins,
     * notes, affectations, programme (niveau_matiere) et examens.
     */
    private function supprimer(): int
    {
        $titulaire = User::query()->where('email', self::EMAIL_TITULAIRE)->first();
        $annees = AnneeAcademique::query()->whereIn('libelle', self::LIBELLES_ANNEES)->get();
        $eleves = Eleve::query()->where('matricule', 'like', 'DEMO-CM1-%')->get();

        if (! $titulaire && $annees->isEmpty() && $eleves->isEmpty()) {
            $this->components->warn('Aucun jeu de données de démonstration trouvé — rien à supprimer.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($titulaire, $annees, $eleves) {
            $annees->each->delete();
            $eleves->each->delete();
            $titulaire?->delete();
        });

        $this->components->info('Jeu de données de démonstration supprimé (années, classes, apprenants, enseignant titulaire).');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Matiere>  $matieres
     * @param  Collection<int, Eleve>  $eleves
     */
    private function genererAnnee(
        BulletinGenerationService $service,
        string $libelle,
        int $anneeDebut,
        StatutInscription $statutInscription,
        Niveau $niveau,
        array $matieres,
        User $titulaire,
        Collection $eleves,
    ): void {
        $anneeAcademique = AnneeAcademique::create([
            'libelle' => $libelle,
            'date_debut' => "{$anneeDebut}-10-01",
            'date_fin' => ($anneeDebut + 1).'-07-31',
            'nombre_evaluations_prevues' => 3,
            'promouvoir_automatiquement' => true,
        ]);

        foreach ($matieres as $matiere) {
            NiveauMatiere::create([
                'niveau_id' => $niveau->id,
                'matiere_id' => $matiere->id,
                'annee_academique_id' => $anneeAcademique->id,
                'coefficient' => 4,
            ]);
        }

        $classe = Classe::create([
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => 'CM1 A',
        ]);

        $classe->matieres()->attach(collect($matieres)->mapWithKeys(fn (Matiere $m) => [$m->id => ['coefficient' => 4]]));
        // Pas via ->pivot->id : withPivot('coefficient') sur Classe::matieres()
        // ne charge que 'coefficient' (+ les deux clés étrangères) sur le
        // pivot, jamais sa propre clé primaire — il faut la requêter à part.
        $classeMatiereIdsParMatiere = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->pluck('id', 'matiere_id');

        foreach ($matieres as $matiere) {
            AffectationEnseignant::create([
                'enseignant_id' => $titulaire->id,
                'classe_id' => $classe->id,
                'matiere_id' => $matiere->id,
                'annee_academique_id' => $anneeAcademique->id,
                'est_professeur_principal' => true,
            ]);
        }

        $inscriptions = $eleves->map(fn (Eleve $eleve) => Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $classe->id,
            'date_inscription' => "{$anneeDebut}-10-01",
            'statut' => $statutInscription,
        ]));

        for ($mois = 1; $mois <= 3; $mois++) {
            $dateExamen = Carbon::parse("{$anneeDebut}-10-01")->addMonths($mois);

            $examen = Examen::create([
                'annee_academique_id' => $anneeAcademique->id,
                'systeme' => SystemeScolaire::Primaire,
                'type' => TypeEvaluation::EvaluationMensuelle,
                'date_examen' => $dateExamen->toDateString(),
                'date_limite_saisie' => $dateExamen->clone()->addDays(10)->toDateString(),
            ]);

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
                        'date_saisie' => $dateExamen->toDateString(),
                    ]);
                }
            }

            $service->genererPourClasse($classe, $examen);

            // Simule la validation par le titulaire (voir Enseignant\
            // EspaceEnseignantController::validerBulletin()) : sans ce
            // statut Valide, ces bulletins ne compteraient pas dans la
            // moyenne annuelle (voir Inscription::calculerMoyenneAnnuelle()).
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

        $service->recalculerMoyennesAnnuellesPourClasse($classe);
        $inscriptions->each(fn (Inscription $inscription) => $inscription->fresh()->determinerPassage());
        $service->genererAnnuelsPourClasse($classe);
    }
}
