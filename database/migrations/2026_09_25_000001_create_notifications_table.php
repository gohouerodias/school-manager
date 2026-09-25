<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table standard du système de notifications de Laravel (canal "database")
 * — voir Illuminate\Notifications\Notifiable, déjà utilisé par App\Models\User.
 * Alimente la cloche de notifications du layout (voir components/
 * notifications-menu.blade.php) : pour l'instant, seule
 * App\Notifications\NotesIncompletesNotification l'utilise (voir
 * App\Console\Commands\NotifierNotesIncompletesCommand).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garde defensive : la table existe déjà sur certains environnements
        // (créée par une exécution antérieure interrompue avant que Laravel
        // n'enregistre cette migration) — éviter une erreur "already exists"
        // plutôt que de forcer un drop qui perdrait déjà des notifications.
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
