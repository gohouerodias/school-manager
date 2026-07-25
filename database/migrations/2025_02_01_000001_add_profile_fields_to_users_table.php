<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profil')->after('email');
            $table->string('statut')->default('actif')->after('profil');
            $table->boolean('deux_fa_actif')->default(false)->after('statut');
            $table->string('secret_2fa')->nullable()->after('deux_fa_actif');
            $table->boolean('doit_changer_mot_de_passe')->default(true)->after('secret_2fa');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profil', 'statut', 'deux_fa_actif', 'secret_2fa', 'doit_changer_mot_de_passe']);
        });
    }
};
