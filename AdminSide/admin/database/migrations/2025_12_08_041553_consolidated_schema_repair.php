<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Disable transaction wrapping.
     * PostgreSQL aborts the ENTIRE transaction when any statement fails,
     * making try/catch useless — subsequent statements get 25P02.
     * Running without a transaction lets each statement succeed/fail independently.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add station_id to users_public if missing
        if (Schema::hasTable('users_public')) {
            if (!Schema::hasColumn('users_public', 'station_id')) {
                Schema::table('users_public', function (Blueprint $table) {
                    $table->unsignedBigInteger('station_id')->nullable()->after('longitude');
                });
            }

            // Add FK for station_id only if it doesn't already exist
            if (!$this->foreignKeyExists('users_public', 'users_public_station_id_foreign')) {
                Schema::table('users_public', function (Blueprint $table) {
                    $table->foreign('station_id')->references('station_id')->on('police_stations')->onDelete('set null');
                });
            }
        }

        // Helper closure to fix FKs pointing to users_public
        $fixUserFk = function ($tableName, $columnName = 'user_id') {
            if (!Schema::hasTable($tableName)) {
                return;
            }

            $constraintName = $tableName . '_' . $columnName . '_foreign';

            // Drop existing FK if present (IF EXISTS is safe in PostgreSQL)
            DB::statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");

            // Delete orphaned records that would violate the new FK constraint
            DB::statement("DELETE FROM \"{$tableName}\" WHERE \"{$columnName}\" NOT IN (SELECT id FROM users_public)");

            // Re-add FK (safe — we just dropped it above)
            if (!$this->foreignKeyExists($tableName, $constraintName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                    $table->foreign($columnName)->references('id')->on('users_public')->onDelete('cascade');
                });
            }
        };

        // 2. Fix FKs for related tables
        $fixUserFk('reports', 'user_id');
        $fixUserFk('verifications', 'user_id');
        $fixUserFk('notifications', 'user_id');
        $fixUserFk('user_flags', 'user_id');
        $fixUserFk('user_restrictions', 'user_id');
    }

    /**
     * Check if a foreign key constraint exists in PostgreSQL.
     */
    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $result = DB::select(
            "SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_name = ? AND table_schema = 'public'",
            [$constraintName, $tableName]
        );
        return !empty($result);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way repair migration
    }
};
