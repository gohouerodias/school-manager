<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US D.3 — when la direction modifie la proposition automatique de passage
 * (voir Inscription::determinerPassage()), un motif est obligatoire (voir
 * Academique\DecisionPassageController::update()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->text('motif_decision')->nullable()->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('motif_decision');
        });
    }
};
