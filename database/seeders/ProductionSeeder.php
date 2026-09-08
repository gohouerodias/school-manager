<?php

namespace Database\Seeders;

use App\Models\ParametreSysteme;
use Database\Seeders\Concerns\SeedsReferenceData;
use Illuminate\Database\Seeder;

/**
 * Démarre une instance réelle (pas de démo) avec uniquement les données de
 * référence dont l'application a besoin pour fonctionner : niveaux, année
 * académique, examen, types de documents, matières, champs personnalisés,
 * et les 3 comptes administratifs nominatifs de l'école.
 *
 * Contrairement à `DatabaseSeeder`, ne crée aucune donnée fictive (pas
 * d'enseignants, élèves, notes ou bulletins générés aléatoirement) — à
 * lancer via `php artisan db:seed --class=ProductionSeeder --force` pour un
 * vrai lancement en production (voir DatabaseSeeder pour le jeu de données
 * de démo complet, utile en local).
 *
 * Les 3 comptes créés doivent changer leur mot de passe à la première
 * connexion (`doit_changer_mot_de_passe`) — communiquer le mot de passe
 * initial ("password") à l'école par un canal séparé, puis leur demander de
 * le changer immédiatement.
 */
class ProductionSeeder extends Seeder
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

        $comptes = $this->seedComptesAdministratifs();

        foreach ($comptes as $compte) {
            $compte->forceFill(['doit_changer_mot_de_passe' => true])->save();
        }
    }
}
