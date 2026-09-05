<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US C.2/C.3 — a bulletin mensuel starts as a Brouillon and is Validé
 * ("signé") by la classe's titulaire, which locks the period's notes and
 * commentaires for every enseignant until it's dévalidé (see App\Enums\
 * StatutBulletin, Enseignant\EspaceEnseignantController::validerBulletin()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->string('statut')->default('brouillon')->after('resultat_global');
            $table->foreignId('valide_par_id')->nullable()->after('statut')->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable()->after('valide_par_id');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valide_par_id');
            $table->dropColumn(['statut', 'valide_at']);
        });
    }
};
