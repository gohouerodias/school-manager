<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fiche élève wizard's "Sauvegarder" (brouillon) action can persist
     * an Eleve row before any of these fields have been filled in — they're
     * only truly required once "Terminer" is confirmed (see
     * SaveEleveWizardRequest). Until now they were NOT NULL from the
     * original create_eleves_table migration, which only ever supported the
     * old single-step "Nouvel apprenant" form where they were always
     * required up front.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('nom')->nullable()->change();
            $table->string('prenom')->nullable()->change();
            $table->date('date_naissance')->nullable()->change();
            $table->string('sexe')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('nom')->nullable(false)->change();
            $table->string('prenom')->nullable(false)->change();
            $table->date('date_naissance')->nullable(false)->change();
            $table->string('sexe')->nullable(false)->change();
        });
    }
};
