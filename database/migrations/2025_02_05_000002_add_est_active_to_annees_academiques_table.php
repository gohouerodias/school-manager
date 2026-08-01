<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Année académique active" is a distinct concept from the date range:
     * a school can prepare next year's structure (niveaux, classes,
     * programme) while the current year is still running. Only one row
     * should be active at a time (enforced in the model/controller, not the
     * DB, since that "single true" rule isn't portably expressible as a
     * constraint).
     */
    public function up(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->boolean('est_active')->default(false)->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->dropColumn('est_active');
        });
    }
};
