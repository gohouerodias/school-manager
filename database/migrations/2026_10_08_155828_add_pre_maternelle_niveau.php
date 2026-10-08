<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Séance du 07/10/2026 : ajouter le niveau « Pré-maternelle » juste
     * avant Maternelle 1. Même nature que Maternelle 1/2 : cycle maternelle
     * (domaines d'évaluation) et niveau de première scolarisation (aucun
     * bulletin d'une école précédente demandé). Les niveaux suivants sont
     * décalés d'un rang pour garder l'ordre de passage.
     */
    public function up(): void
    {
        if (DB::table('niveaux')->where('libelle', 'Pré-maternelle')->exists()) {
            return;
        }

        $ordreMaternelle1 = DB::table('niveaux')->where('libelle', 'Maternelle 1')->value('ordre')
            ?? (int) DB::table('niveaux')->min('ordre')
            ?: 1;

        DB::table('niveaux')->where('ordre', '>=', $ordreMaternelle1)->update(['ordre' => DB::raw('ordre + 1')]);

        DB::table('niveaux')->insert([
            'libelle' => 'Pré-maternelle',
            'ordre' => $ordreMaternelle1,
            'cycle' => 'maternelle',
            'premiere_scolarisation' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $niveau = DB::table('niveaux')->where('libelle', 'Pré-maternelle')->first();

        if (! $niveau || DB::table('classes')->where('niveau_id', $niveau->id)->exists()) {
            return;
        }

        DB::table('niveaux')->where('id', $niveau->id)->delete();
        DB::table('niveaux')->where('ordre', '>', $niveau->ordre)->update(['ordre' => DB::raw('ordre - 1')]);
    }
};
