<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eleve_parent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('parent_tuteur_id')->constrained('parent_tuteurs')->cascadeOnDelete();
            $table->string('lien_parente');
            $table->timestamps();

            $table->unique(['eleve_id', 'parent_tuteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleve_parent');
    }
};
