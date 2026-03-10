<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ReportTimeline;

class Report extends Model
{
    use HasFactory;

    protected $primaryKey = 'report_id';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'report_type',
        'location_id',
        'assigned_station_id',
        'status',
        'is_valid',
        'validated_at',
        'is_anonymous',
        'date_reported',
        'is_focus_crime',
        'has_sufficient_info',
        'urgency_score',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_focus_crime' => 'boolean',
        'has_sufficient_info' => 'boolean',
        'date_reported' => 'datetime',
        'validated_at' => 'datetime',
        // encryption reverted to fix 500 error
    ];

    /**
     * Get the user that created the report
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the location of the report
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    /**
     * Get the police station assigned to handle this report
     */
    public function policeStation()
    {
        return $this->belongsTo(PoliceStation::class, 'assigned_station_id', 'station_id');
    }

    /**
     * Get the messages related to this report
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'report_id');
    }

    /**
     * Get the media files for this report
     */
    public function media()
    {
        return $this->hasMany(ReportMedia::class, 'report_id', 'report_id');
    }

    public function timelines()
    {
        return $this->hasMany(ReportTimeline::class, 'report_id', 'report_id')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get the patrol dispatch for this report
     */
    public function dispatch()
    {
        return $this->hasOne(PatrolDispatch::class, 'report_id', 'report_id');
    }

    /**
     * Auto-assign police station based on report location coordinates
     * 
     * @param float $latitude
     * @param float $longitude
     * @return int|null The assigned station ID
     */
    public static function autoAssignPoliceStation(float $latitude, float $longitude): ?int
    {
        // First, try to find which barangay this point falls into
        $barangay = \App\Helpers\GeoHelper::findBarangayByCoordinates($latitude, $longitude);

        if ($barangay && $barangay->station_id) {
            return $barangay->station_id;
        }

        // AUTO-ASSIGNMENT UPDATE:
        // If not inside any polygon, return null (remain unassigned)
        return null;
    }

    /**
     * Assign this report to a police station based on location
     * 
     * @return bool True if assignment was successful
     */
    public function assignToStation(): bool
    {
        if (!$this->location) {
            return false;
        }

        $stationId = self::autoAssignPoliceStation(
            $this->location->latitude,
            $this->location->longitude
        );

        if ($stationId) {
            $this->assigned_station_id = $stationId;
            return $this->save();
        }

        return false;
    }
}