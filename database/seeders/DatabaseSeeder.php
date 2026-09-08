<?php

namespace Database\Seeders;

use App\Models\ParametreSysteme;
use Database\Seeders\Concerns\SeedsReferenceData;
use Illuminate\Database\Seeder;

/**
 * Démarre une instance réelle (pas de démo) avec uniquement les données de
 * référence dont l'application a besoin pour fonctionner : niveaux, année
 * académique, examen, types de documents, matières, champs personnalisés.
 *
 * Ne crée aucun compte utilisateur — le compte administrateur principal se
 * crée séparément via `php artisan admin:creer-principal`, puis sert à
 * inviter tous les autres comptes (agent de scolarité, direction,
 * enseignants) depuis l'écran "Gestion des comptes" de l'application.
 *
 * Ne crée aucune donnée fictive (pas d'élèves, notes ou bulletins générés
 * aléatoirement) — c'est l'unique seeder de ce projet, lancé simplement via
 * `php artisan db:seed --force` (c'est le seeder par défaut de Laravel).
 */
class DatabaseSeeder extends Seeder
{
    use SeedsReferenceData;

    public function run(): void
    {
        ParametreSysteme::factory()->create(['duree_conservation_donnees' => 60]);

        $this->seedNiveaux();
        $anneeAcademique = $this->seedAnneeAcademique();
        $this->seedExamen($anneeAcademique);
        $this->seedTypesDocuments();
        $this->seedMatieres();
        $this->seedChampsPersonnalises();
    }
}
