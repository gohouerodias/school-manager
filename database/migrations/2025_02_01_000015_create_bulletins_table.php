<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();
            $table->float('moyenne_generale')->nullable();
            $table->text('appreciation')->nullable();
            $table->unsignedInteger('rang')->nullable();
            $table->date('date_generation')->nullable();
            $table->timestamps();

            $table->unique(['inscription_id', 'trimestre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
    }
};
