<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Champs de formation » du bulletin papier (Français, EA, Mathématique,
 * Anglais, ES, EST, EPS...) : des catégories créées par l'administrateur,
 * auxquelles on rattache les matières. Une matière sans champ reste valide
 * (affichée seule sur le bulletin).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('champs_formation')) {
            Schema::create('champs_formation', function (Blueprint $table) {
                $table->id();
                $table->string('nom')->unique();
                $table->unsignedInteger('ordre')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('matieres', 'champ_formation_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->foreignId('champ_formation_id')
                    ->nullable()
                    ->after('nom')
                    ->constrained('champs_formation')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('matieres', 'champ_formation_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->dropConstrainedForeignId('champ_formation_id');
            });
        }

        Schema::dropIfExists('champs_formation');
    }
};
