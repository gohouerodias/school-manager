<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The « date limite de saisie des notes » can now carry a time (e.g.
     * 15/10 à 18h00) instead of only a day. Existing deadlines meant "until
     * the end of that day", so they're moved to 23:59 — otherwise the
     * conversion would turn them into midnight and close every currently
     * open saisie a whole day early.
     */
    public function up(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->dateTime('date_limite_saisie')->comment('délai laissé aux enseignants pour saisir les notes')->change();
        });

        foreach (DB::table('examens')->select(['id', 'date_limite_saisie'])->get() as $examen) {
            DB::table('examens')->where('id', $examen->id)->update([
                'date_limite_saisie' => substr((string) $examen->date_limite_saisie, 0, 10).' 23:59:00',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            $table->date('date_limite_saisie')->comment('délai laissé aux enseignants pour saisir les notes')->change();
        });
    }
};
