<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une classe de maternelle n'a pas de matières (voir domaines_evaluation) —
 * son unique enseignant s'affecte donc sans matière précise (voir
 * Academique\AffectationEnseignantController::store(), qui crée une seule
 * ligne avec matiere_id=null pour ce cas, au lieu d'une ligne par matière
 * comme pour le primaire/collège).
 *
 * Recrée la colonne (plutôt que ->nullable()->change(), qui exigerait
 * doctrine/dbal) pour rester compatible MySQL comme SQLite sans nouvelle
 * dépendance. Chaque étape vérifie l'état réel de la table via les
 * introspections natives de Laravel (getColumns()/getIndexes()/
 * getForeignKeys(), sans doctrine/dbal non plus) avant d'agir : MySQL/
 * MariaDB n'appliquent pas toujours un ALTER TABLE multi-clauses de façon
 * atomique, une exécution précédente en échec a donc pu laisser la table
 * dans un état intermédiaire (FK déjà supprimée, index déjà supprimé...).
 */
return new class extends Migration
{
    public function up(): void
    {
        $matiereColumn = collect(Schema::getColumns('affectations_enseignant'))->firstWhere('name', 'matiere_id');

        // Déjà migré par une tentative précédente : rien à faire.
        if ($matiereColumn && $matiereColumn['nullable']) {
            return;
        }

        if ($matiereColumn) {
            $foreignKey = collect(Schema::getForeignKeys('affectations_enseignant'))
                ->first(fn (array $fk) => $fk['columns'] === ['matiere_id']);

            if ($foreignKey) {
                // dropForeign(['matiere_id']) (colonnes), pas
                // dropForeign($foreignKey['name']) (nom) : SQLite (utilisé
                // par les tests) ne sait supprimer une FK que via ses
                // colonnes — voir SQLiteGrammar::compileDropForeign(), qui
                // reconstruit la table entière et exige $command->columns.
                Schema::table('affectations_enseignant', fn (Blueprint $table) => $table->dropForeign(['matiere_id']));
            }

            $indexesExistants = collect(Schema::getIndexes('affectations_enseignant'))->pluck('name');

            if ($indexesExistants->contains('affectation_unique')) {
                // « affectation_unique » (enseignant_id, classe_id,
                // matiere_id, annee_academique_id) sert aussi d'index de
                // support à la FK sur enseignant_id (colonne de tête du
                // composite) : MySQL refuse de le supprimer tant qu'aucun
                // autre index ne couvre cette colonne — on lui en fournit un
                // dédié avant de le supprimer.
                if (! $indexesExistants->contains('affectations_enseignant_enseignant_id_index')) {
                    Schema::table('affectations_enseignant', fn (Blueprint $table) => $table->index('enseignant_id'));
                }

                Schema::table('affectations_enseignant', fn (Blueprint $table) => $table->dropUnique('affectation_unique'));
            }

            Schema::table('affectations_enseignant', fn (Blueprint $table) => $table->dropColumn('matiere_id'));
        }

        Schema::table('affectations_enseignant', function (Blueprint $table) {
            $table->foreignId('matiere_id')->nullable()->after('classe_id')->constrained('matieres')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $matiereColumn = collect(Schema::getColumns('affectations_enseignant'))->firstWhere('name', 'matiere_id');

        if (! $matiereColumn || ! $matiereColumn['nullable']) {
            return;
        }

        $foreignKey = collect(Schema::getForeignKeys('affectations_enseignant'))
            ->first(fn (array $fk) => $fk['columns'] === ['matiere_id']);

        if ($foreignKey) {
            Schema::table('affectations_enseignant', fn (Blueprint $table) => $table->dropForeign(['matiere_id']));
        }

        Schema::table('affectations_enseignant', fn (Blueprint $table) => $table->dropColumn('matiere_id'));

        Schema::table('affectations_enseignant', function (Blueprint $table) {
            $table->foreignId('matiere_id')->after('classe_id')->constrained('matieres')->cascadeOnDelete();
            $table->unique(['enseignant_id', 'classe_id', 'matiere_id', 'annee_academique_id'], 'affectation_unique');
        });
    }
};
