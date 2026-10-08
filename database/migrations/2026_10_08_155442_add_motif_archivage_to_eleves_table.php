<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a fiche was archived (départ, transfert, abandon…), asked when
     * archiving and shown on the fiche. Nullable: fiches archived before
     * this existed have none.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('motif_archivage', 255)->nullable()->after('date_archivage');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn('motif_archivage');
        });
    }
};
