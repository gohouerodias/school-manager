<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's per-matière remark for one élève on one examen — kept separate
 * from `notes` (rather than a nullable column there) so a comment can exist
 * without forcing `notes.valeur` to be nullable (a grade of "no value yet"
 * must stay clearly distinct from an actual 0/20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commentaires_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->constrained('classe_matiere')->cascadeOnDelete();
            $table->foreignId('examen_id')->constrained('examens')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->text('commentaire');
            $table->timestamps();

            $table->unique(['eleve_id', 'classe_matiere_id', 'examen_id'], 'commentaires_matiere_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commentaires_matiere');
    }
};
