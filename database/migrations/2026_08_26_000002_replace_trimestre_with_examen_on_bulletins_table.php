<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same rationale as the notes table migration — Bulletin now belongs to an
 * Examen instead of a Trimestre. `resultat_global` stores the qualitative
 * rating (see App\Enums\ResultatMensuel) a classe's titulaire picks for the
 * monthly bulletin, alongside the existing free-text `appreciation`.
 *
 * Foreign keys and indexes are looked up by their actual name (via
 * information_schema) rather than assumed to follow Laravel's naming
 * convention — see the notes table migration for why.
 *
 * Ordering matters here: `inscription_id`'s own foreign key (to
 * `inscriptions`) is satisfied by the composite unique index — since
 * `inscription_id` is its leftmost column — rather than by a dedicated
 * single-column index. Dropping that composite index before a replacement
 * one exists leaves `inscription_id`'s FK with no supporting index, which
 * MySQL refuses ("Cannot drop index ... needed in a foreign key
 * constraint"). So the new composite unique index is always created before
 * the old one is dropped, never after.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeysOn('bulletins', 'trimestre_id');

        Schema::table('bulletins', function (Blueprint $table) {
            if (! Schema::hasColumn('bulletins', 'examen_id')) {
                $table->foreignId('examen_id')->after('inscription_id')->constrained('examens')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('bulletins', 'resultat_global')) {
                $table->string('resultat_global')->nullable()->after('appreciation');
            }
        });

        if (! $this->hasIndexOn('bulletins', 'examen_id')) {
            Schema::table('bulletins', function (Blueprint $table) {
                $table->unique(['inscription_id', 'examen_id']);
            });
        }

        $this->dropIndexesOn('bulletins', 'trimestre_id');

        if (Schema::hasColumn('bulletins', 'trimestre_id')) {
            Schema::table('bulletins', function (Blueprint $table) {
                $table->dropColumn('trimestre_id');
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignKeysOn('bulletins', 'examen_id');

        if (! Schema::hasColumn('bulletins', 'trimestre_id')) {
            Schema::table('bulletins', function (Blueprint $table) {
                $table->foreignId('trimestre_id')->after('inscription_id')->constrained('trimestres')->cascadeOnDelete();
            });
        }

        if (! $this->hasIndexOn('bulletins', 'trimestre_id')) {
            Schema::table('bulletins', function (Blueprint $table) {
                $table->unique(['inscription_id', 'trimestre_id']);
            });
        }

        $this->dropIndexesOn('bulletins', 'examen_id');

        if (Schema::hasColumn('bulletins', 'examen_id')) {
            Schema::table('bulletins', function (Blueprint $table) {
                $table->dropColumn(['examen_id', 'resultat_global']);
            });
        }
    }

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

    /**
     * Drops every non-primary index/unique key on $table that covers
     * $column (e.g. the old (inscription_id, trimestre_id) unique key) —
     * call only once a replacement index for `inscription_id` already
     * exists (see the class docblock).
     */
    private function dropIndexesOn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $connection = Schema::getConnection();

        // Only MySQL needs the dynamic lookup; on SQLite Laravel's own
        // naming convention for the unique key (inscription_id + $column)
        // is reliable — this method is only ever called with that pair.
        if ($connection->getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique(['inscription_id', $column]);
            });

            return;
        }

        $database = $connection->getDatabaseName();

        $indexes = $connection->select(
            "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND INDEX_NAME != 'PRIMARY'",
            [$database, $table, $column]
        );

        foreach ($indexes as $index) {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index->INDEX_NAME);
            });
        }
    }

    /**
     * MySQL only (see dropForeignKeysOn()) — on other drivers this always
     * reports "no index yet" so the unique key is (re)created unconditionally,
     * which is correct for a normal (non-partially-applied) migration run.
     */
    private function hasIndexOn(string $table, string $column): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        $database = $connection->getDatabaseName();

        $indexes = $connection->select(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, $column]
        );

        return count($indexes) > 0;
    }
};
