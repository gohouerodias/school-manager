<?php

namespace Database\Seeders;

use App\Enums\CycleNiveau;
use App\Enums\TypeChampPersonnalise;
use App\Enums\TypeEvaluation;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\ImportDonnees;
use App\Models\Inscription;
use App\Models\JournalAction;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\ObservationAdministrative;
use App\Models\ParametreSysteme;
use App\Models\ParentTuteur;
use App\Models\Rapport;
use App\Models\TypeDocument;
use App\Models\User;
use App\Models\ValeurChampPersonnalise;
use App\Support\BeninData;
use Database\Seeders\Concerns\SeedsReferenceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use SeedsReferenceData;

    /**
     * Seed a realistic (but reduced-scale) dataset for CSC Madre Trinidad.
     *
     * The school has ~7800 students across 10 grade levels in real life;
     * this seeder creates a representative subset (~10 classes, ~80
     * students) so the app is usable and demoable without generating
     * an unreasonably large local dataset.
     *
     * For a real production launch (no fake students/teachers/notes), use
     * `ProductionSeeder` instead — see its docblock.
     */
    public function run(): void
    {
        ParametreSysteme::factory()->create(['duree_conservation_donnees' => 60]);

        $niveaux = $this->seedNiveaux();
        $anneeAcademique = $this->seedAnneeAcademique();
        $examen = $this->seedExamen($anneeAcademique);
        $typesDocuments = $this->seedTypesDocuments();
        $matieres = $this->seedMatieres();
        $champsPersonnalises = $this->seedChampsPersonnalises();

        ['admin' => $admin, 'agentScolarite' => $agentScolarite] = $this->seedComptesAdministratifs();
        $enseignants = User::factory()->enseignant()->count(12)->create();

        $classes = $this->seedClasses($niveaux, $anneeAcademique);
        $this->seedClasseMatiere($classes, $matieres);
        $this->seedAffectations($classes, $matieres, $enseignants, $anneeAcademique);

        // L'examen mensuel (voir Academique\ExamenController) n'est pour
        // l'instant disponible que pour le système primaire (Maternelle +
        // Primaire, voir SystemeScolaire::estDisponible()) — les classes de
        // collège n'ont donc pas encore de notes/bulletins de démo.
        foreach ($classes as $classe) {
            $eleves = Eleve::factory()->count(8)->create();
            $classeEstPrimaire = in_array($classe->niveau->cycle, [CycleNiveau::Maternelle, CycleNiveau::Primaire], true);

            foreach ($eleves as $eleve) {
                $this->attacherParents($eleve);
                $this->creerDocuments($eleve, $typesDocuments, $agentScolarite);
                $this->remplirChampsPersonnalises($eleve, $champsPersonnalises);

                $inscription = Inscription::factory()->create([
                    'eleve_id' => $eleve->id,
                    'classe_id' => $classe->id,
                ]);

                if (! $classeEstPrimaire) {
                    continue;
                }

                $this->saisirNotes($eleve, $classe, $examen, $enseignants);

                $bulletin = Bulletin::factory()->create([
                    'inscription_id' => $inscription->id,
                    'examen_id' => $examen->id,
                    'moyenne_generale' => null,
                    'date_generation' => null,
                ]);
                $bulletin->genererBulletin();
            }
        }

        ObservationAdministrative::factory()->count(15)->create(['auteur_id' => $admin->id]);
        JournalAction::factory()->count(50)->create();
        Rapport::factory()->count(5)->create(['genere_par' => $admin->id]);
        ImportDonnees::factory()->count(2)->create(['importe_par' => $admin->id]);
    }

    /**
     * @param  Collection<int, Niveau>  $niveaux
     * @return Collection<int, Classe>
     */
    private function seedClasses(Collection $niveaux, AnneeAcademique $anneeAcademique): Collection
    {
        return $niveaux->map(fn (Niveau $niveau) => Classe::create([
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => $niveau->libelle.' A',
        ]));
    }

    /**
     * @param  Collection<int, Classe>  $classes
     * @param  Collection<int, Matiere>  $matieres
     */
    private function seedClasseMatiere(Collection $classes, Collection $matieres): void
    {
        foreach ($classes as $classe) {
            foreach ($matieres as $matiere) {
                $coefficient = in_array($matiere->nom, ['Français', 'Mathématiques'], true) ? 4 : 2;

                ClasseMatiere::create([
                    'classe_id' => $classe->id,
                    'matiere_id' => $matiere->id,
                    'coefficient' => $coefficient,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Classe>  $classes
     * @param  Collection<int, Matiere>  $matieres
     * @param  Collection<int, User>  $enseignants
     */
    private function seedAffectations(
        Collection $classes,
        Collection $matieres,
        Collection $enseignants,
        AnneeAcademique $anneeAcademique
    ): void {
        foreach ($classes as $classe) {
            $matieresAffectees = $matieres->random(4)->values();

            foreach ($matieresAffectees as $index => $matiere) {
                AffectationEnseignant::create([
                    'enseignant_id' => $enseignants->random()->id,
                    'classe_id' => $classe->id,
                    'matiere_id' => $matiere->id,
                    'annee_academique_id' => $anneeAcademique->id,
                    'est_professeur_principal' => $index === 0,
                ]);
            }
        }
    }

    private function attacherParents(Eleve $eleve): void
    {
        $liens = ['Père', 'Mère', 'Tuteur légal'];
        $nombreParents = fake()->numberBetween(1, 2);

        foreach (range(1, $nombreParents) as $i) {
            $parent = ParentTuteur::factory()->create();
            $eleve->parents()->attach($parent->id, ['lien_parente' => $liens[$i - 1]]);
        }
    }

    /**
     * @param  Collection<int, TypeDocument>  $typesDocuments
     */
    private function creerDocuments(Eleve $eleve, Collection $typesDocuments, User $agent): void
    {
        foreach ($typesDocuments->where('obligatoire', true) as $type) {
            DocumentNumerique::factory()->create([
                'eleve_id' => $eleve->id,
                'type_document_id' => $type->id,
                'televerse_par' => $agent->id,
            ]);
        }
    }

    /**
     * @param  Collection<int, ChampPersonnalise>  $champsPersonnalises
     */
    private function remplirChampsPersonnalises(Eleve $eleve, Collection $champsPersonnalises): void
    {
        foreach ($champsPersonnalises as $champ) {
            $valeur = match ($champ->type) {
                TypeChampPersonnalise::ListeDeroulante => fake()->randomElement($champ->options ?? ['—']),
                default => fake()->randomElement(BeninData::$villes),
            };

            ValeurChampPersonnalise::create([
                'eleve_id' => $eleve->id,
                'champ_personnalise_id' => $champ->id,
                'valeur' => $valeur,
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $enseignants
     */
    private function saisirNotes(Eleve $eleve, Classe $classe, Examen $examen, Collection $enseignants): void
    {
        $classeMatieres = ClasseMatiere::where('classe_id', $classe->id)->get();

        foreach ($classeMatieres as $classeMatiere) {
            Note::factory()->create([
                'eleve_id' => $eleve->id,
                'classe_matiere_id' => $classeMatiere->id,
                'examen_id' => $examen->id,
                'enseignant_id' => $enseignants->random()->id,
                'type' => TypeEvaluation::EvaluationMensuelle,
                'numero' => 1,
            ]);
        }
    }
}
