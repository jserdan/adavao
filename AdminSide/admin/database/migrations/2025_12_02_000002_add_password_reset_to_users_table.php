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
            if (!Schema::hasColumn($tableName, 'reset_token')) {
                $table->string('reset_token', 100)->nullable()->after('token_expires_at');
            }
            if (!Schema::hasColumn($tableName, 'reset_token_expires_at')) {
                $table->timestamp('reset_token_expires_at')->nullable()->after('reset_token');
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
            if (Schema::hasColumn($tableName, 'reset_token')) {
                $table->dropColumn('reset_token');
            }
            if (Schema::hasColumn($tableName, 'reset_token_expires_at')) {
                $table->dropColumn('reset_token_expires_at');
            }
        });
    }
};
