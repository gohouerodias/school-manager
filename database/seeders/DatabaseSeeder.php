<?php

namespace Database\Seeders;

use App\Enums\CycleNiveau;
use App\Enums\SystemeScolaire;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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
        $examen = $this->seedExamen($anneeAcademique);
        $typesDocuments = $this->seedTypesDocuments();
        $matieres = $this->seedMatieres();
        $champsPersonnalises = $this->seedChampsPersonnalises();

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
     * @return Collection<int, Niveau>
     */
    private function seedNiveaux(): Collection
    {
        $definitions = [
            ['libelle' => 'Maternelle 1', 'ordre' => 1, 'cycle' => CycleNiveau::Maternelle, 'premiere_scolarisation' => true],
            ['libelle' => 'Maternelle 2', 'ordre' => 2, 'cycle' => CycleNiveau::Maternelle, 'premiere_scolarisation' => true],
            ['libelle' => 'CI', 'ordre' => 3, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CP', 'ordre' => 4, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CE1', 'ordre' => 5, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CE2', 'ordre' => 6, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CM1', 'ordre' => 7, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => 'CM2', 'ordre' => 8, 'cycle' => CycleNiveau::Primaire],
            ['libelle' => '6e', 'ordre' => 9, 'cycle' => CycleNiveau::College],
            ['libelle' => '5e', 'ordre' => 10, 'cycle' => CycleNiveau::College],
            ['libelle' => '4e', 'ordre' => 11, 'cycle' => CycleNiveau::College],
            ['libelle' => '3e', 'ordre' => 12, 'cycle' => CycleNiveau::College],
        ];

        return collect($definitions)->map(fn (array $data) => Niveau::create($data));
    }

    private function seedAnneeAcademique(): AnneeAcademique
    {
        return AnneeAcademique::create([
            'libelle' => '2025-2026',
            'est_active' => true,
            'date_debut' => '2025-10-01',
            'date_fin' => '2026-07-31',
        ]);
    }

    private function seedExamen(AnneeAcademique $anneeAcademique): Examen
    {
        return Examen::create([
            'annee_academique_id' => $anneeAcademique->id,
            'systeme' => SystemeScolaire::Primaire,
            'type' => TypeEvaluation::EvaluationMensuelle,
            'date_examen' => '2025-11-15',
            'date_limite_saisie' => '2025-11-25',
        ]);
    }

    /**
     * @return Collection<int, TypeDocument>
     */
    private function seedTypesDocuments(): Collection
    {
        $definitions = [
            ['libelle' => 'Photo d\'identité', 'formats' => ['JPG', 'PNG'], 'obligatoire' => true, 'protege' => true],
            ['libelle' => 'Acte de naissance', 'formats' => ['PDF', 'JPG'], 'obligatoire' => true, 'protege' => false],
            ['libelle' => 'CIP', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false],
            ['libelle' => 'NPI', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false],
            ['libelle' => 'Certificat médical', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false],
            // Shown by the fiche élève wizard's "Documents" step only when
            // the classe désirée isn't Maternelle 1/2 (Niveau::premiere_scolarisation).
            ['libelle' => "Bulletin de l'école précédente", 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false, 'requis_si_transfert' => true],
            ['libelle' => 'Certificat de scolarité antérieure', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false, 'requis_si_transfert' => true],
        ];

        return collect($definitions)->map(fn (array $data) => TypeDocument::create([
            'libelle' => $data['libelle'],
            'description' => null,
            'formats_acceptes' => $data['formats'],
            'obligatoire' => $data['obligatoire'],
            'protege' => $data['protege'],
            'requis_si_transfert' => $data['requis_si_transfert'] ?? false,
        ]));
    }

    /**
     * Default configurable fields for the fiche apprenant, beyond the fixed
     * Nom/Prénom/Sexe/Date de naissance columns.
     *
     * @return Collection<int, ChampPersonnalise>
     */
    private function seedChampsPersonnalises(): Collection
    {
        $definitions = [
            ['libelle' => 'Lieu de naissance', 'type' => TypeChampPersonnalise::Texte, 'options' => null, 'obligatoire' => true],
            ['libelle' => 'Nationalité', 'type' => TypeChampPersonnalise::Texte, 'options' => null, 'obligatoire' => true],
            ['libelle' => 'Adresse', 'type' => TypeChampPersonnalise::Texte, 'options' => null, 'obligatoire' => true],
            ['libelle' => 'Groupe sanguin', 'type' => TypeChampPersonnalise::ListeDeroulante, 'options' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], 'obligatoire' => false],
            ['libelle' => 'Quartier', 'type' => TypeChampPersonnalise::Texte, 'options' => null, 'obligatoire' => true],
            ['libelle' => 'Allergies', 'type' => TypeChampPersonnalise::Texte, 'options' => null, 'obligatoire' => false],
            ['libelle' => 'Situation de handicap', 'type' => TypeChampPersonnalise::ListeDeroulante, 'options' => ['Aucune', 'Motrice', 'Visuelle', 'Auditive', 'Autre'], 'obligatoire' => false],
        ];

        return collect($definitions)->values()->map(fn (array $data, int $index) => ChampPersonnalise::create([
            'libelle' => $data['libelle'],
            'type' => $data['type'],
            'options' => $data['options'],
            'obligatoire' => $data['obligatoire'],
            'ordre' => $index + 1,
        ]));
    }

    /**
     * @return Collection<int, Matiere>
     */
    private function seedMatieres(): Collection
    {
        $noms = [
            'Français', 'Mathématiques', 'Sciences de la Vie et de la Terre', 'Histoire-Géographie',
            'Anglais', 'Éducation Civique et Morale', 'Éducation Physique et Sportive', 'Informatique',
        ];

        return collect($noms)->map(fn (string $nom) => Matiere::create(['nom' => $nom]));
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
