<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PatrolDispatch extends Model
{
    protected $table = 'patrol_dispatches';
    protected $primaryKey = 'dispatch_id';

    protected $fillable = [
        'report_id',
        'station_id',
        'patrol_officer_id',
        'status',
        'dispatched_at',
        'accepted_at',
        'declined_at',
        'en_route_at',
        'arrived_at',
        'completed_at',
        'cancelled_at',
        'acceptance_time',
        'response_time',
        'completion_time',
        'three_minute_rule_met',
        'three_minute_rule_time',
        'is_valid',
        'validation_notes',
        'validated_at',
        'dispatched_by',
        'decline_reason',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'validated_at' => 'datetime',
        'three_minute_rule_met' => 'boolean',
        'is_valid' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id', 'report_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PoliceStation::class, 'station_id', 'station_id');
    }

    public function patrolOfficer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'patrol_officer_id', 'id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserAdmin::class, 'dispatched_by', 'id');
    }

    /**
     * Calculate and update acceptance time
     */
    public function calculateAcceptanceTime(): void
    {
        if ($this->accepted_at && $this->dispatched_at) {
            $this->acceptance_time = $this->dispatched_at->diffInSeconds($this->accepted_at);
            $this->save();
        }
    }

    /**
     * Calculate and update response time + 3-minute rule
     */
    public function calculateResponseTime(): void
    {
        if ($this->arrived_at && $this->accepted_at) {
            // Response time = acceptance to arrival (not dispatch to arrival)
            $this->response_time = $this->accepted_at->diffInSeconds($this->arrived_at);
            $this->three_minute_rule_time = $this->response_time;
            $this->three_minute_rule_met = $this->response_time <= 180; // 3 minutes = 180 seconds
            $this->save();
        }
    }

    /**
     * Calculate and update completion time
     */
    public function calculateCompletionTime(): void
    {
        if ($this->completed_at && $this->arrived_at) {
            $this->completion_time = $this->arrived_at->diffInSeconds($this->completed_at);
            $this->save();
        }
    }

    /**
     * Get time remaining for 3-minute rule (in seconds)
     * Returns negative if exceeded
     */
    public function getTimeRemainingAttribute(): int
    {
        if ($this->status === 'arrived' || $this->status === 'completed') {
            return 0; // Already arrived
        }

        if (!$this->dispatched_at) {
            return 180;
        }

        $elapsed = Carbon::now()->diffInSeconds($this->dispatched_at);
        return 180 - $elapsed;
    }

    /**
     * Check if dispatch is overdue (3-minute rule exceeded)
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->time_remaining < 0 && !in_array($this->status, ['arrived', 'completed', 'cancelled', 'declined']);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'gray',
            'accepted' => 'blue',
            'declined' => 'red',
            'en_route' => 'yellow',
            'arrived' => 'purple',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get 3-minute rule badge color
     */
    public function getThreeMinuteRuleBadgeAttribute(): string
    {
        if ($this->three_minute_rule_met === null) {
            return 'gray';
        }
        return $this->three_minute_rule_met ? 'green' : 'red';
    }

    /**
     * Scope: Active dispatches
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'accepted', 'en_route', 'arrived']);
    }

    /**
     * Scope: Completed dispatches
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: For specific officer
     */
    public function scopeForOfficer($query, $officerId)
    {
        return $query->where('patrol_officer_id', $officerId);
    }

    /**
     * Scope: For specific station
     */
    public function scopeForStation($query, $stationId)
    {
        return $query->where('station_id', $stationId);
    }
}
