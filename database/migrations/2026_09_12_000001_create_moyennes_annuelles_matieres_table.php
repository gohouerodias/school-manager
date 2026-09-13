<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moyenne annuelle d'un apprenant (inscription) pour une matière donnée de
 * sa classe — persistée par le bouton « Calculer les moyennes annuelles »
 * (voir App\Services\BulletinGenerationService::recalculerMoyennesAnnuellesPourClasse())
 * plutôt que recalculée à chaque affichage : sur une grande classe, croiser
 * chaque matière avec chaque examen validé de l'année pour chaque apprenant
 * est trop coûteux pour tourner à chaque chargement d'écran (contrairement à
 * Inscription::calculerMoyenneAnnuelle(), une simple moyenne de
 * bulletins.moyenne_generale déjà calculée, qui elle reste calculée en
 * direct).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moyennes_annuelles_matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->constrained('classe_matiere')->cascadeOnDelete();
            $table->float('moyenne')->nullable();
            $table->timestamp('calculee_at')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'classe_matiere_id'], 'moyennes_annuelles_matieres_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moyennes_annuelles_matieres');
    }
};
