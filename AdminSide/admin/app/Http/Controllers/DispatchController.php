<?php

namespace App\Http\Controllers;

use App\Models\PatrolDispatch;
use App\Models\Report;
use App\Models\User;
use App\Models\PoliceStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\EncryptionService;

class DispatchController extends Controller
{
    /**
     * Display all dispatches
     */
    public function index(Request $request)
    {
        $query = PatrolDispatch::with(['report.user', 'station', 'patrolOfficer', 'dispatcher'])
            ->orderBy('dispatched_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by station
        if ($request->has('station_id')) {
            $query->where('station_id', $request->station_id);
        }

        // Filter by officer
        if ($request->has('officer_id')) {
            $query->where('patrol_officer_id', $request->officer_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('dispatched_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('dispatched_at', '<=', $request->date_to);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Search by Report ID or Title
                $q->whereHas('report', function($rq) use ($search) {
                    $rq->where('report_id', 'like', "%$search%")
                       ->orWhere('title', 'like', "%$search%")
                       // Search by Reporter Name
                       ->orWhereHas('user', function($uq) use ($search) {
                           $uq->where('firstname', 'like', "%$search%")
                              ->orWhere('lastname', 'like', "%$search%");
                       });
                })
                // Search by Patrol Officer Name
                ->orWhereHas('patrolOfficer', function($pq) use ($search) {
                    $pq->where('firstname', 'like', "%$search%")
                       ->orWhere('lastname', 'like', "%$search%");
                });
            });
        }

        $dispatches = $query->paginate(20)->withQueryString();

        // 🔓 Decrypt sensitive fields in reports for display
        // Note: Only description, barangay, and reporters_address are encrypted (not title)
        foreach ($dispatches as $dispatch) {
            if ($dispatch->report) {
                $dispatch->report->description = EncryptionService::decrypt($dispatch->report->description);
                if ($dispatch->report->location) {
                    $dispatch->report->location->barangay = EncryptionService::decrypt($dispatch->report->location->barangay);
                    $dispatch->report->location->reporters_address = EncryptionService::decrypt($dispatch->report->location->reporters_address);
                }
            }
        }

        // Get statistics
        $stats = [
            'total' => PatrolDispatch::count(),
            'active' => PatrolDispatch::active()->count(),
            'completed' => PatrolDispatch::completed()->count(),
            'three_minute_compliance' => $this->getThreeMinuteCompliance(),
            'avg_response_time' => $this->getAverageResponseTime(),
        ];

        // Get available officers
                $officers = User::whereRaw("LOWER(COALESCE(user_role::text, role::text, '')) = ?", ['patrol_officer'])
                        ->where('is_on_duty', true)
                        ->whereDoesntHave('patrolDispatches', function($q) {
                            $q->whereIn('status', ['pending', 'accepted', 'en_route', 'arrived']);
                        })
                        ->get();

        $stations = PoliceStation::all();

        return view('dispatches', compact('dispatches', 'stats', 'officers', 'stations'));
    }

    /**
     * Create new dispatch from report
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'report_id' => 'required|exists:reports,report_id',
                'patrol_officer_id' => 'nullable|exists:users_public,id',
                'notes' => 'nullable|string',
            ]);

            $report = Report::findOrFail($request->report_id);

            // Check if report already has an active dispatch
            $existingDispatch = PatrolDispatch::where('report_id', $report->report_id)
                ->active()
                ->first();

            if ($existingDispatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'This report already has an active dispatch'
                ], 400);
            }

            $dispatch = PatrolDispatch::create([
                'report_id' => $report->report_id,
                'station_id' => $report->assigned_station_id,
                'patrol_officer_id' => $request->patrol_officer_id,
                'status' => $request->patrol_officer_id ? 'assigned' : 'pending',
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Update report status to investigating when dispatch is created
            $report->status = 'investigating';
            $report->save();

            // Load relationships BEFORE sending notification (required for notification content)
            $dispatch->load(['report.location', 'patrolOfficer']);

            // Send notification to patrol officer
            if ($dispatch->patrol_officer_id) {
                $this->sendDispatchNotification($dispatch);
            }

            // Sync dispatch to UserSide backend (safety for multi-DB deployments)
            $this->syncDispatchToUserSide($dispatch->report_id, auth()->id(), $dispatch->notes, $dispatch->patrol_officer_id);

            Log::info('Dispatch created', [
                'dispatch_id' => $dispatch->dispatch_id,
                'report_id' => $report->report_id,
                'officer_id' => $dispatch->patrol_officer_id,
            ]);

            // Decrypt for response
            $this->decryptDispatchReport($dispatch);

            return response()->json([
                'success' => true,
                'message' => 'Dispatch created successfully',
                'dispatch' => $dispatch,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create dispatch: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create dispatch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update dispatch status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:accepted,declined,en_route,arrived,completed,cancelled',
                'decline_reason' => 'required_if:status,declined',
                'cancellation_reason' => 'required_if:status,cancelled',
                'validation_notes' => 'nullable|string',
                'is_valid' => 'nullable|boolean',
            ]);

            $dispatch = PatrolDispatch::findOrFail($id);
            $oldStatus = $dispatch->status;
            $dispatch->status = $request->status;

            // Update timestamps based on status
            switch ($request->status) {
                case 'accepted':
                    $dispatch->accepted_at = now();
                    $dispatch->calculateAcceptanceTime();
                    break;

                case 'declined':
                    $dispatch->declined_at = now();
                    $dispatch->decline_reason = $request->decline_reason;
                    break;

                case 'en_route':
                    $dispatch->en_route_at = now();
                    break;

                case 'arrived':
                    $dispatch->arrived_at = now();
                    $dispatch->calculateResponseTime(); // This also calculates 3-minute rule
                    break;

                case 'completed':
                    $dispatch->completed_at = now();
                    $dispatch->calculateCompletionTime();
                    if ($request->has('is_valid')) {
                        $dispatch->is_valid = $request->is_valid;
                        $dispatch->validation_notes = $request->validation_notes;
                        $dispatch->validated_at = now();
                        
                        // Update report validity and status based on patrol feedback
                        $report = $dispatch->report;
                        if ($request->is_valid) {
                            // Valid report: keep investigating, mark as valid
                            $report->is_valid = 'valid';
                            $report->validated_at = now();
                        } else {
                            // Fake/invalid report: resolve it
                            $report->is_valid = 'invalid';
                            $report->status = 'resolved';
                            $report->validated_at = now();
                        }
                        $report->save();
                    }
                    break;

                case 'cancelled':
                    $dispatch->cancelled_at = now();
                    $dispatch->cancellation_reason = $request->cancellation_reason;
                    break;
            }

            $dispatch->save();

            Log::info('Dispatch status updated', [
                'dispatch_id' => $dispatch->dispatch_id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
            ]);

            // Load relationships and decrypt for response
            $dispatch = $dispatch->fresh()->load(['report.location', 'patrolOfficer']);
            $this->decryptDispatchReport($dispatch);

            return response()->json([
                'success' => true,
                'message' => 'Dispatch status updated successfully',
                'dispatch' => $dispatch,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update dispatch status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update dispatch status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign dispatch to patrol officer
     */
    public function assign(Request $request, $id)
    {
        try {
            $request->validate([
                'patrol_officer_id' => 'required|exists:users_public,id',
            ]);

            $dispatch = PatrolDispatch::findOrFail($id);
            $dispatch->patrol_officer_id = $request->patrol_officer_id;
            $dispatch->save();

            // Send notification
            $this->sendDispatchNotification($dispatch);

            // Sync dispatch to UserSide backend (safety for multi-DB deployments)
            $this->syncDispatchToUserSide($dispatch->report_id, auth()->id(), null, $dispatch->patrol_officer_id);

            return response()->json([
                'success' => true,
                'message' => 'Dispatch assigned successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign dispatch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel dispatch
     */
    public function cancel(Request $request, $id)
    {
        try {
            $request->validate([
                'cancellation_reason' => 'required|string',
            ]);

            $dispatch = PatrolDispatch::findOrFail($id);
            $dispatch->status = 'cancelled';
            $dispatch->cancelled_at = now();
            $dispatch->cancellation_reason = $request->cancellation_reason;
            $dispatch->save();

            return response()->json([
                'success' => true,
                'message' => 'Dispatch cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel dispatch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics
     */
    public function analytics(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30));
        $dateTo = $request->get('date_to', Carbon::now());

        $dispatches = PatrolDispatch::whereBetween('dispatched_at', [$dateFrom, $dateTo])->get();

        $analytics = [
            'total_dispatches' => $dispatches->count(),
            'completed' => $dispatches->where('status', 'completed')->count(),
            'three_minute_compliance_rate' => $this->calculateComplianceRate($dispatches),
            'avg_response_time' => $this->calculateAvgResponseTime($dispatches),
            'avg_completion_time' => $this->calculateAvgCompletionTime($dispatches),
            'officer_performance' => $this->getOfficerPerformance($dispatches),
            'hourly_distribution' => $this->getHourlyDistribution($dispatches),
        ];

        return response()->json($analytics);
    }

    /**
     * Get on-duty patrol officers
     */
    public function getOnDutyOfficers()
    {
        // Use raw SQL to join with patrol_locations for better accuracy
        $officers = DB::select("
            SELECT 
                u.id,
                CONCAT(u.firstname, ' ', u.lastname) as name,
                u.push_token,
                ps.station_name,
                pl.latitude,
                pl.longitude,
                pl.updated_at as location_updated_at,
            CASE 
                WHEN pl.updated_at > NOW() - INTERVAL '10 minutes' THEN true 
                ELSE false 
            END as has_recent_location,
            u.is_on_duty
        FROM users_public u
        LEFT JOIN police_stations ps ON u.assigned_station_id = ps.station_id
        LEFT JOIN patrol_locations pl ON u.id = pl.user_id
        WHERE LOWER(COALESCE(u.user_role::text, u.role::text, '')) = 'patrol_officer'
        ORDER BY u.is_on_duty DESC, pl.updated_at DESC NULLS LAST
    ");

        return response()->json(['officers' => $officers]);
    }

    /**
     * Auto dispatch to nearest patrol officer
     */
    public function autoDispatch(Request $request)
    {
        try {
            $request->validate([
                'report_id' => 'required|exists:reports,report_id',
                'notes' => 'nullable|string|max:500',
            ]);

            $report = Report::with('location')->findOrFail($request->report_id);

            // Check if report already has an active dispatch
            $existingDispatch = PatrolDispatch::where('report_id', $report->report_id)
                ->active()
                ->first();

            if ($existingDispatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'This report already has an active dispatch'
                ], 400);
            }

            // Create broadcast dispatch — no specific officer assigned
            // All patrol officers will see it and can accept (first-come-first-served)
            $dispatch = PatrolDispatch::create([
                'report_id' => $report->report_id,
                'station_id' => $report->assigned_station_id,
                'patrol_officer_id' => null,
                'status' => 'pending',
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Load the dispatch with its relationships
            $dispatch->load(['report.location']);

            // Send push notification to ALL patrol officers
            $allOfficers = DB::select("
                SELECT u.id, u.push_token
                FROM users_public u
                WHERE LOWER(COALESCE(u.user_role::text, u.role::text, '')) = 'patrol_officer'
                  AND u.push_token IS NOT NULL AND u.push_token != ''
            ");

            $notifiedCount = 0;
            foreach ($allOfficers as $officer) {
                if (!empty($officer->push_token)) {
                    try {
                        $this->sendBroadcastNotification($dispatch, $officer);
                        $notifiedCount++;
                    } catch (\Exception $e) {
                        Log::warning('Failed to notify officer', ['id' => $officer->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            // Sync dispatch to UserSide backend
            $this->syncDispatchToUserSide($dispatch->report_id, auth()->id(), $request->notes, null);

            Log::info('Broadcast dispatch created', [
                'dispatch_id' => $dispatch->dispatch_id,
                'report_id' => $report->report_id,
                'officers_notified' => $notifiedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Dispatch broadcast to all patrol officers. {$notifiedCount} notified.",
                'notification_sent' => $notifiedCount > 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to auto-dispatch: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to dispatch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send broadcast notification to a patrol officer
     */
    private function sendBroadcastNotification($dispatch, $officer)
    {
        $report = $dispatch->report;
        $location = $report->location ?? null;
        $barangay = $location ? EncryptionService::decrypt($location->barangay) : 'Unknown';

        $message = [
            'to' => $officer->push_token,
            'sound' => 'default',
            'title' => '🚨 New Dispatch Alert',
            'body' => ($report->report_type ?? 'Report') . ' at ' . $barangay,
            'data' => [
                'type' => 'dispatch',
                'dispatch_id' => $dispatch->dispatch_id,
                'report_id' => $report->report_id,
            ],
            'priority' => 'high',
            'channelId' => 'dispatch',
        ];

        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }

    private function fetchPushTokenFromBackend($officerId)
    {
        try {
            $baseUrl = rtrim(env('NODE_BACKEND_URL', 'https://adavao-1-mawm.onrender.com'), '/');
            $url = $baseUrl . '/api/user/push-token/' . $officerId;

            $headers = [
                'Accept' => 'application/json',
            ];

            if (env('DISPATCH_SYNC_KEY')) {
                $headers['x-dispatch-key'] = env('DISPATCH_SYNC_KEY');
            }

            $response = \Http::withHeaders($headers)->get($url);
            if (!$response->successful()) {
                Log::warning('Failed to fetch push token from backend', [
                    'officer_id' => $officerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            return $data['push_token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Error fetching push token from backend', [
                'officer_id' => $officerId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function syncDispatchToUserSide($reportId, $dispatcherId = null, $notes = null, $patrolOfficerId = null)
    {
        try {
            $baseUrl = rtrim(env('NODE_BACKEND_URL', 'https://adavao-1-mawm.onrender.com'), '/');
            $url = $baseUrl . '/api/dispatch/admin-sync';

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            if (env('DISPATCH_SYNC_KEY')) {
                $headers['x-dispatch-key'] = env('DISPATCH_SYNC_KEY');
            }

            $payload = [
                'reportId' => $reportId,
                'dispatcherId' => $dispatcherId,
                'notes' => $notes,
                'patrolOfficerId' => $patrolOfficerId,
            ];

            $response = \Http::withHeaders($headers)->post($url, $payload);
            if (!$response->successful()) {
                Log::warning('UserSide dispatch sync failed', [
                    'report_id' => $reportId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('UserSide dispatch sync error', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Private helper methods
     */
    private function sendDispatchNotification($dispatch)
    {
        try {
            $officer = $dispatch->patrolOfficer;
            
            if (!$officer || !$officer->push_token) {
                Log::info('No push token for officer', ['officer_id' => $dispatch->patrol_officer_id]);
                return false;
            }

            $report = $dispatch->report;
            $barangay = ($report->location && $report->location->barangay) 
                ? EncryptionService::decrypt($report->location->barangay) 
                : 'Unknown location';
            
            // Prepare Expo push notification
            $message = [
                'to' => $officer->push_token,
                'sound' => 'default',
                'title' => '🚓 New Dispatch',
                'body' => $report->report_type . ' at ' . $barangay,
                'data' => [
                    'type' => 'dispatch',
                    'dispatch_id' => $dispatch->dispatch_id,
                    'report_id' => $report->report_id,
                    'crime_type' => $report->report_type,
                    'location' => $barangay,
                    'urgency' => $report->urgency_score ?? 0,
                ],
                'priority' => 'high',
                'channelId' => 'dispatch',
            ];

            // Send to Expo Push Notification service with proper headers
            $response = \Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post('https://exp.host/--/api/v2/push/send', $message);

            $responseBody = $response->json();
            
            Log::info('Regular dispatch push response', [
                'status' => $response->status(),
                'body' => $responseBody,
            ]);

            if ($response->successful()) {
                Log::info('Push notification sent successfully', [
                    'dispatch_id' => $dispatch->dispatch_id,
                    'officer_id' => $officer->id,
                ]);
                return true;
            } else {
                Log::error('Failed to send push notification', [
                    'dispatch_id' => $dispatch->dispatch_id,
                    'response' => $response->body(),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error sending push notification: ' . $e->getMessage());
            return false;
        }
    }

    private function getThreeMinuteCompliance()
    {
        $completed = PatrolDispatch::whereNotNull('three_minute_rule_met')->count();
        if ($completed === 0) return 0;

        $met = PatrolDispatch::where('three_minute_rule_met', true)->count();
        return round(($met / $completed) * 100, 1);
    }

    private function getAverageResponseTime()
    {
        $avg = PatrolDispatch::whereNotNull('response_time')->avg('response_time');
        return $avg ? round($avg) : 0;
    }

    private function calculateComplianceRate($dispatches)
    {
        $withRule = $dispatches->whereNotNull('three_minute_rule_met');
        if ($withRule->count() === 0) return 0;

        $met = $withRule->where('three_minute_rule_met', true)->count();
        return round(($met / $withRule->count()) * 100, 1);
    }

    private function calculateAvgResponseTime($dispatches)
    {
        $avg = $dispatches->whereNotNull('response_time')->avg('response_time');
        return $avg ? round($avg) : 0;
    }

    private function calculateAvgCompletionTime($dispatches)
    {
        $avg = $dispatches->whereNotNull('completion_time')->avg('completion_time');
        return $avg ? round($avg) : 0;
    }

    private function getOfficerPerformance($dispatches)
    {
        return $dispatches->groupBy('patrol_officer_id')->map(function ($group) {
            return [
                'officer_id' => $group->first()->patrol_officer_id,
                'officer_name' => $group->first()->patrolOfficer->name ?? 'Unknown',
                'total_dispatches' => $group->count(),
                'completed' => $group->where('status', 'completed')->count(),
                'avg_response_time' => round($group->whereNotNull('response_time')->avg('response_time') ?? 0),
                'three_minute_compliance' => $this->calculateComplianceRate($group),
            ];
        })->values();
    }

    private function getHourlyDistribution($dispatches)
    {
        return $dispatches->groupBy(function ($dispatch) {
            return $dispatch->dispatched_at->format('H');
        })->map(function ($group, $hour) {
            return [
                'hour' => $hour,
                'count' => $group->count(),
            ];
        })->values();
    }

    /**
     * Decrypt sensitive fields in a dispatch's report for display
     * Note: Only description, barangay, and reporters_address are encrypted (not title)
     */
    private function decryptDispatchReport($dispatch)
    {
        if ($dispatch->report) {
            // Only description is encrypted, not title
            $dispatch->report->description = EncryptionService::decrypt($dispatch->report->description);
            if ($dispatch->report->location) {
                $dispatch->report->location->barangay = EncryptionService::decrypt($dispatch->report->location->barangay);
                $dispatch->report->location->reporters_address = EncryptionService::decrypt($dispatch->report->location->reporters_address);
            }
        }
        return $dispatch;
    }
}
