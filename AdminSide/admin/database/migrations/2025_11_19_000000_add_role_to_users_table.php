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
        if (!Schema::hasColumn($table, 'role')) {
            Schema::table($table, function (Blueprint $table) {
                // Add role field with default 'user' value
                $table->string('role')->default('user')->after('is_verified');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Schema::hasTable('users_public') ? 'users_public' : 'users';
        if (Schema::hasColumn($table, 'role')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
