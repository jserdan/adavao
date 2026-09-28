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
            $table->double('latitude')->nullable()->change();
            $table->double('longitude')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Schema::hasTable('users_public') ? 'users_public' : 'users';
        Schema::table($table, function (Blueprint $table) {
            $table->double('latitude')->nullable(false)->change();
            $table->double('longitude')->nullable(false)->change();
        });
    }
};