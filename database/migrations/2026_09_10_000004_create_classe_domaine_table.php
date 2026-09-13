<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domaines d'évaluation réellement actifs pour une classe (de maternelle) —
 * équivalent maternelle de `classe_matiere`, sans coefficient. Copié depuis
 * `niveau_domaine` à la création de la classe (voir Academique\
 * ClasseController::synchroniserDomainesDepuisNiveau()), toujours ajustable
 * ensuite indépendamment du programme du niveau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_domaine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('domaine_evaluation_id')->constrained('domaines_evaluation')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['classe_id', 'domaine_evaluation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_domaine');
    }
};
