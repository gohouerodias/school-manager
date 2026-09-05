<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne = « la classe X a demandé la génération des bulletins de
 * l'examen Y » (bouton « Générer les bulletins de la classe », voir
 * Eleves\BulletinGenerationController) — la génération elle-même n'a lieu
 * que plus tard, à la date `examens.date_generation_bulletins`, via la
 * commande planifiée `bulletins:generer` (voir routes/console.php), qui
 * renseigne alors `genere_at` et `nb_bulletins_generes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_generation_bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('examen_id')->constrained('examens')->cascadeOnDelete();
            $table->foreignId('demande_par_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('demande_at');
            $table->timestamp('genere_at')->nullable();
            $table->unsignedInteger('nb_bulletins_generes')->nullable();
            $table->string('chemin_pdf')->nullable();
            $table->timestamps();

            $table->unique(['classe_id', 'examen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_generation_bulletins');
    }
};
