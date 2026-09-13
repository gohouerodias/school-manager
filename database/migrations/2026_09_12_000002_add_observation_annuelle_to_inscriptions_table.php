<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observation annuelle du titulaire pour le bulletin de fin d'année (voir
 * Enseignant\EspaceEnseignantController::saveObservationAnnuelle()) —
 * distincte de Bulletin::appreciation (mensuelle) : une seule valeur par
 * apprenant pour toute l'année, affichée sur le bulletin annuel (voir
 * eleves/bulletins/_papier_annuel.blade.php et _papier_annuel_pdf.blade.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->text('observation_annuelle')->nullable()->after('motif_decision');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('observation_annuelle');
        });
    }
};
