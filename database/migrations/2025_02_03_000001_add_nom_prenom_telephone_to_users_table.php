<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds nom/prenom/telephone as their own columns (used by the account
     * management forms and each user's self-service profile), while keeping
     * the existing `name` column as the single computed display name used
     * throughout the app (avatars, search, layout, notifications...).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nom')->nullable()->after('name');
            $table->string('prenom')->nullable()->after('nom');
            $table->string('telephone')->nullable()->after('prenom');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nom', 'prenom', 'telephone']);
        });
    }
};
