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
        Schema::table($table, function (Blueprint $table) {
            $table->string('address')->after('password');
            $table->double('latitude')->after('address')->comment('User current or registered latitude');
            $table->double('longitude')->after('latitude')->comment('User current or registered longitude');
            $table->boolean('is_verified')->default(false)->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Schema::hasTable('users_public') ? 'users_public' : 'users';
        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn(['address', 'latitude', 'longitude', 'is_verified']);
        });
    }
};
