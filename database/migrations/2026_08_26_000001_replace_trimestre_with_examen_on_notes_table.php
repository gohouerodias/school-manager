<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Trimestre" UI feature was replaced by "Examens" (see
 * Academique\ExamenController) before any note-entry UI existed — this
 * repoints Note at Examen instead, and adds `commentaire` for the
 * per-matière remark a teacher can leave alongside a grade (see
 * Enseignant\EspaceEnseignantController).
 *
 * The foreign key on `trimestre_id` is looked up by its actual name (via
 * information_schema) rather than assumed to be the Laravel-convention
 * `notes_trimestre_id_foreign` — some environments' constraint ended up
 * named differently, which made a hardcoded dropForeign() fail with
 * "Can't DROP FOREIGN KEY ... check that it exists".
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeysOn('notes', 'trimestre_id');

        if (Schema::hasColumn('notes', 'trimestre_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropColumn('trimestre_id');
            });
        }

        Schema::table('notes', function (Blueprint $table) {
            if (! Schema::hasColumn('notes', 'examen_id')) {
                $table->foreignId('examen_id')->after('classe_matiere_id')->constrained('examens')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('notes', 'commentaire')) {
                $table->text('commentaire')->nullable()->after('valeur');
            }
        });
    }

    public function down(): void
    {
        $this->dropForeignKeysOn('notes', 'examen_id');

        if (Schema::hasColumn('notes', 'examen_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropColumn(['examen_id', 'commentaire']);
            });
        }

        if (! Schema::hasColumn('notes', 'trimestre_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->foreignId('trimestre_id')->after('classe_matiere_id')->constrained('trimestres')->cascadeOnDelete();
            });
        }
    }

    /**
     * Drops every foreign key constraint on $table that covers $column,
     * whatever it's actually named — safer than assuming Laravel's default
     * naming convention holds in every environment.
     */
    private function dropForeignKeysOn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $connection = Schema::getConnection();

        // Only MySQL is affected by constraint-naming drift (the reason this
        // whole information_schema lookup exists); on SQLite (used by the
        // test suite, see phpunit.xml) Laravel's own naming convention is
        // reliable, and SQLite refuses to drop a column that's still part
        // of a foreign key, so this step can't simply be skipped there.
        if ($connection->getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            return;
        }

        $database = $connection->getDatabaseName();

        $foreignKeys = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column]
        );

        foreach ($foreignKeys as $foreignKey) {
            Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                $blueprint->dropForeign($foreignKey->CONSTRAINT_NAME);
            });
        }
    }
};
