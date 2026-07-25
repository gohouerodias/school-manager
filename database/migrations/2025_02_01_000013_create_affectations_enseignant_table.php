<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affectations_enseignant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->boolean('est_professeur_principal')->default(false);
            $table->timestamps();

            $table->unique(['enseignant_id', 'classe_id', 'matiere_id', 'annee_academique_id'], 'affectation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations_enseignant');
    }
};
