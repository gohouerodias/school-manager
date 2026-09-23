<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable : la grande majorité des documents restent rattachés seulement à
 * l'élève (pièce d'identité, photo…), sans lien à une année précise. Ce
 * champ ne se remplit que pour un document justifiant un événement précis
 * du parcours scolaire — typiquement le certificat/l'attestation prouvant
 * un "Transféré entrant" (voir App\Enums\StatutInscription et la frise
 * chronologique de la fiche élève) — afin qu'il s'affiche directement sur
 * la ligne concernée plutôt que seulement dans l'onglet Documents. nullOnDelete
 * plutôt que cascadeOnDelete : si l'inscription est supprimée, le document
 * reste sur la fiche de l'élève (juste détaché de cette année précise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents_numeriques', function (Blueprint $table) {
            $table->foreignId('inscription_id')->nullable()->after('eleve_id')->constrained('inscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents_numeriques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inscription_id');
        });
    }
};
