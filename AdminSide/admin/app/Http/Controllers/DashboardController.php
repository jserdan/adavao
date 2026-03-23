<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = auth()->user();
        
        // Item #15: Date Range Filtering
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        // Determine role using RBAC methods
        $userRole = 'guest';
        if ($user) {
            if ($user->email === 'alertdavao.ph@gmail.com') {
                $userRole = 'super_admin';
            } elseif (method_exists($user, 'hasRole')) {
                if ($user->hasRole('police')) {
                    $userRole = 'police';
                } elseif ($user->hasRole('admin') || $user->hasRole('super_admin')) {
                    $userRole = 'admin';
                }
            } elseif (isset($user->role)) {
                $userRole = $user->role;
            }
        }
        
        $isSuperAdmin = ($userRole === 'super_admin');
        $stationId = $user->station_id ?? 'all';
        
        // Item #15: Include dates in cache key if filtering is active
        $cacheKey = 'dashboard_stats_' . $userRole . '_' . $stationId;
        if ($dateFrom || $dateTo) {
            $cacheKey .= '_' . ($dateFrom ?? 'x') . '_' . ($dateTo ?? 'x');
        }
        
        $stats = Cache::remember($cacheKey, 300, function() use ($isSuperAdmin, $userRole, $user, $dateFrom, $dateTo) {
            $reportsQuery = DB::table('reports');
            
            // Apply Date Filter if present
            if ($dateFrom) {
                $reportsQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $reportsQuery->whereDate('created_at', '<=', $dateTo);
            }
            
            // Helper to get count based on base query constraints
            $getCount = function($status = null) use ($reportsQuery, $isSuperAdmin, $userRole, $user) {
                $q = clone $reportsQuery;
                
                if ($status) {
                    $q->where('status', $status);
                }
                
                if (!$isSuperAdmin) {
                    if ($userRole === 'police') {
                        $stationId = $user->station_id;
                        if ($stationId) {
                            $q->where('assigned_station_id', $stationId);
                        } else {
                            return 0; 
                        }
                    } else {
                        // Admin: Only assigned reports
                        $q->whereNotNull('assigned_station_id');
                    }
                }
                
                return $q->count();
            };

            return [
                'urgentPending' => $getCount('pending') > 0 ? (function() use ($reportsQuery, $isSuperAdmin, $userRole, $user) {
                    try {
                        $q = clone $reportsQuery;
                        $q->where('status', 'pending');
                        // Focus Crimes are considered urgent
                        $q->where('is_focus_crime', true);
                        
                        if (!$isSuperAdmin) {
                            if ($userRole === 'police') {
                                $stationId = $user->station_id;
                                if ($stationId) $q->where('assigned_station_id', $stationId);
                            } else {
                                $q->whereNotNull('assigned_station_id');
                            }
                        }
                        return $q->count();
                    } catch (\Exception $e) {
                        // Fallback if column doesn't exist yet
                        return 0;
                    }
                })() : 0,
                'totalReports' => $getCount(),
                'pendingReports' => $getCount('pending'),
                'investigatingReports' => $getCount('investigating'),
                'resolvedReports' => $getCount('resolved'),
                'activeInvestigations' => $getCount('investigating'),
                'reportsToday' => DB::table('reports')
                    ->when($userRole === 'police' && $user->station_id, function($q) use ($user) {
                        return $q->where('assigned_station_id', $user->station_id);
                    })
                    ->when($dateFrom, function($q) use ($dateFrom) {
                         // Apply date filter if set, otherwise "Today" logic is usually implied by context but here we want literally TODAY unless overridden
                         if($dateFrom) return $q->whereDate('created_at', '>=', $dateFrom);
                    })
                    ->whereDate('created_at', \Carbon\Carbon::today())
                    ->count(),
                'solvedThisMonth' => DB::table('reports')
                    ->when($userRole === 'police' && $user->station_id, function($q) use ($user) {
                        return $q->where('assigned_station_id', $user->station_id);
                    })
                    ->where('status', 'resolved')
                    ->whereMonth('updated_at', \Carbon\Carbon::now()->month)
                    ->whereYear('updated_at', \Carbon\Carbon::now()->year)
                    ->count(),
                'unreadMessages' => 0 // Temporarily disabled until is_read column is added
            ];
        });
        
        // Extract stats
        $totalReports = $stats['totalReports'];
        $pendingReports = $stats['pendingReports'];
        $investigatingReports = $stats['investigatingReports'];
        $resolvedReports = $stats['resolvedReports'];
        $reportsToday = $stats['reportsToday'];
        $unreadMessages = $stats['unreadMessages'];
        $urgentPending = $stats['urgentPending'] ?? 0;
        $activeInvestigations = $stats['activeInvestigations'] ?? 0;
        $solvedThisMonth = $stats['solvedThisMonth'] ?? 0;
        
        // Cache sidebar/overview counts for 60 seconds to avoid running on every request
        $sidebarStats = Cache::remember('dashboard_sidebar_counts', 60, function() {
            return [
                'totalUsers' => DB::table('users_public')->count(),
                'totalPoliceOfficers' => DB::table('user_admin')
                    ->join('user_admin_roles', 'user_admin.id', '=', 'user_admin_roles.user_admin_id')
                    ->join('roles', 'user_admin_roles.role_id', '=', 'roles.role_id')
                    ->where('roles.role_name', 'police')
                    ->count(),
                'flaggedUsersCount' => DB::table('users_public')
                    ->where('total_flags', '>', 0)
                    ->orWhere('restriction_level', '!=', 'none')
                    ->count(),
                'pendingVerificationsCount' => DB::table('verifications')
                    ->where('status', 'pending')
                    ->count(),
                'fakeReports' => DB::table('reports')
                    ->where('is_valid', 'invalid')
                    ->count(),
            ];
        });
        
        $totalUsers = $sidebarStats['totalUsers'];
        $totalPoliceOfficers = $sidebarStats['totalPoliceOfficers'];
        $flaggedUsersCount = $sidebarStats['flaggedUsersCount'];
        $pendingVerificationsCount = $sidebarStats['pendingVerificationsCount'];
        $fakeReports = $sidebarStats['fakeReports'];
        
        return view('welcome', compact(
            'userRole',
            'totalReports',
            'pendingReports',
            'investigatingReports',
            'resolvedReports',
            'reportsToday',
            'unreadMessages',
            'totalUsers',
            'totalPoliceOfficers',
            'flaggedUsersCount',
            'pendingVerificationsCount',
            'urgentPending',
            'activeInvestigations',
            'solvedThisMonth',
            'fakeReports',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Get crime data for charts and analytics
     */
    public function getCrimeData(Request $request)
    {
        $user = auth()->user();
        $stationId = $user->station_id ?? null;
        $isSuperAdmin = $user && $user->email === 'alertdavao.ph@gmail.com';

        $cacheKey = 'crime_data_' . ($isSuperAdmin ? 'all' : ($stationId ?? 'none'));
        
        $data = Cache::remember($cacheKey, 300, function() use ($stationId, $isSuperAdmin) {
            // Crime types breakdown
            $crimeTypes = DB::table('reports')
                ->select('crime_type', DB::raw('COUNT(*) as count'))
                ->when(!$isSuperAdmin && $stationId, function($q) use ($stationId) {
                    return $q->where('assigned_station_id', $stationId);
                })
                ->whereNotNull('crime_type')
                ->groupBy('crime_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // Monthly trend (last 12 months)
            $monthlyTrend = DB::table('reports')
                ->select(
                    DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
                    DB::raw('COUNT(*) as count')
                )
                ->when(!$isSuperAdmin && $stationId, function($q) use ($stationId) {
                    return $q->where('assigned_station_id', $stationId);
                })
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
                ->orderBy('month')
                ->get();

            // Status distribution
            $statusDistribution = DB::table('reports')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->when(!$isSuperAdmin && $stationId, function($q) use ($stationId) {
                    return $q->where('assigned_station_id', $stationId);
                })
                ->groupBy('status')
                ->get();

            return [
                'crimeTypes' => $crimeTypes,
                'monthlyTrend' => $monthlyTrend,
                'statusDistribution' => $statusDistribution
            ];
        });

        return response()->json($data);
    }
}
