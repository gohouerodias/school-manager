<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "protégé" type de document (e.g. "Photo d'identité") can't be
     * renamed, have its formats/obligatoire changed, or be deleted from
     * "Paramètres des dossiers" — see TypeDocumentController. It can still
     * be uploaded/replaced per élève from the fiche's "Documents" tab; only
     * the type's own definition is locked.
     */
    public function up(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->boolean('protege')->default(false)->after('obligatoire');
        });

        // Protect the "Photo d'identité" type on databases that were
        // already seeded before this migration existed.
        DB::table('types_documents')
            ->where('libelle', 'like', '%photo%')
            ->update(['protege' => true]);
    }

    public function down(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->dropColumn('protege');
        });
    }
};
