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
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id('action_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('action_type');
            $table->text('description');
            $table->datetime('date_performed');
            $table->timestamps();
            
            $table->foreign('admin_id')->references('id')->on('users_public')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
