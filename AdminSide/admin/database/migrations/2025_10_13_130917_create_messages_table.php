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
        Schema::create('messages', function (Blueprint $table) {
            $table->id('message_id');
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->unsignedBigInteger('report_id')->nullable();
            $table->text('message');
            $table->boolean('status')->default(false)->comment('True if the recipient has read the message');
            $table->datetime('sent_at');
            $table->timestamps();
            
            $table->foreign('sender_id')->references('id')->on('users_public')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users_public')->onDelete('cascade');
            $table->foreign('report_id')->references('report_id')->on('reports')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
