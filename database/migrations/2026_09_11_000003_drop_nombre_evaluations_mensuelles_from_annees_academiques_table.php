<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `nombre_evaluations_mensuelles` (voir la migration qui l'a introduite,
 * 2026_09_10_000001) est restée purement déclarative : rien dans
 * l'application (feuille de saisie enseignant, calcul de moyenne...) ne s'en
 * servait jamais. Retirée pour ne pas laisser un réglage qui n'a aucun effet
 * — à ne pas confondre avec `nombre_evaluations_prevues`, ajoutée ensuite et
 * elle bien utilisée (voir Examen::pourClasse() et
 * BulletinGenerationService::payloadAnnuelPourClasse()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->dropColumn('nombre_evaluations_mensuelles');
        });
    }

    public function down(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->unsignedTinyInteger('nombre_evaluations_mensuelles')->default(2)->after('date_fin');
        });
    }
};
