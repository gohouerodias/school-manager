<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'appréciation mensuelle d'un apprenant de maternelle pour un domaine
 * d'évaluation donné — équivalent maternelle de `notes`, mais qualitative
 * (voir App\Enums\NiveauQualitatif) plutôt que chiffrée, avec son
 * observation optionnelle au même endroit (la maternelle n'a pas de table
 * `commentaires_matiere` séparée : un seul enseignant titulaire saisit
 * généralement toute la grille, contrairement au primaire/collège où
 * plusieurs enseignants se partagent les matières).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations_domaine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_domaine_id')->constrained('classe_domaine')->cascadeOnDelete();
            $table->foreignId('examen_id')->constrained('examens')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->string('valeur')->nullable()->comment('App\Enums\NiveauQualitatif : ts, s ou ps');
            $table->text('observation')->nullable();
            $table->date('date_saisie')->nullable();
            $table->timestamps();

            $table->unique(['eleve_id', 'classe_domaine_id', 'examen_id'], 'evaluations_domaine_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations_domaine');
    }
};
