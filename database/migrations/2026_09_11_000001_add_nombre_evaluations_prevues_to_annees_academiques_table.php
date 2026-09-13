<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combien d'évaluations mensuelles (Examen) sont prévues sur toute l'année
 * académique — configuré par année, comme nombre_evaluations_mensuelles (qui
 * porte sur un autre nombre : les notes comptant pour la moyenne du mois).
 * Une fois que l'admin a créé ce nombre d'Examen pour l'année (voir
 * Examen::pourClasse()), l'écran Bulletins propose de générer le bulletin
 * annuel de chaque classe (voir Eleves\BulletinAnnuelGenerationController et
 * BulletinGenerationService::payloadAnnuelPourClasse()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->unsignedTinyInteger('nombre_evaluations_prevues')->default(1)->after('nombre_evaluations_mensuelles');
        });
    }

    public function down(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->dropColumn('nombre_evaluations_prevues');
        });
    }
};
