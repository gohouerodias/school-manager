<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('systeme')->comment('primaire | secondaire (secondaire indisponible pour le moment)');
            $table->string('type')->comment('ex: evaluation_mensuelle');
            $table->date('date_examen');
            $table->date('date_limite_saisie')->comment('délai laissé aux enseignants pour saisir les notes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examens');
    }
};
