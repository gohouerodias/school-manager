<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only Nom, Prénom, Sexe et Date de naissance restent des colonnes fixes
     * sur `eleves` : ce sont les seuls champs dont dépendent la recherche,
     * le tri, l'avatar et la génération du matricule ailleurs dans l'appli.
     * Lieu de naissance et Adresse deviennent des « champs personnalisés »
     * configurables (voir `champs_personnalises` / `valeurs_champs_personnalises`),
     * au même titre que Nationalité, Groupe sanguin, etc.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['lieu_naissance', 'adresse']);
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->string('adresse')->nullable()->after('sexe');
        });
    }
};
