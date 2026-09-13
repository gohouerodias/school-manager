<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrôle si AnneeAcademiqueController::demarrer() doit exécuter
 * PromotionAnnuelleService::promouvoir() pour cette année (comme cible :
 * "promouvoir les élèves admis de l'année sortante VERS celle-ci"). Réglé à
 * la création (voir StoreAnneeAcademiqueRequest et le formulaire de création
 * dans academique/annees/index.blade.php), mais seulement exploité plus tard
 * au démarrage, une fois que les classes de la nouvelle année existent.
 * Coché par défaut pour préserver le comportement historique (promotion
 * systématique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->boolean('promouvoir_automatiquement')->default(true)->after('nombre_evaluations_prevues');
        });
    }

    public function down(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->dropColumn('promouvoir_automatiquement');
        });
    }
};
