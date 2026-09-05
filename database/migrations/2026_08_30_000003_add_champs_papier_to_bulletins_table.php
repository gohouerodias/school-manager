<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs renseignés par le titulaire dans le commentaire général du
 * bulletin (voir Enseignant\EspaceEnseignantController::saveBulletin()),
 * repris tels quels sur le bulletin papier généré (voir
 * files/bulletin.html — bloc « Assiduité / Conduite / Défauts majeurs /
 * Qualités / Décision »), en plus de `appreciation` et `resultat_global`
 * qui existaient déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->string('assiduite')->nullable()->after('resultat_global');
            $table->string('conduite')->nullable()->after('assiduite');
            $table->string('defauts_majeurs')->nullable()->after('conduite');
            $table->string('qualites')->nullable()->after('defauts_majeurs');
            $table->string('decision_pedagogique')->nullable()->after('qualites')
                ->comment('ex: "renforcer en lecture et écriture" — texte libre du titulaire');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropColumn(['assiduite', 'conduite', 'defauts_majeurs', 'qualites', 'decision_pedagogique']);
        });
    }
};
