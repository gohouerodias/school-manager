<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La génération des bulletins d'une classe se lance désormais immédiatement
 * (job en file d'attente, voir App\Jobs\GenererBulletinsClasseJob) au lieu
 * d'être programmée pour une date future — ces colonnes permettent à l'écran
 * Bulletins de suivre sa progression en temps réel (voir
 * Eleves\BulletinGenerationController::statut(), interrogé par polling).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_generation_bulletins', function (Blueprint $table) {
            $table->string('statut')->default('en_attente')->after('demande_par_id');
            $table->unsignedInteger('total')->default(0)->after('nb_bulletins_generes');
            $table->unsignedInteger('traites')->default(0)->after('total');
            $table->text('erreur')->nullable()->after('chemin_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('demandes_generation_bulletins', function (Blueprint $table) {
            $table->dropColumn(['statut', 'total', 'traites', 'erreur']);
        });
    }
};
