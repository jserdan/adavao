<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix report_reassignment_requests foreign keys.
 * The original migration incorrectly referenced 'users' table,
 * but admin/police users are stored in 'user_admin' table.
 */
return new class extends Migration
{
    /**
     * PostgreSQL aborts entire transaction on any error — run without transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First check if the table exists
        if (!Schema::hasTable('report_reassignment_requests')) {
            // If table doesn't exist, create it with correct FKs
            Schema::create('report_reassignment_requests', function (Blueprint $table) {
                $table->id('request_id');
                $table->unsignedBigInteger('report_id');
                $table->unsignedBigInteger('requested_by_user_id');
                $table->unsignedBigInteger('current_station_id')->nullable();
                $table->unsignedBigInteger('requested_station_id');
                $table->string('reason', 500)->nullable();
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                // Foreign keys - referencing user_admin instead of users
                $table->foreign('report_id')->references('report_id')->on('reports')->onDelete('cascade');
                $table->foreign('requested_by_user_id')->references('id')->on('user_admin')->onDelete('cascade');
                $table->foreign('current_station_id')->references('station_id')->on('police_stations')->onDelete('set null');
                $table->foreign('requested_station_id')->references('station_id')->on('police_stations')->onDelete('cascade');
                $table->foreign('reviewed_by_user_id')->references('id')->on('user_admin')->onDelete('set null');
            });
            return;
        }

        // Table exists - fix the foreign keys using safe DROP IF EXISTS
        $tableName = 'report_reassignment_requests';

        // Drop existing FKs safely (IF EXISTS prevents errors)
        DB::statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$tableName}_requested_by_user_id_foreign\"");
        DB::statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$tableName}_reviewed_by_user_id_foreign\"");

        // Re-add correct foreign keys to user_admin table
        $fk1Exists = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = ? AND table_name = ? AND table_schema = 'public'",
            [$tableName . '_requested_by_user_id_foreign', $tableName]
        );
        if (empty($fk1Exists)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('requested_by_user_id')
                    ->references('id')
                    ->on('user_admin')
                    ->onDelete('cascade');
            });
        }

        $fk2Exists = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = ? AND table_name = ? AND table_schema = 'public'",
            [$tableName . '_reviewed_by_user_id_foreign', $tableName]
        );
        if (empty($fk2Exists)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('reviewed_by_user_id')
                    ->references('id')
                    ->on('user_admin')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'report_reassignment_requests';
        DB::statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$tableName}_requested_by_user_id_foreign\"");
        DB::statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT IF EXISTS \"{$tableName}_reviewed_by_user_id_foreign\"");
        // Note: We don't restore the old incorrect FKs
    }
};
