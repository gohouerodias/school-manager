<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accepted file extensions for a document type (e.g. ["PDF", "JPG"]),
     * configurable from "Paramètres des dossiers" > Types de documents.
     */
    public function up(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->json('formats_acceptes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->dropColumn('formats_acceptes');
        });
    }
};
