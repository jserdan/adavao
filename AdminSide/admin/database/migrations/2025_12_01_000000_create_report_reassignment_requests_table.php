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
        Schema::create('report_reassignment_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('current_station_id')->nullable();
            $table->unsignedBigInteger('requested_station_id');
            $table->string('reason', 500)->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('report_id')->references('report_id')->on('reports')->onDelete('cascade');
            $table->foreign('requested_by_user_id')->references('id')->on('users_public')->onDelete('cascade');
            $table->foreign('current_station_id')->references('station_id')->on('police_stations')->onDelete('set null');
            $table->foreign('requested_station_id')->references('station_id')->on('police_stations')->onDelete('cascade');
            $table->foreign('reviewed_by_user_id')->references('id')->on('users_public')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_reassignment_requests');
    }
};
