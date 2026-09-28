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
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0)->after('remember_token');
            }
            if (!Schema::hasColumn($tableName, 'lockout_until')) {
                $table->timestamp('lockout_until')->nullable()->after('failed_login_attempts');
            }
            if (!Schema::hasColumn($tableName, 'last_failed_login')) {
                $table->timestamp('last_failed_login')->nullable()->after('lockout_until');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('users_public') ? 'users_public' : 'users';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $cols = array_filter(['failed_login_attempts', 'lockout_until', 'last_failed_login'], function($c) use ($tableName) {
                return Schema::hasColumn($tableName, $c);
            });
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
