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
            // Add columns if they don't exist
            if (!Schema::hasColumn($tableName, 'total_flags')) {
                $table->integer('total_flags')->default(0);
            }
            if (!Schema::hasColumn($tableName, 'restriction_level')) {
                $table->string('restriction_level')->default('none');
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
            if (Schema::hasColumn($tableName, 'total_flags')) {
                $table->dropColumn('total_flags');
            }
            if (Schema::hasColumn($tableName, 'restriction_level')) {
                $table->dropColumn('restriction_level');
            }
        });
    }
};
