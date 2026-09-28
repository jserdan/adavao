<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = Schema::hasTable('users_public') ? 'users_public' : 'users';

        // Add column if it doesn't exist
        if (!Schema::hasColumn($tableName, 'station_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('station_id')->nullable()->after('longitude')->comment('Only for police users');
            });
        }

        // Add foreign key only if it doesn't already exist
        // (In PostgreSQL, a failed SQL aborts the entire transaction — try/catch won't help)
        $fkName = $tableName . '_station_id_foreign';
        $fkExists = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = ? AND table_name = ? AND table_schema = 'public'",
            [$fkName, $tableName]
        );
        if (empty($fkExists)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('station_id')->references('station_id')->on('police_stations')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('users_public') ? 'users_public' : 'users';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'station_id')) {
                $table->dropForeign(['station_id']);
                $table->dropColumn('station_id');
            }
        });
    }
};
