<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the two document types the fiche élève wizard's "Documents" step
     * shows conditionally — only when the classe désirée (step 1) isn't a
     * Niveau with `premiere_scolarisation` (see the previous migration) —
     * and the `requis_si_transfert` flag that marks them as such.
     */
    public function up(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->boolean('requis_si_transfert')->default(false)->after('obligatoire');
        });

        DB::table('types_documents')->insert([
            [
                'libelle' => "Bulletin de l'école précédente",
                'description' => null,
                'formats_acceptes' => json_encode(['PDF', 'JPG']),
                'obligatoire' => false,
                'protege' => false,
                'requis_si_transfert' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Certificat de scolarité antérieure',
                'description' => null,
                'formats_acceptes' => json_encode(['PDF', 'JPG']),
                'obligatoire' => false,
                'protege' => false,
                'requis_si_transfert' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('types_documents')->whereIn('libelle', [
            "Bulletin de l'école précédente",
            'Certificat de scolarité antérieure',
        ])->delete();

        Schema::table('types_documents', function (Blueprint $table) {
            $table->dropColumn('requis_si_transfert');
        });
    }
};
