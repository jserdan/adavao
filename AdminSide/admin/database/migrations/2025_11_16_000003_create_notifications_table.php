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
        // Check if table already exists
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('type'); // e.g., 'user_flagged', 'restriction_applied', etc.
                $table->text('message');
                $table->json('data')->nullable(); // Store additional data like flag_id, violation_type, etc.
                $table->boolean('read')->default(false);
                $table->timestamps();
                
                $table->foreign('user_id')->references('id')->on('users_public')->onDelete('cascade');
                $table->index('user_id');
                $table->index('read');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};