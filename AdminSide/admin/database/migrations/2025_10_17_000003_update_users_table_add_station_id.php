<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        // Add foreign key separately (try/catch must wrap Schema::table, not the Blueprint call)
        try {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('station_id')->references('station_id')->on('police_stations')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Constraint already exists — safe to ignore
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
