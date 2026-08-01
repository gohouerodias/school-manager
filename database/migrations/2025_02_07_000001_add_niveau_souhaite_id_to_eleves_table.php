<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Classe désirée" at creation is really a désired *niveau* (grade
     * level), not a specific classe/section — the censeur later sorts new
     * élèves by niveau and distributes them into actual classes for the
     * année académique. This is purely informational: it does not create
     * an Inscription, so the élève stays "sans classe attribuée" until a
     * real classe is assigned.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->foreignId('niveau_souhaite_id')->nullable()->after('sexe')
                ->constrained('niveaux')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('niveau_souhaite_id');
        });
    }
};
