<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable extra fields for the fiche apprenant (Nationalité,
     * Groupe sanguin, Allergies, ...), managed from "Paramètres des
     * dossiers". Nom/Prénom/Sexe/Date de naissance are NOT here: they stay
     * fixed columns on `eleves` since the rest of the app depends on them.
     */
    public function up(): void
    {
        Schema::create('champs_personnalises', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('obligatoire')->default(false);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('champs_personnalises');
    }
};
