<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Séance du 07/10/2026 : documents obligatoires pour enregistrer un
     * nouvel apprenant = photo d'identité, acte de naissance, et bulletin de
     * l'école précédente (seulement s'il vient d'une autre école). Le
     * certificat de scolarité antérieure devient facultatif (il reste un
     * type protégé, simplement plus exigé).
     */
    public function up(): void
    {
        DB::table('types_documents')
            ->whereIn('libelle', ["Photo d'identité", 'Acte de naissance'])
            ->update(['obligatoire' => true]);

        DB::table('types_documents')
            ->where('libelle', "Bulletin de l'école précédente")
            ->update(['requis_si_transfert' => true]);

        DB::table('types_documents')
            ->where('libelle', 'Certificat de scolarité antérieure')
            ->update(['obligatoire' => false, 'requis_si_transfert' => false]);
    }

    public function down(): void
    {
        DB::table('types_documents')
            ->where('libelle', 'Certificat de scolarité antérieure')
            ->update(['requis_si_transfert' => true]);
    }
};
