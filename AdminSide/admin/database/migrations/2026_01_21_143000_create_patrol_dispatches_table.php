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
        // Create patrol_dispatches table
        Schema::create('patrol_dispatches', function (Blueprint $table) {
            $table->id('dispatch_id');
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('station_id');
            $table->unsignedBigInteger('patrol_officer_id')->nullable();
            
            // Dispatch Status
            $table->string('status', 50)->default('pending'); // pending, accepted, declined, en_route, arrived, completed, cancelled
            
            // Timestamps for 3-minute rule tracking
            $table->timestamp('dispatched_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Response Times (in seconds)
            $table->integer('acceptance_time')->nullable(); // Time to accept dispatch
            $table->integer('response_time')->nullable(); // Time from dispatch to arrival
            $table->integer('completion_time')->nullable(); // Time from arrival to completion
            
            // 3-Minute Rule Compliance
            $table->boolean('three_minute_rule_met')->nullable();
            $table->integer('three_minute_rule_time')->nullable(); // Actual time taken (seconds)
            
            // Validation
            $table->boolean('is_valid')->nullable();
            $table->text('validation_notes')->nullable();
            $table->timestamp('validated_at')->nullable();
            
            // Metadata
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->text('decline_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('report_id')->references('report_id')->on('reports')->onDelete('cascade');
            $table->foreign('station_id')->references('station_id')->on('police_stations')->onDelete('cascade');
            $table->foreign('patrol_officer_id')->references('id')->on('users_public')->onDelete('set null');
            $table->foreign('dispatched_by')->references('id')->on('user_admin')->onDelete('set null');
            
            // Indexes
            $table->index('report_id');
            $table->index('patrol_officer_id');
            $table->index('status');
            $table->index('station_id');
            $table->index('dispatched_at');
        });

        // Update users_public table for patrol officers
        Schema::table('users_public', function (Blueprint $table) {
            if (!Schema::hasColumn('users_public', 'user_role')) {
                $table->string('user_role', 50)->default('user')->after('role');
            }
            if (!Schema::hasColumn('users_public', 'assigned_station_id')) {
                $table->unsignedBigInteger('assigned_station_id')->nullable()->after('user_role');
            }
            if (!Schema::hasColumn('users_public', 'is_on_duty')) {
                $table->boolean('is_on_duty')->default(false)->after('assigned_station_id');
            }
            if (!Schema::hasColumn('users_public', 'push_token')) {
                $table->text('push_token')->nullable()->after('is_on_duty');
            }
        });

        // Add FK for assigned_station_id separately (try/catch must wrap Schema::table)
        try {
            Schema::table('users_public', function (Blueprint $table) {
                $table->foreign('assigned_station_id')->references('station_id')->on('police_stations')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Constraint already exists — safe to ignore
        }

        // Create indexes on users_public
        try {
            Schema::table('users_public', function (Blueprint $table) {
                $table->index('user_role');
                $table->index('assigned_station_id');
                $table->index('is_on_duty');
            });
        } catch (\Exception $e) {
            // Indexes may already exist — safe to ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('users_public', function (Blueprint $table) {
            $table->dropForeign(['assigned_station_id']);
            $table->dropIndex(['user_role']);
            $table->dropIndex(['assigned_station_id']);
            $table->dropIndex(['is_on_duty']);
            $table->dropColumn(['user_role', 'assigned_station_id', 'is_on_duty', 'push_token']);
        });

        Schema::dropIfExists('patrol_dispatches');
    }
};
