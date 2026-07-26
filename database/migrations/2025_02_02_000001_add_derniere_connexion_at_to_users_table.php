<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the last successful login for display in the account
     * management list. This is purely informational and unrelated to the
     * (removed) session-inactivity-timeout feature.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('derniere_connexion_at')->nullable()->after('doit_changer_mot_de_passe');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('derniere_connexion_at');
        });
    }
};
