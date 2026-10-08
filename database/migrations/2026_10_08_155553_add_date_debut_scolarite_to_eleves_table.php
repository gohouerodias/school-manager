<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date at which the élève started school (here or elsewhere) — optional,
     * filled in from the fiche wizard when known.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->date('date_debut_scolarite')->nullable()->after('date_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn('date_debut_scolarite');
        });
    }
};
