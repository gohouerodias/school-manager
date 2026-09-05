<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_systeme', function (Blueprint $table) {
            $table->float('seuil_passage')->default(10)->after('duree_conservation_donnees')
                ->comment('Moyenne annuelle /20 à partir de laquelle le passage en classe supérieure est proposé automatiquement.');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_systeme', function (Blueprint $table) {
            $table->dropColumn('seuil_passage');
        });
    }
};
