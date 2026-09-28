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
        if (Schema::hasTable('user_flags')) {
            return; // Table already exists, skip
        }

        Schema::create('user_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('violation_type');
            $table->text('description')->nullable();
            $table->string('status')->default('confirmed');
            $table->string('severity')->default('medium');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users_public')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users_public')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users_public')->onDelete('set null');

            // Indexes
            $table->index('user_id');
            $table->index('reported_by');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_flags');
    }
};
