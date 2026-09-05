<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La génération des bulletins n'est plus programmée pour une date future
 * (voir App\Jobs\GenererBulletinsClasseJob) — cliquer sur « Générer les
 * bulletins de la classe » les lance immédiatement, donc cette date n'a
 * plus lieu d'être.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->dropColumn('date_generation_bulletins');
        });
    }

    public function down(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->date('date_generation_bulletins')->nullable()->after('date_limite_saisie')
                ->comment('date à laquelle les bulletins demandés pour cet examen sont générés automatiquement');
        });
    }
};
