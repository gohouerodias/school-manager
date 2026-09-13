<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liste maîtresse des "domaines d'évaluation" de la maternelle (Langage,
 * Pré-lecture, Pré-mathématique...) — l'équivalent maternelle de `matieres`
 * pour le primaire/collège, mais sans coefficient : la maternelle n'a pas de
 * moyenne chiffrée, seulement une appréciation qualitative par domaine (voir
 * App\Enums\NiveauQualitatif). Réutilisée chaque année via `niveau_domaine`,
 * exactement comme Matiere/NiveauMatiere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domaines_evaluation', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domaines_evaluation');
    }
};
