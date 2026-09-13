<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme de domaines d'évaluation d'un niveau (de maternelle), redéfini
 * chaque année académique — équivalent maternelle de `niveau_matiere`, sans
 * coefficient. Une Classe créée pour ce niveau/année hérite cette liste dans
 * `classe_domaine` (voir Academique\ClasseController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveau_domaine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('domaine_evaluation_id')->constrained('domaines_evaluation')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['niveau_id', 'domaine_evaluation_id', 'annee_academique_id'], 'niveau_domaine_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveau_domaine');
    }
};
