<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One value per (eleve, champ_personnalise). Storing everything as text
     * keeps this generic across the "texte / date / liste déroulante /
     * nombre" field types configured in `champs_personnalises`.
     */
    public function up(): void
    {
        Schema::create('valeurs_champs_personnalises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('champ_personnalise_id')->constrained('champs_personnalises')->cascadeOnDelete();
            $table->text('valeur')->nullable();
            $table->timestamps();

            $table->unique(['eleve_id', 'champ_personnalise_id'], 'eleve_champ_valeur_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valeurs_champs_personnalises');
    }
};
