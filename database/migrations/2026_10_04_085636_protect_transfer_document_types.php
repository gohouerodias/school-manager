<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Bulletin de l'école précédente" and "Certificat de scolarité
     * antérieure" (requis_si_transfert) carry an application rule — they're
     * what a transferred élève must provide — so deleting them would
     * silently drop that rule. They become protégés (like "Photo
     * d'identité"): no deletion, no renaming.
     *
     * Some installs ended up with duplicates of these types (same libellé
     * twice). Only the oldest of each libellé is protected; an extra copy
     * that no document uses is removed, and one already in use is left
     * unprotected so an admin can still clean it up.
     */
    public function up(): void
    {
        $types = DB::table('types_documents')
            ->where('requis_si_transfert', true)
            ->orderBy('id')
            ->get(['id', 'libelle']);

        foreach ($types->groupBy('libelle') as $exemplaires) {
            DB::table('types_documents')->where('id', $exemplaires->first()->id)->update(['protege' => true]);

            foreach ($exemplaires->slice(1) as $doublon) {
                $utilise = DB::table('documents_numeriques')->where('type_document_id', $doublon->id)->exists();

                if (! $utilise) {
                    DB::table('types_documents')->where('id', $doublon->id)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('types_documents')->where('requis_si_transfert', true)->update(['protege' => false]);
    }
};
