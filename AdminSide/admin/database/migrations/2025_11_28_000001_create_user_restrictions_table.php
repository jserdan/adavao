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
        if (Schema::hasTable('user_restrictions')) {
            return; // Table already exists, skip
        }

        Schema::create('user_restrictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('restriction_type')->default('warning');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('restricted_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('can_report')->default(true);
            $table->boolean('can_message')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lifted_by')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users_public')->onDelete('cascade');
            $table->foreign('restricted_by')->references('id')->on('users_public')->onDelete('set null');
            $table->foreign('lifted_by')->references('id')->on('users_public')->onDelete('set null');

            // Indexes
            $table->index('user_id');
            $table->index('is_active');
            $table->index('restriction_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_restrictions');
    }
};
