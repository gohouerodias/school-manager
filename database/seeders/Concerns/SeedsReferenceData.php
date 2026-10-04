<?php

namespace Database\Seeders\Concerns;

use App\Enums\CycleNiveau;
use App\Enums\SystemeScolaire;
use App\Enums\TypeChampPersonnalise;
use App\Enums\TypeEvaluation;
use App\Models\AnneeAcademique;
use App\Models\ChampPersonnalise;
use App\Models\DomaineEvaluation;
use App\Models\Examen;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\TypeDocument;
use Illuminate\Support\Collection;

/**
 * Données de référence/structurelles nécessaires au démarrage d'une
 * instance réelle — utilisées par `DatabaseSeeder` (voir son docblock).
 */
trait SeedsReferenceData
{
    /**
     * "Maternelle 1"/"Maternelle 2" (ordre 1-2) ne sont volontairement PAS
     * ici : ils sont créés par la migration
     * add_premiere_scolarisation_to_niveaux_table (retrofit historique d'une
     * base déjà en prod à l'époque) — qui s'exécute pour toute installation,
     * fraîche ou existante, puisque les migrations tournent toujours avant
     * ce seeder. Les redéfinir ici les créerait une seconde fois (voir
     * DatabaseSeederTest, qui a révélé le doublon).
     *
     * @return Collection<int, Niveau>
     */
    public function seedNiveaux(): Collection
    {
        $definitions = [
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

    public function seedAnneeAcademique(): AnneeAcademique
    {
        return AnneeAcademique::create([
            'libelle' => '2025-2026',
            'est_active' => true,
            'date_debut' => '2025-10-01',
            'date_fin' => '2026-07-31',
        ]);
    }

    public function seedExamen(AnneeAcademique $anneeAcademique): Examen
    {
        return Examen::create([
            'annee_academique_id' => $anneeAcademique->id,
            'systeme' => SystemeScolaire::Primaire,
            'type' => TypeEvaluation::EvaluationMensuelle,
            'date_examen' => '2025-11-15',
            'date_limite_saisie' => '2025-11-25 23:59:00',
        ]);
    }

    /**
     * "Bulletin de l'école précédente" et "Certificat de scolarité
     * antérieure" ne sont volontairement PAS ici : ils sont créés par la
     * migration add_requis_si_transfert_to_types_documents_table (retrofit
     * historique, comme Maternelle 1/2 — voir seedNiveaux()), qui s'exécute
     * pour toute installation avant ce seeder. Les redéfinir ici les
     * créerait une seconde fois.
     *
     * @return Collection<int, TypeDocument>
     */
    public function seedTypesDocuments(): Collection
    {
        $definitions = [
            ['libelle' => 'Photo d\'identité', 'formats' => ['JPG', 'PNG'], 'obligatoire' => true, 'protege' => true],
            ['libelle' => 'Acte de naissance', 'formats' => ['PDF', 'JPG'], 'obligatoire' => true, 'protege' => false],
            ['libelle' => 'CIP', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false],
            ['libelle' => 'NPI', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false],
            ['libelle' => 'Certificat médical', 'formats' => ['PDF', 'JPG'], 'obligatoire' => false, 'protege' => false],
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
    public function seedChampsPersonnalises(): Collection
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
    public function seedMatieres(): Collection
    {
        $noms = [
            'Français', 'Mathématiques', 'Sciences de la Vie et de la Terre', 'Histoire-Géographie',
            'Anglais', 'Éducation Civique et Morale', 'Éducation Physique et Sportive', 'Informatique',
        ];

        return collect($noms)->map(fn (string $nom) => Matiere::create(['nom' => $nom]));
    }

    /**
     * Domaines d'évaluation de la maternelle (voir RESULTATS DE FIN D'ANNEE.pdf,
     * grille d'évaluation mensuelle) — équivalent des Matieres pour ce cycle,
     * associés aux niveaux/classes via Académique > Niveaux & matières et la
     * fiche de l'année académique (comme les matières, aucun lien n'est créé
     * automatiquement ici).
     *
     * @return Collection<int, DomaineEvaluation>
     */
    public function seedDomainesEvaluation(): Collection
    {
        $noms = [
            'Fréquentation',
            'Propreté corporelle et vestimentaire',
            'Dessin/Coloriage',
            'Langage',
            'Pré Ecriture',
            'Education du mouvement',
            'Pré-Lecture',
            'Pré-mathématique',
            'Poésie et Chant',
            'Anglais',
            'Autres',
        ];

        return collect($noms)->map(fn (string $nom) => DomaineEvaluation::create(['nom' => $nom]));
    }
}
