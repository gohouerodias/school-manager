<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_donnees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importe_par')->constrained('users')->cascadeOnDelete();
            $table->string('type_import');
            $table->string('fichier_source');
            $table->date('date_import');
            $table->unsignedInteger('nombre_lignes_importees')->default(0);
            $table->unsignedInteger('nombre_erreurs')->default(0);
            $table->string('statut')->default('en_cours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports_donnees');
    }
};
