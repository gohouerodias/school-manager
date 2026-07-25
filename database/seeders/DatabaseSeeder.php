<?php

namespace Database\Seeders;

use App\Enums\CycleNiveau;
use App\Enums\ProfilUtilisateur;
use App\Enums\StatutTrimestre;
use App\Enums\TypeEvaluation;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
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
use App\Models\Trimestre;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a realistic (but reduced-scale) dataset for CSC Madre Trinidad.
     *
     * The school has ~7800 students across 10 grade levels in real life;
     * this seeder creates a representative subset (~10 classes, ~80
     * students) so the app is usable and demoable without generating
     * an unreasonably large local dataset.
     */
    public function run(): void
    {
        ParametreSysteme::factory()->create(['duree_conservation_donnees' => 60]);

        $niveaux = $this->seedNiveaux();
        $anneeAcademique = $this->seedAnneeAcademique();
        $trimestres = $this->seedTrimestres($anneeAcademique);
        $typesDocuments = $this->seedTypesDocuments();
        $matieres = $this->seedMatieres();

        $admin = User::factory()->administrateur()->create([
            'name' => 'Admin CSC',
            'email' => 'admin@cscmadretrinidad.bj',
        ]);
        $agentScolarite = User::factory()->agentScolarite()->create([
            'name' => 'Agent Scolarité',
            'email' => 'scolarite@cscmadretrinidad.bj',
        ]);
        User::factory()->direction()->create([
            'name' => 'Direction CSC',
            'email' => 'direction@cscmadretrinidad.bj',
        ]);
        $enseignants = User::factory()->enseignant()->count(12)->create();

        $classes = $this->seedClasses($niveaux, $anneeAcademique);
        $this->seedClasseMatiere($classes, $matieres);
        $this->seedAffectations($classes, $matieres, $enseignants, $anneeAcademique);

        $trimestreOuvert = $trimestres->first();

        foreach ($classes as $classe) {
            $eleves = Eleve::factory()->count(8)->create();

            foreach ($eleves as $eleve) {
                $this->attacherParents($eleve);
                $this->creerDocuments($eleve, $typesDocuments, $agentScolarite);

                $inscription = Inscription::factory()->create([
                    'eleve_id' => $eleve->id,
                    'classe_id' => $classe->id,
                ]);

                $this->saisirNotes($eleve, $classe, $trimestreOuvert, $enseignants);

                $bulletin = Bulletin::factory()->create([
                    'inscription_id' => $inscription->id,
                    'trimestre_id' => $trimestreOuvert->id,
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
     * @return \Illuminate\Support\Collection<int, Niveau>
     */
    private function seedNiveaux(): \Illuminate\Support\Collection
    {
        $definitions = [
            ['libelle' => 'CI', 'ordre' => 1, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CP', 'ordre' => 2, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CE1', 'ordre' => 3, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CE2', 'ordre' => 4, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CM1', 'ordre' => 5, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CM2', 'ordre' => 6, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => '6e', 'ordre' => 7, 'cycle' => CycleNiveau::College],
            ['libelle' => '5e', 'ordre' => 8, 'cycle' => CycleNiveau::College],
            ['libelle' => '4e', 'ordre' => 9, 'cycle' => CycleNiveau::College],
            ['libelle' => '3e', 'ordre' => 10, 'cycle' => CycleNiveau::College],
        ];

        return collect($definitions)->map(fn (array $data) => Niveau::create($data));
    }

    private function seedAnneeAcademique(): AnneeAcademique
    {
        return AnneeAcademique::create([
            'libelle' => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin' => '2026-07-31',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Trimestre>
     */
    private function seedTrimestres(AnneeAcademique $anneeAcademique): \Illuminate\Support\Collection
    {
        $definitions = [
            ['nom' => 'Trimestre 1', 'ordre' => 1, 'debut' => '2025-10-01', 'fin' => '2025-12-20', 'statut' => StatutTrimestre::Ouvert],
            ['nom' => 'Trimestre 2', 'ordre' => 2, 'debut' => '2026-01-05', 'fin' => '2026-03-27', 'statut' => StatutTrimestre::Ferme],
            ['nom' => 'Trimestre 3', 'ordre' => 3, 'debut' => '2026-04-06', 'fin' => '2026-07-10', 'statut' => StatutTrimestre::Ferme],
        ];

        return collect($definitions)->map(fn (array $data) => Trimestre::create([
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => $data['nom'],
            'ordre' => $data['ordre'],
            'date_debut' => $data['debut'],
            'date_fin' => $data['fin'],
            'statut' => $data['statut'],
        ]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, TypeDocument>
     */
    private function seedTypesDocuments(): \Illuminate\Support\Collection
    {
        $definitions = [
            ['libelle' => 'Photo d\'identité', 'obligatoire' => true],
            ['libelle' => 'Acte de naissance', 'obligatoire' => true],
            ['libelle' => 'CIP', 'obligatoire' => false],
            ['libelle' => 'NPI', 'obligatoire' => false],
            ['libelle' => 'Certificat médical', 'obligatoire' => false],
        ];

        return collect($definitions)->map(fn (array $data) => TypeDocument::create([
            'libelle' => $data['libelle'],
            'description' => null,
            'obligatoire' => $data['obligatoire'],
        ]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Matiere>
     */
    private function seedMatieres(): \Illuminate\Support\Collection
    {
        $noms = [
            'Français', 'Mathématiques', 'Sciences de la Vie et de la Terre', 'Histoire-Géographie',
            'Anglais', 'Éducation Civique et Morale', 'Éducation Physique et Sportive', 'Informatique',
        ];

        return collect($noms)->map(fn (string $nom) => Matiere::create(['nom' => $nom]));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Niveau>  $niveaux
     * @return \Illuminate\Support\Collection<int, Classe>
     */
    private function seedClasses(\Illuminate\Support\Collection $niveaux, AnneeAcademique $anneeAcademique): \Illuminate\Support\Collection
    {
        return $niveaux->map(fn (Niveau $niveau) => Classe::create([
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => $niveau->libelle.' A',
        ]));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Classe>  $classes
     * @param  \Illuminate\Support\Collection<int, Matiere>  $matieres
     */
    private function seedClasseMatiere(\Illuminate\Support\Collection $classes, \Illuminate\Support\Collection $matieres): void
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
     * @param  \Illuminate\Support\Collection<int, Classe>  $classes
     * @param  \Illuminate\Support\Collection<int, Matiere>  $matieres
     * @param  \Illuminate\Support\Collection<int, User>  $enseignants
     */
    private function seedAffectations(
        \Illuminate\Support\Collection $classes,
        \Illuminate\Support\Collection $matieres,
        \Illuminate\Support\Collection $enseignants,
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
     * @param  \Illuminate\Support\Collection<int, TypeDocument>  $typesDocuments
     */
    private function creerDocuments(Eleve $eleve, \Illuminate\Support\Collection $typesDocuments, User $agent): void
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
     * @param  \Illuminate\Support\Collection<int, User>  $enseignants
     */
    private function saisirNotes(Eleve $eleve, Classe $classe, Trimestre $trimestre, \Illuminate\Support\Collection $enseignants): void
    {
        $classeMatieres = ClasseMatiere::where('classe_id', $classe->id)->get();

        foreach ($classeMatieres as $classeMatiere) {
            foreach (TypeEvaluation::cases() as $type) {
                Note::factory()->create([
                    'eleve_id' => $eleve->id,
                    'classe_matiere_id' => $classeMatiere->id,
                    'trimestre_id' => $trimestre->id,
                    'enseignant_id' => $enseignants->random()->id,
                    'type' => $type,
                    'numero' => 1,
                ]);
            }
        }
    }
}
