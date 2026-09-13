<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combien de notes mensuelles (devoirs/interrogations) comptent pour la
     * moyenne du mois pour chaque matière — configuré par année académique
     * (voir Academique\AnneeAcademiqueController::store()/update()) puisque
     * ce nombre peut changer d'une année à l'autre. Purement déclaratif pour
     * l'instant : la feuille de saisie de l'enseignant (Enseignant\
     * EspaceEnseignantController) ne s'appuie pas encore dessus.
     */
    public function up(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->unsignedTinyInteger('nombre_evaluations_mensuelles')->default(2)->after('date_fin');
        });
    }

    public function down(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->dropColumn('nombre_evaluations_mensuelles');
        });
    }
};
