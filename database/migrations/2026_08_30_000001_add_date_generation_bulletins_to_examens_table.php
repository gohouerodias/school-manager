<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La date à laquelle la commande planifiée `bulletins:generer` (voir
 * routes/console.php) doit produire les bulletins d'une classe pour cet
 * examen, une fois leur génération demandée (voir
 * Eleves\BulletinGenerationController::demanderGeneration() et
 * App\Models\DemandeGenerationBulletin) — nullable tant que l'admin ne l'a
 * pas encore configurée pour cet examen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->date('date_generation_bulletins')->nullable()->after('date_limite_saisie')
                ->comment('date à laquelle les bulletins demandés pour cet examen sont générés automatiquement');
        });
    }

    public function down(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->dropColumn('date_generation_bulletins');
        });
    }
};
