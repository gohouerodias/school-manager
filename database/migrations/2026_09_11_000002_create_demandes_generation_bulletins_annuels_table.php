<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Équivalent annuel de `demandes_generation_bulletins` (voir cette table) —
 * une ligne = « la classe X a demandé la génération de son bulletin annuel ».
 * Pas d'`examen_id` ici : le bulletin annuel porte sur toute l'année
 * académique de la classe (voir Examen::pourClasse() pour le décompte des
 * évaluations de l'année), pas sur un seul examen mensuel — d'où
 * `classe_id` seul en clé unique plutôt que `[classe_id, examen_id]`.
 * Colonnes de progression/statut créées directement ici (contrairement à
 * demandes_generation_bulletins, qui les a reçues dans une migration
 * ultérieure) puisque cette table est neuve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_generation_bulletins_annuels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->unique()->constrained('classes')->cascadeOnDelete();
            $table->foreignId('demande_par_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('demande_at');
            $table->string('statut')->default('en_attente');
            $table->timestamp('genere_at')->nullable();
            $table->unsignedInteger('nb_bulletins_generes')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('traites')->default(0);
            $table->string('chemin_pdf')->nullable();
            $table->text('erreur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_generation_bulletins_annuels');
    }
};
