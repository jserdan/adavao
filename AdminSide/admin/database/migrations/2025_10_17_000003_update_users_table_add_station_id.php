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
        $table = Schema::hasTable('users_public') ? 'users_public' : 'users';
        Schema::table($table, function (Blueprint $table) use ($table as $tableName) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn($tableName, 'station_id')) {
                $table->unsignedBigInteger('station_id')->nullable()->after('longitude')->comment('Only for police users');
                
                // Add foreign key
                $table->foreign('station_id')->references('station_id')->on('police_stations')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Schema::hasTable('users_public') ? 'users_public' : 'users';
        Schema::table($table, function (Blueprint $table) use ($table as $tableName) {
            if (Schema::hasColumn($tableName, 'station_id')) {
                $table->dropForeign(['station_id']);
                $table->dropColumn('station_id');
            }
        });
    }
};
