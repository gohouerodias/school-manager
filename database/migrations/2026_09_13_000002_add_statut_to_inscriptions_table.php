<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voir App\Enums\StatutInscription pour le détail des valeurs possibles et
 * de qui les fixe (bascule automatique vs correction manuelle). Défaut
 * "normal" : toute Inscription déjà existante avant ce chantier (créée par
 * EleveClasseController ou PromotionAnnuelleService) était de facto une
 * inscription normale — aucune migration de données n'est nécessaire au-delà
 * de cette valeur par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->string('statut')->default('normal')->after('date_inscription');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
    }
};
