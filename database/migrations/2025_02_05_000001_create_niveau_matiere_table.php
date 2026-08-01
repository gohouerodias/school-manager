<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standard curriculum (matières + coefficients) for a niveau, redefined
     * each academic year since the programme can change from one year to
     * the next. When a new classe is created for a given niveau/année, its
     * `classe_matiere` rows are seeded from here (still adjustable per
     * classe afterwards).
     */
    public function up(): void
    {
        Schema::create('niveau_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->float('coefficient');
            $table->timestamps();

            $table->unique(['niveau_id', 'matiere_id', 'annee_academique_id'], 'niveau_matiere_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveau_matiere');
    }
};
