<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_numeriques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('type_document_id')->constrained('types_documents')->restrictOnDelete();
            $table->foreignId('televerse_par')->constrained('users')->restrictOnDelete();
            $table->string('chemin_fichier');
            $table->date('date_ajout');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_numeriques');
    }
};
