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
        if (!Schema::hasTable('police_officers')) {
            Schema::create('police_officers', function (Blueprint $table) {
                $table->id('officer_id');
                $table->unsignedBigInteger('user_id')->unique()->comment('Linked to the police user account');
                $table->unsignedBigInteger('station_id');
                $table->date('assigned_since')->comment('Date when the officer was assigned to this station');
                $table->string('rank')->nullable()->comment('Optional - officer rank like Patrolman, Sergeant, etc.');
                $table->string('status')->default('active')->comment('active, on_leave, or retired');
                $table->timestamps();

                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users_public')->onDelete('cascade');
                $table->foreign('station_id')->references('station_id')->on('police_stations')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('police_officers');
    }
};
