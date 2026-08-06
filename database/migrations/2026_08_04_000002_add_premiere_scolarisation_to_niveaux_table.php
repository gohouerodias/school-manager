<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds "Maternelle 1" and "Maternelle 2" ahead of the existing niveaux
     * (CI...3e), and flags them `premiere_scolarisation` — used by the
     * fiche élève wizard's "Documents" step to skip asking for a bulletin/
     * certificat from a previous school (see TypeDocument's
     * requis_si_transfert, added in the next migration).
     *
     * A Classe is also created for each new niveau, for whichever année
     * académique is currently active — otherwise they'd have no classe at
     * all to assign an élève to (this app has no separate "gestion des
     * classes" screen; classes only ever come from seeding/migrations).
     */
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->boolean('premiere_scolarisation')->default(false)->after('cycle');
        });

        // Make room at the front of the ordre sequence for the two new
        // niveaux, without disturbing the existing CI < CP < ... < 3e order.
        DB::table('niveaux')->update(['ordre' => DB::raw('ordre + 2')]);

        $maternelle1 = DB::table('niveaux')->insertGetId([
            'libelle' => 'Maternelle 1',
            'ordre' => 1,
            'cycle' => 'maternelle',
            'premiere_scolarisation' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $maternelle2 = DB::table('niveaux')->insertGetId([
            'libelle' => 'Maternelle 2',
            'ordre' => 2,
            'cycle' => 'maternelle',
            'premiere_scolarisation' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anneeActiveId = DB::table('annees_academiques')->where('est_active', true)->value('id');

        if ($anneeActiveId !== null) {
            foreach ([$maternelle1 => 'Maternelle 1', $maternelle2 => 'Maternelle 2'] as $niveauId => $libelle) {
                DB::table('classes')->insert([
                    'niveau_id' => $niveauId,
                    'annee_academique_id' => $anneeActiveId,
                    'nom' => "{$libelle} A",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('classes')->whereIn('niveau_id', function ($query) {
            $query->select('id')->from('niveaux')->whereIn('libelle', ['Maternelle 1', 'Maternelle 2']);
        })->delete();

        DB::table('niveaux')->whereIn('libelle', ['Maternelle 1', 'Maternelle 2'])->delete();

        DB::table('niveaux')->update(['ordre' => DB::raw('ordre - 2')]);

        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropColumn('premiere_scolarisation');
        });
    }
};
