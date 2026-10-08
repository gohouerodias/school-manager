<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Séance du 07/10/2026 — au primaire, la note d'une épreuve est la somme
     * du critère minimal (/18) et du critère de perfectionnement (/2), comme
     * sur le carnet d'évaluation mensuelle. `valeur` reste le total (/20),
     * utilisé partout pour les moyennes ; ces deux colonnes gardent le
     * détail. Nullable : notes saisies avant ce changement, et notes du
     * collège (une seule note /20).
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->float('critere_minimal')->nullable()->after('valeur')->comment('0 à 18');
            $table->float('critere_perfectionnement')->nullable()->after('critere_minimal')->comment('0 à 2');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['critere_minimal', 'critere_perfectionnement']);
        });
    }
};
