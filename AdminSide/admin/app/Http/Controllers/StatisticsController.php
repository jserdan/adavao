<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\CrimeForecast;
use App\Models\CrimeAnalytics;

class StatisticsController extends Controller
{
    private $sarimaApiUrl;

    private const REPORT_VALID = 'valid';
    private const REPORT_INVALID = 'invalid';
    private const REPORT_CHECKING = 'checking_for_report_validity';

    public function __construct() {
        // Default to localhost:8001 for local development, Docker override via env
        $this->sarimaApiUrl = env('SARIMA_API_URL', 'http://localhost:8001');
    }

    /**
     * Check if SARIMA API is running
     * Increased timeout to 60s to handle Render cold starts
     */
    private function isSarimaApiRunning()
    {
        try {
            $response = Http::timeout(10)->get("{$this->sarimaApiUrl}/");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Auto-start SARIMA API if not running (development only)
     */
    private function autoStartSarimaApi()
    {
        if ($this->isSarimaApiRunning()) {
            return true;
        }

        // Only auto-start in local development
        if (!app()->environment('local')) {
            return false;
        }

        try {
            // Path to sarima_api/main.py relative to Laravel root (admin)
            // base_path() is .../admin, so we go up one level
            $pythonScript = base_path('../sarima_api/main.py');
            
            if (file_exists($pythonScript)) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Windows
                    pclose(popen("start /B python \"$pythonScript\"", "r"));
                } else {
                    // Linux/Mac
                    exec("python3 \"$pythonScript\" > /dev/null 2>&1 &");
                }
                
                // Wait a moment for the API to start
                sleep(5);
                return $this->isSarimaApiRunning();
            } else {
                \Log::error("SARIMA script not found at: $pythonScript");
            }
        } catch (\Exception $e) {
            \Log::error('Failed to auto-start SARIMA API: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Display the statistics page
     */
    public function index()
    {
        // SARIMA API check removed from page load to prevent blocking.
        // The frontend AJAX calls will handle API connectivity lazily.
        return view('statistics');
    }

    /**
     * Get crime forecast from pre-generated SARIMA CSV file
     * Uses sarima_forecast.csv (12 months forecast from CrimeDAta.csv)
     */

    /**
     * Pre-warm all caches
     */
    public function warmUpCache()
    {
        try {
            \Log::info('Starting cache warm-up...');
            
            // Warm up forecasts for common horizons
            foreach ([6, 12, 18, 24] as $horizon) {
                $this->_getForecast($horizon);
            }
            
            // Warm up crime stats (all time)
            $this->_getCrimeStats(null, null);
            
            // Warm up barangay stats
            $this->_getBarangayStats(null, null);
            
            \Log::info('Cache warm-up completed.');
            return true;
        } catch (\Exception $e) {
            \Log::error('Cache warm-up failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getForecast(Request $request)
    {
        $horizon = $request->input('horizon', 12);
        $crimeType = $request->input('crime_type');
        $month = $request->input('month');
        $year = $request->input('year');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            // Ensure API is running
            $this->autoStartSarimaApi();
            
            // Get forecast directly from SARIMA API for the active scope.
            // If crime_type is selected, the API returns that specific crime model output.
            $apiResponse = $this->_getForecast($horizon, $crimeType ?: null);
            
            // Base response structure
            $response = [
                'status' => 'success',
                'horizon' => $horizon,
                'model' => 'SARIMA(0,1,1)(0,1,1)[12]',
                'source' => 'Live SARIMA API'
            ];

            // If API returns a direct array (legacy/simple mode), assume it's data
            // If API returns an assoc array with keys, merge it (scalable mode)
            if (isset($apiResponse['data'])) {
                 // API follows standard { data: [...], other_metrics: ... }
                 $response = array_merge($response, $apiResponse);
            } else {
                 // API returns raw list of points
                 $response['data'] = $apiResponse;
            }

            // Always provide historical points aligned with active statistics filter/date range
            try {
                if ($dateFrom || $dateTo) {
                    $response['historical'] = $this->getDateRangeMonthlyReports($dateFrom, $dateTo, $crimeType);
                } elseif (!empty($crimeType)) {
                    $response['historical'] = $this->getCombinedHistoricalByCrimeType($crimeType, $month, $year);
                } else {
                    $historical = $this->_getCrimeStats($month, $year);
                    $response['historical'] = $historical['monthly'] ?? [];
                }
            } catch (\Throwable $e) {
                $response['historical'] = [];
            }

            // Dashboard date-range context (date_from/date_to)
            $response = $this->applyDateRangeContextScaling($response, $dateFrom, $dateTo, $crimeType);

            // Make forecast context-aware when dashboard/statistics filters are active.
            $response = $this->applyFilterContextScaling($response, $month, $year, $crimeType);

            // Real-time online adjustment using fresh submitted reports.
            // This keeps forecasts responsive when new reports arrive between retraining windows.
            $response = $this->applyLiveReportAdjustment($response, $crimeType);

            $response['filter'] = [
                'month' => $month,
                'year' => $year,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'crime_type' => $crimeType,
            ];
            
            return response()->json($response);
        } catch (\Exception $e) {
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to load SARIMA forecast data',
                'details' => $e->getMessage()
            ], 503);
        }
    }

    private function _getForecast($horizon, $crimeType = null) 
    {
        $liveVersion = $this->getForecastLiveVersionToken();

        // Cache key must include crime type
        $cacheKey = "sarima_forecast_full_{$horizon}";
        if ($crimeType) {
            $cacheKey .= "_" . md5($crimeType);
        }
        $cacheKey .= "_v{$liveVersion}";

        return Cache::remember($cacheKey, 120, function () use ($horizon, $crimeType) {
            $params = ['horizon' => $horizon];
            if ($crimeType) {
                $params['crime_type'] = $crimeType;
            }

            // 60s timeout to handle Render cold starts
            $response = Http::timeout(60)->get("{$this->sarimaApiUrl}/forecast", $params);
            
            if ($response->successful()) {
                // Return EVERYTHING the API sends, not just ['data']
                return $response->json();
            }
            
            throw new \Exception('Failed to fetch forecast from API: ' . $response->status());
        });
    }

    private function getForecastLiveVersionToken(): string
    {
        try {
            $row = DB::selectOne(
                "SELECT GREATEST(
                    COALESCE((SELECT MAX(updated_at) FROM reports), '1970-01-01'::timestamp),
                    COALESCE((SELECT MAX(updated_at) FROM patrol_dispatches), '1970-01-01'::timestamp)
                ) AS latest"
            );

            $latest = $row->latest ?? null;
            if (!$latest) return '0';

            return (string) strtotime((string) $latest);
        } catch (\Throwable $e) {
            return '0';
        }
    }

    private function applyLiveReportAdjustment(array $response, $crimeType = null): array
    {
        if (!isset($response['data']) || !is_array($response['data']) || count($response['data']) === 0) {
            return $response;
        }

        try {
            $recentQuery = DB::table('reports')
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->where(function ($q) {
                    $q->whereNull('is_valid')
                      ->orWhere('is_valid', '!=', self::REPORT_INVALID);
                });

            if (!empty($crimeType)) {
                $recentQuery->whereRaw("UPPER(COALESCE(report_type::text, '')) LIKE ?", ['%'.strtoupper($crimeType).'%']);
            }

            $recentReports = (int) $recentQuery->count();
            $signal = min(80, max(0, $recentReports));

            if ($signal <= 0) {
                return $response;
            }

            $decay = 0.65;
            $adjusted = [];

            foreach ($response['data'] as $idx => $point) {
                $base = floatval($point['forecast'] ?? 0);
                $lower = floatval($point['lower_ci'] ?? $base);
                $upper = floatval($point['upper_ci'] ?? $base);

                $shift = $signal * pow($decay, $idx);

                $point['forecast'] = round(max(0, $base + $shift), 2);
                $point['lower_ci'] = round(max(0, $lower + $shift), 2);
                $point['upper_ci'] = round(max(0, $upper + $shift), 2);

                $adjusted[] = $point;
            }

            $response['data'] = $adjusted;
            $response['live_adjustment'] = [
                'enabled' => true,
                'recent_reports_24h' => $recentReports,
                'signal_applied' => $signal,
                'decay' => $decay,
            ];
        } catch (\Throwable $e) {
            // Keep base forecast if adjustment query fails
        }

        return $response;
    }

    private function applyFilterContextScaling(array $response, $month = null, $year = null, $crimeType = null): array
    {
        if ((!$month && !$year) || !isset($response['data']) || !is_array($response['data']) || count($response['data']) === 0) {
            return $response;
        }

        try {
            $scopeHistory = $response['historical'] ?? [];
            $scopeValues = collect($scopeHistory)
                ->pluck('count')
                ->map(fn($v) => floatval($v))
                ->filter(fn($v) => is_finite($v) && $v >= 0)
                ->values();

            if ($scopeValues->isEmpty()) {
                return $response;
            }

            if (!empty($crimeType)) {
                $baseHistory = $this->getCombinedHistoricalByCrimeType($crimeType, null, null);
            } else {
                $base = $this->_getCrimeStats(null, null);
                $baseHistory = $base['monthly'] ?? [];
            }

            $baseValues = collect($baseHistory)
                ->pluck('count')
                ->map(fn($v) => floatval($v))
                ->filter(fn($v) => is_finite($v) && $v > 0)
                ->values();

            if ($baseValues->isEmpty()) {
                return $response;
            }

            $scopeAvg = $scopeValues->avg();
            $baseAvg = $baseValues->avg();

            if ($baseAvg <= 0) {
                // If base is strictly 0 across all history (maybe impossible, but edge case), assume zero forecast.
                $multiplier = $scopeAvg > 0 ? 1.5 : 0.05;
            } else {
                $multiplier = $scopeAvg / $baseAvg;
            }

            // Allow the multiplier to reflect near-zero conditions properly
            $multiplier = max(0.01, min(2.50, $multiplier));

            $adjusted = [];
            foreach ($response['data'] as $point) {
                $forecast = floatval($point['forecast'] ?? 0);
                $lower = floatval($point['lower_ci'] ?? $forecast);
                $upper = floatval($point['upper_ci'] ?? $forecast);

                $point['forecast'] = round(max(0, $forecast * $multiplier), 2);
                $point['lower_ci'] = round(max(0, $lower * $multiplier), 2);
                $point['upper_ci'] = round(max(0, $upper * $multiplier), 2);

                $adjusted[] = $point;
            }

            $response['data'] = $adjusted;
            $response['filter_context'] = [
                'applied' => true,
                'scope_average' => round($scopeAvg, 2),
                'base_average' => round($baseAvg, 2),
                'multiplier' => round($multiplier, 3),
            ];
        } catch (\Throwable $e) {
            \Log::error('applyFilterContextScaling error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // keep original forecast on any scaling error
        }

        return $response;
    }

    private function applyCrimeTypeShareScaling(array $response, $crimeType = null, $month = null, $year = null): array
    {
        if (empty($crimeType) || !isset($response['data']) || !is_array($response['data']) || count($response['data']) === 0) {
            return $response;
        }

        try {
            $stats = $this->_getCrimeStats($month, $year);
            $byType = collect($stats['byType'] ?? []);
            $total = $byType->sum(function ($row) {
                return floatval($row['count'] ?? 0);
            });

            if ($total <= 0) {
                return $response;
            }

            $selected = $byType
                ->filter(function ($row) use ($crimeType) {
                    return strtoupper(trim((string)($row['type'] ?? ''))) === strtoupper(trim((string)$crimeType));
                })
                ->sum(function ($row) {
                    return floatval($row['count'] ?? 0);
                });

            $ratio = $selected / $total;
            // keep realistic proportions so lines remain visible but distinctly per-crime
            $ratio = max(0.02, min(0.95, $ratio));

            $adjusted = [];
            foreach ($response['data'] as $point) {
                $forecast = floatval($point['forecast'] ?? 0);
                $lower = floatval($point['lower_ci'] ?? $forecast);
                $upper = floatval($point['upper_ci'] ?? $forecast);

                $point['forecast'] = round(max(0, $forecast * $ratio), 2);
                $point['lower_ci'] = round(max(0, $lower * $ratio), 2);
                $point['upper_ci'] = round(max(0, $upper * $ratio), 2);
                $adjusted[] = $point;
            }

            $response['data'] = $adjusted;
            $response['crime_type_scaling'] = [
                'applied' => true,
                'crime_type' => $crimeType,
                'ratio' => round($ratio, 4),
            ];
        } catch (\Throwable $e) {
            // keep original response on any issue
        }

        return $response;
    }

    private function applyDateRangeContextScaling(array $response, $dateFrom = null, $dateTo = null, $crimeType = null): array
    {
        if ((!$dateFrom && !$dateTo) || !isset($response['data']) || !is_array($response['data']) || count($response['data']) === 0) {
            return $response;
        }

        try {
            $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
            $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();
            if ($start->gt($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            $days = max(1, $start->diffInDays($end) + 1);
            $prevEnd = $start->copy()->subDay()->endOfDay();
            $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

            // Treat unset validity as usable report context; only exclude explicit invalid reports.
            $baseQuery = DB::table('reports')
                ->where(function ($q) {
                    $q->whereNull('is_valid')
                      ->orWhere('is_valid', '!=', self::REPORT_INVALID);
                });

            if (!empty($crimeType)) {
                $baseQuery->whereRaw("UPPER(COALESCE(report_type::text, '')) LIKE ?", ['%' . strtoupper($crimeType) . '%']);
            }

            // Use incident date when available so dashboard date filters reflect reported crime dates,
            // not only record insertion timestamps.
            
            $currentCount = (clone $baseQuery)
                ->whereRaw("COALESCE(date_reported::timestamp, created_at::timestamp) BETWEEN ?::timestamp AND ?::timestamp", [$start, $end])
                ->count();

            $prevCount = (clone $baseQuery)
                ->whereRaw("COALESCE(date_reported::timestamp, created_at::timestamp) BETWEEN ?::timestamp AND ?::timestamp", [$prevStart, $prevEnd])
                ->count();

            // Build a stable baseline from recent history so each new range filter
            // produces a context-specific multiplier (instead of a flat fallback).
            $baselineEnd = $end->copy();
            $baselineStart = $baselineEnd->copy()->subMonths(12)->startOfDay();

            $baselineCount = (clone $baseQuery)
                ->whereRaw("COALESCE(date_reported::timestamp, created_at::timestamp) BETWEEN ?::timestamp AND ?::timestamp", [$baselineStart, $baselineEnd])
                ->count();

            $baselineDays = max(1, $baselineStart->diffInDays($baselineEnd) + 1);
            $currentDailyRate = $currentCount / $days;
            $baselineDailyRate = $baselineCount / $baselineDays;

            if ($baselineDailyRate <= 0) {
                // If baseline has no activity, and currently no activity, it should predict near zero.
                $intensityMultiplier = $currentDailyRate > 0 ? 1.35 : 0.05;
            } else {
                $intensityMultiplier = $currentDailyRate / $baselineDailyRate;
            }

            // Momentum from immediately preceding window (if available).
            if ($prevCount > 0) {
                $trendMultiplier = $currentCount / $prevCount;
            } else {
                // If there was no activity previously, and none now, trend is flat near zero
                $trendMultiplier = $currentCount > 0 ? 1.5 : 0.05;
            }

            // Blend intensity + trend to avoid static values across successive filters.
            $multiplier = ($intensityMultiplier * 0.70) + ($trendMultiplier * 0.30);
            // Allow multiplier to drop very low if there truly is 0 matching data.
            $multiplier = max(0.01, min(3.00, $multiplier));

            $adjusted = [];
            foreach ($response['data'] as $point) {
                $forecast = floatval($point['forecast'] ?? 0);
                $lower = floatval($point['lower_ci'] ?? $forecast);
                $upper = floatval($point['upper_ci'] ?? $forecast);

                $point['forecast'] = round(max(0, $forecast * $multiplier), 2);
                $point['lower_ci'] = round(max(0, $lower * $multiplier), 2);
                $point['upper_ci'] = round(max(0, $upper * $multiplier), 2);
                $adjusted[] = $point;
            }

            $response['data'] = $adjusted;
            $response['date_range_context'] = [
                'applied' => true,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'current_count' => $currentCount,
                'previous_count' => $prevCount,
                'baseline_count_12m' => $baselineCount,
                'current_daily_rate' => round($currentDailyRate, 4),
                'baseline_daily_rate' => round($baselineDailyRate, 4),
                'intensity_multiplier' => round($intensityMultiplier, 3),
                'trend_multiplier' => round($trendMultiplier, 3),
                'multiplier' => round($multiplier, 3),
            ];
        } catch (\Throwable $e) {
            \Log::error('applyDateRangeContextScaling error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // keep original forecast if range scaling fails
        }

        return $response;
    }

    private function getDateRangeMonthlyReports($dateFrom = null, $dateTo = null, $crimeType = null): array
    {
        try {
            $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
            $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();
            if ($start->gt($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            $q = DB::table('reports')
                ->where(function ($query) {
                    $query->whereNull('is_valid')
                          ->orWhere('is_valid', '!=', self::REPORT_INVALID);
                })
                ->whereRaw("COALESCE(date_reported::timestamp, created_at::timestamp) BETWEEN ?::timestamp AND ?::timestamp", [$start, $end]);

            if (!empty($crimeType)) {
                $q->whereRaw("UPPER(COALESCE(report_type::text, '')) LIKE ?", ['%' . strtoupper($crimeType) . '%']);
            }

            $rows = $q->selectRaw("to_char(COALESCE(date_reported::timestamp, created_at::timestamp), 'YYYY-MM') as ym, COUNT(*) as c")
                ->groupBy('ym')
                ->orderBy('ym', 'asc')
                ->get();

            $countsByMonth = collect($rows)->mapWithKeys(function ($row) {
                return [strval($row->ym) => intval($row->c)];
            });

            $monthlySeries = [];
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();

            while ($cursor->lte($last)) {
                $ym = $cursor->format('Y-m');
                $monthlySeries[] = [
                    'year' => intval($cursor->format('Y')),
                    'month' => intval($cursor->format('m')),
                    'count' => intval($countsByMonth->get($ym, 0)),
                ];
                $cursor->addMonth();
            }

            return $monthlySeries;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get crime statistics from CSV files
     * Uses CrimeDAta.csv and DCPO_5years_monthly.csv
     * Supports optional month/year filtering
     */
    public function getCrimeStats(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
        
        try {
            $statsData = $this->_getCrimeStats($month, $year);
             return response()->json([
                'status' => 'success',
                'data' => $statsData,
                'filter' => ['month' => $month, 'year' => $year]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DB-backed report summary for analytics cards (role-aware)
     * Supports optional filtering by year and/or month.
     */
    public function getReportSummary(Request $request)
    {
        $month = $request->input('month'); // '01'..'12'
        $year = $request->input('year');   // '2025'..

        // Determine role similarly to DashboardController
        $user = auth()->user();
        $email = $user->email ?? '';
        $role = $user->role ?? null;
        $isSuperAdmin = $user && ($email === 'alertdavao.ph@gmail.com' || str_contains($email, 'alertdavao.ph'));

        $reportsQuery = DB::table('reports');

        // Optional date filters
        if ($year) {
            $reportsQuery->whereYear('created_at', $year);
        }
        if ($month) {
            // Accept either '01'..'12' or 'YYYY-MM'
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                $reportsQuery->whereRaw("to_char(created_at, 'YYYY-MM') = ?", [$month]);
            } else {
                $reportsQuery->whereMonth('created_at', intval($month));
            }
        }

        // Role-aware scoping
        if (!$isSuperAdmin) {
            if ($role === 'police') {
                $stationId = $user->station_id ?? null;
                if ($stationId) {
                    $reportsQuery->where('assigned_station_id', $stationId);
                } else {
                    // Police without station: return zeros
                    return response()->json([
                        'status' => 'success',
                        'filter' => ['month' => $month, 'year' => $year],
                        'data' => [
                            'total' => 0,
                            'resolved' => 0,
                            'pending' => 0,
                            'investigating' => 0,
                            'valid' => 0,
                            'invalid' => 0,
                            'checking' => 0,

                            // Aliases / extra cards
                            'complaints' => 0,
                            'hoaxes' => 0,
                            'total_complaints' => 0,
                            'resolved_cases' => 0,
                            'fake_reports' => 0,
                            'patrol_total' => 0,
                            'patrol_on_duty' => 0,
                            'active_dispatches' => 0,
                        ]
                    ]);
                }
            } else {
                // Admin: only assigned reports
                $reportsQuery->whereNotNull('assigned_station_id');
            }
        }

        $base = clone $reportsQuery;

        $total = (clone $base)->count();
        $resolved = (clone $base)->where('status', 'resolved')->count();
        $pending = (clone $base)->where('status', 'pending')->count();
        $investigating = (clone $base)->where('status', 'investigating')->count();

        $valid = (clone $base)->where('is_valid', self::REPORT_VALID)->count();
        $invalid = (clone $base)->where('is_valid', self::REPORT_INVALID)->count();
        $checking = (clone $base)->where('is_valid', self::REPORT_CHECKING)->count();

        // Patrol dispatch validity (used as a proxy for "fake reports" after on-ground validation)
        $dispatchQuery = DB::table('patrol_dispatches');
        if ($year) {
            $dispatchQuery->whereYear('dispatched_at', $year);
        }
        if ($month) {
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                // Try to keep it cross-db: use year+month split
                [$y, $m] = explode('-', $month);
                $dispatchQuery->whereYear('dispatched_at', $y)->whereMonth('dispatched_at', intval($m));
            } else {
                $dispatchQuery->whereMonth('dispatched_at', intval($month));
            }
        }

        if (!$isSuperAdmin) {
            if ($role === 'police') {
                $stationId = $user->station_id ?? null;
                if ($stationId) {
                    $dispatchQuery->where('station_id', $stationId);
                }
            }
        }

        $fakeReports = (clone $dispatchQuery)
            ->whereNotNull('validated_at')
            ->where('is_valid', false)
            ->count();

        $activeDispatches = (clone $dispatchQuery)
            ->whereIn('status', ['pending', 'accepted', 'en_route', 'arrived'])
            ->count();

        // Police deployment numbers (patrol officers)
        $officersQuery = DB::table('users_public')->where('user_role', 'patrol_officer');
        if (!$isSuperAdmin && $role === 'police') {
            $stationId = $user->station_id ?? null;
            if ($stationId) {
                $officersQuery->where('assigned_station_id', $stationId);
            }
        }
        $patrolTotal = (clone $officersQuery)->count();
        $patrolOnDuty = (clone $officersQuery)->where('is_on_duty', true)->count();

        return response()->json([
            'status' => 'success',
            'filter' => ['month' => $month, 'year' => $year],
            'data' => [
                'total' => $total,
                'resolved' => $resolved,
                'pending' => $pending,
                'investigating' => $investigating,
                'valid' => $valid,
                'invalid' => $invalid,
                'checking' => $checking,

                // Backlog naming / extra cards
                'complaints' => $valid,
                'hoaxes' => $invalid,
                'total_complaints' => $total,
                'resolved_cases' => $resolved,
                'fake_reports' => $fakeReports,
                'patrol_total' => $patrolTotal,
                'patrol_on_duty' => $patrolOnDuty,
                'active_dispatches' => $activeDispatches,
            ]
        ]);
    }

    /**
     * Insights endpoint: deployment suggestions, seasonality and barangay correlation.
     * Role-aware and supports optional month/year filtering (same semantics as report-summary).
     */
    public function getInsights(Request $request)
    {
        $month = $request->input('month'); // 'YYYY-MM' or '01'..'12'
        $year = $request->input('year');

        $user = auth()->user();
        $email = $user->email ?? '';
        $role = $user->role ?? null;
        $isSuperAdmin = $user && ($email === 'alertdavao.ph@gmail.com' || str_contains($email, 'alertdavao.ph'));

        $stationScopeId = null;
        if (!$isSuperAdmin && $role === 'police') {
            $stationScopeId = $user->station_id ?? null;
            if (!$stationScopeId) {
                return response()->json([
                    'status' => 'success',
                    'filter' => ['month' => $month, 'year' => $year],
                    'data' => [
                        'deployment' => [
                            'patrol_total' => 0,
                            'patrol_on_duty' => 0,
                            'active_dispatches' => 0,
                            'overdue_dispatches' => 0,
                            'fake_reports' => 0,
                        ],
                        'recommendations' => ['No station assigned to this police account; deployment insights are unavailable.'],
                        'seasonality' => [
                            'topMonths' => [],
                        ],
                        'correlation' => [
                            'topPairs' => [],
                            'topByBarangay' => [],
                        ],
                    ]
                ]);
            }
        }

        $cacheKey = 'statistics_insights_v1'
            . ($month ? "_m{$month}" : '')
            . ($year ? "_y{$year}" : '')
            . ($stationScopeId ? "_s{$stationScopeId}" : ($isSuperAdmin ? '_super' : '_admin'));

        $payload = Cache::remember($cacheKey, 600, function () use ($month, $year, $isSuperAdmin, $role, $stationScopeId) {
            // Deployment stats
            $officersQuery = DB::table('users_public')->where('user_role', 'patrol_officer');
            if ($stationScopeId) {
                $officersQuery->where('assigned_station_id', $stationScopeId);
            }
            $patrolTotal = (clone $officersQuery)->count();
            $patrolOnDuty = (clone $officersQuery)->where('is_on_duty', true)->count();

            $dispatchQuery = DB::table('patrol_dispatches');
            if ($stationScopeId) {
                $dispatchQuery->where('station_id', $stationScopeId);
            }
            if ($year) {
                $dispatchQuery->whereYear('dispatched_at', $year);
            }
            if ($month) {
                if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                    [$y, $m] = explode('-', $month);
                    $dispatchQuery->whereYear('dispatched_at', $y)->whereMonth('dispatched_at', intval($m));
                } else {
                    $dispatchQuery->whereMonth('dispatched_at', intval($month));
                }
            }

            $activeDispatches = (clone $dispatchQuery)
                ->whereIn('status', ['pending', 'accepted', 'en_route', 'arrived'])
                ->count();

            $overdueDispatches = (clone $dispatchQuery)
                ->whereIn('status', ['pending', 'accepted', 'en_route'])
                ->where('dispatched_at', '<=', Carbon::now()->subSeconds(180))
                ->count();

            $fakeReports = (clone $dispatchQuery)
                ->whereNotNull('validated_at')
                ->where('is_valid', false)
                ->count();

            // Invalid vs valid reports (for recommendation context)
            $reportsQuery = DB::table('reports');
            if ($year) {
                $reportsQuery->whereYear('created_at', $year);
            }
            if ($month) {
                if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                    [$y, $m] = explode('-', $month);
                    $reportsQuery->whereYear('created_at', $y)->whereMonth('created_at', intval($m));
                } else {
                    $reportsQuery->whereMonth('created_at', intval($month));
                }
            }
            if ($stationScopeId) {
                $reportsQuery->where('assigned_station_id', $stationScopeId);
            } elseif (!$isSuperAdmin && $role !== 'police') {
                // Admin scope: assigned only
                $reportsQuery->whereNotNull('assigned_station_id');
            }

            $validReports = (clone $reportsQuery)->where('is_valid', self::REPORT_VALID)->count();
            $invalidReports = (clone $reportsQuery)->where('is_valid', self::REPORT_INVALID)->count();
            $checkingReports = (clone $reportsQuery)->where('is_valid', self::REPORT_CHECKING)->count();

            // Recommendations
            $recommendations = [];
            if ($patrolOnDuty <= 0 && $activeDispatches > 0) {
                $recommendations[] = 'Active dispatches exist but no patrol officers appear on duty. Patrol coverage for this period should be reviewed.';
            }
            if ($overdueDispatches > 0) {
                $recommendations[] = "{$overdueDispatches} dispatch(es) are over the 3-minute response threshold. Review station routing and dispatch prioritization.";
            }
            $loadRatio = $patrolOnDuty > 0 ? ($activeDispatches / $patrolOnDuty) : null;
            if ($loadRatio !== null && $loadRatio > 2.0) {
                $recommendations[] = 'Dispatch load is high relative to available coverage. Consider rebalancing resources across stations.';
            }
            if (($invalidReports + $fakeReports) > 0 && ($validReports + $invalidReports + $checkingReports) > 0) {
                $totalProcessed = ($validReports + $invalidReports + $checkingReports);
                $invalidRate = round((($invalidReports) / max(1, $totalProcessed)) * 100, 1);
                if ($invalidRate >= 20) {
                    $recommendations[] = "High invalid-report rate ({$invalidRate}%). Consider tightening reporting guidance and prioritizing verification.";
                }
            }
            if (empty($recommendations)) {
                $recommendations[] = 'No critical issues detected for the selected filter. Continue monitoring dispatch response times and report validity.';
            }

            // Per-station deployment suggestions (admin/super-admin), or station-only for police
            $stations = [];
            if ($stationScopeId) {
                $stationRows = DB::table('police_stations')
                    ->select('station_id', 'station_name')
                    ->where('station_id', $stationScopeId)
                    ->get();
            } else {
                $stationRows = DB::table('police_stations')
                    ->select('station_id', 'station_name')
                    ->orderBy('station_name', 'asc')
                    ->get();
            }

            $officerAgg = DB::table('users_public')
                ->select(
                    'assigned_station_id',
                    DB::raw('COUNT(*) as patrol_total'),
                    DB::raw('SUM(CASE WHEN is_on_duty = true THEN 1 ELSE 0 END) as patrol_on_duty')
                )
                ->where('user_role', 'patrol_officer')
                ->whereNotNull('assigned_station_id')
                ->groupBy('assigned_station_id')
                ->get()
                ->keyBy('assigned_station_id');

            $dispatchAggQuery = DB::table('patrol_dispatches')
                ->select('station_id', DB::raw('COUNT(*) as active_dispatches'))
                ->whereIn('status', ['pending', 'accepted', 'en_route', 'arrived'])
                ->groupBy('station_id');
            if ($year) {
                $dispatchAggQuery->whereYear('dispatched_at', $year);
            }
            if ($month) {
                if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                    [$y, $m] = explode('-', $month);
                    $dispatchAggQuery->whereYear('dispatched_at', $y)->whereMonth('dispatched_at', intval($m));
                } else {
                    $dispatchAggQuery->whereMonth('dispatched_at', intval($month));
                }
            }
            $dispatchAgg = $dispatchAggQuery->get()->keyBy('station_id');

            $overdueAggQuery = DB::table('patrol_dispatches')
                ->select('station_id', DB::raw('COUNT(*) as overdue_dispatches'))
                ->whereIn('status', ['pending', 'accepted', 'en_route'])
                ->where('dispatched_at', '<=', Carbon::now()->subSeconds(180))
                ->groupBy('station_id');
            if ($year) {
                $overdueAggQuery->whereYear('dispatched_at', $year);
            }
            if ($month) {
                if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                    [$y, $m] = explode('-', $month);
                    $overdueAggQuery->whereYear('dispatched_at', $y)->whereMonth('dispatched_at', intval($m));
                } else {
                    $overdueAggQuery->whereMonth('dispatched_at', intval($month));
                }
            }
            $overdueAgg = $overdueAggQuery->get()->keyBy('station_id');

            foreach ($stationRows as $s) {
                $sid = $s->station_id;
                $o = $officerAgg->get($sid);
                $d = $dispatchAgg->get($sid);
                $od = $overdueAgg->get($sid);

                $sPatrolTotal = intval($o->patrol_total ?? 0);
                $sPatrolOnDuty = intval($o->patrol_on_duty ?? 0);
                $sActive = intval($d->active_dispatches ?? 0);
                $sOverdue = intval($od->overdue_dispatches ?? 0);
                $ratio = $sPatrolOnDuty > 0 ? ($sActive / $sPatrolOnDuty) : null;

                $suggestion = 'OK';
                if ($sOverdue > 0) {
                    $suggestion = 'Response times are exceeding the 3-minute threshold. Consider reassigning dispatch coverage for this area.';
                } elseif ($sPatrolOnDuty <= 0 && $sActive > 0) {
                    $suggestion = 'Active dispatches exist but no officers appear on duty. Patrol coverage should be reviewed.';
                } elseif ($ratio !== null && $ratio > 2.0) {
                    $suggestion = 'Dispatch load is high relative to available coverage. Consider rebalancing resources across stations.';
                }

                $stations[] = [
                    'station_id' => $sid,
                    'station_name' => $s->station_name,
                    'active_dispatches' => $sActive,
                    'overdue_dispatches' => $sOverdue,
                    'suggestion' => $suggestion,
                ];
            }

            // Seasonality from CSV (month-of-year aggregation)
            $seasonality = $this->computeSeasonalityFromCsv($year);

            // Crime type vs barangay correlation (DB)
            $correlation = $this->computeCrimeTypeBarangayCorrelation($month, $year, $stationScopeId, $isSuperAdmin, $role);

            return [
                'deployment' => [
                    'active_dispatches' => $activeDispatches,
                    'overdue_dispatches' => $overdueDispatches,
                    'fake_reports' => $fakeReports,
                ],
                'stations' => $stations,
                'recommendations' => $recommendations,
                'seasonality' => $seasonality,
                'correlation' => $correlation,
            ];
        });

        return response()->json([
            'status' => 'success',
            'filter' => ['month' => $month, 'year' => $year],
            'data' => $payload,
        ]);
    }

    /**
     * Compute month-of-year seasonality from BOTH CSV + DB reports
     */
    private function computeSeasonalityFromCsv($year = null): array
    {
        $csvPath = storage_path('app/davao_crime_5years.csv');
        $monthTotals = array_fill(1, 12, 0.0);
        $monthCounts = array_fill(1, 12, 0);

        // ── 1. CSV data ─────────────────────────────────────────
        if (file_exists($csvPath)) {
            $file = fopen($csvPath, 'r');
            $header = fgetcsv($file);
            $headerMap = array_flip($header ?: []);
            $idxDate = $headerMap['date'] ?? 1;
            $idxCount = $headerMap['crime_count'] ?? 4;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 5) continue;
                $date = $row[$idxDate] ?? null;
                if (!$date || strlen($date) < 7) continue;

                $rowYear = substr($date, 0, 4);
                $rowMonth = intval(substr($date, 5, 2));
                if ($rowMonth < 1 || $rowMonth > 12) continue;
                if ($year && $rowYear !== (string)$year) continue;

                $count = floatval($row[$idxCount] ?? 0);
                $monthTotals[$rowMonth] += $count;
                $monthCounts[$rowMonth] += 1;
            }
            fclose($file);
        }

        // ── 2. DB reports (validated) ───────────────────────────
        try {
            $dbQuery = DB::table('reports')
                ->select(
                    DB::raw('EXTRACT(MONTH FROM created_at) as m'),
                    DB::raw('COUNT(*) as cnt')
                )
                ->where('is_valid', self::REPORT_VALID);

            if ($year) {
                $dbQuery->whereYear('created_at', $year);
            }

            $dbMonthly = $dbQuery->groupBy('m')->get();

            foreach ($dbMonthly as $row) {
                $m = intval($row->m);
                if ($m >= 1 && $m <= 12) {
                    $monthTotals[$m] += $row->cnt;
                    $monthCounts[$m] += 1;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('DB seasonality merge failed: ' . $e->getMessage());
        }

        // ── 3. Build averages ───────────────────────────────────
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
            7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        $averages = [];
        foreach ($monthTotals as $m => $total) {
            $avg = $monthCounts[$m] > 0 ? ($total / $monthCounts[$m]) : 0;
            $averages[] = [
                'month' => $m,
                'monthName' => $monthNames[$m],
                'averageCount' => round($avg, 2),
                'totalCount' => round($total, 2),
            ];
        }

        usort($averages, function ($a, $b) {
            return $b['averageCount'] <=> $a['averageCount'];
        });

        return [
            'topMonths' => array_slice($averages, 0, 3),
            'monthAverages' => $averages,
        ];
    }

    private function normalizeReportTypes($reportTypeRaw): array
    {
        if ($reportTypeRaw === null) return [];

        if (is_string($reportTypeRaw)) {
            $decoded = json_decode($reportTypeRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $reportTypeRaw = $decoded;
            }
        }

        $types = [];
        if (is_array($reportTypeRaw)) {
            foreach ($reportTypeRaw as $t) {
                if (!is_string($t)) continue;
                $clean = trim($t);
                if ($clean !== '') $types[] = $clean;
            }
        } elseif (is_string($reportTypeRaw)) {
            $clean = trim($reportTypeRaw);
            if ($clean !== '') $types[] = $clean;
        }

        return array_values(array_unique($types));
    }

    private function computeCrimeTypeBarangayCorrelation($month, $year, $stationScopeId, $isSuperAdmin, $role): array
    {
        $query = DB::table('reports')
            ->join('locations', 'reports.location_id', '=', 'locations.location_id')
            ->select('reports.report_type', 'locations.barangay', 'reports.created_at', 'reports.assigned_station_id')
            ->where('reports.is_valid', self::REPORT_VALID);

        if ($year) {
            $query->whereYear('reports.created_at', $year);
        }
        if ($month) {
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                [$y, $m] = explode('-', $month);
                $query->whereYear('reports.created_at', $y)->whereMonth('reports.created_at', intval($m));
            } else {
                $query->whereMonth('reports.created_at', intval($month));
            }
        }

        if ($stationScopeId) {
            $query->where('reports.assigned_station_id', $stationScopeId);
        } elseif (!$isSuperAdmin && $role !== 'police') {
            $query->whereNotNull('reports.assigned_station_id');
        }

        // Keep it bounded to avoid heavy loads on large datasets
        $rows = $query->orderBy('reports.created_at', 'desc')->limit(5000)->get();

        $pairCounts = [];
        $byBarangay = [];

        foreach ($rows as $row) {
            $barangay = trim((string)($row->barangay ?? ''));
            if ($barangay === '') continue;

            $types = $this->normalizeReportTypes($row->report_type);
            foreach ($types as $t) {
                $type = trim($t);
                if ($type === '') continue;

                $pairKey = $barangay . '||' . $type;
                $pairCounts[$pairKey] = ($pairCounts[$pairKey] ?? 0) + 1;

                if (!isset($byBarangay[$barangay])) {
                    $byBarangay[$barangay] = [];
                }
                $byBarangay[$barangay][$type] = ($byBarangay[$barangay][$type] ?? 0) + 1;
            }
        }

        arsort($pairCounts);
        $topPairs = [];
        foreach (array_slice($pairCounts, 0, 12, true) as $key => $count) {
            [$barangay, $type] = explode('||', $key, 2);
            $topPairs[] = ['barangay' => $barangay, 'crimeType' => $type, 'count' => $count];
        }

        // For each barangay, keep top 3 types
        $topByBarangay = [];
        foreach ($byBarangay as $barangay => $counts) {
            arsort($counts);
            $topTypes = [];
            foreach (array_slice($counts, 0, 3, true) as $type => $count) {
                $topTypes[] = ['crimeType' => $type, 'count' => $count];
            }
            $topByBarangay[] = ['barangay' => $barangay, 'topTypes' => $topTypes];
        }
        usort($topByBarangay, function ($a, $b) {
            return strcmp($a['barangay'], $b['barangay']);
        });

        return [
            'topPairs' => $topPairs,
            'topByBarangay' => array_slice($topByBarangay, 0, 15),
        ];
    }

    /**
     * Fetch validated reports from the DB, normalized into the same
     * byType / byLocation / monthly shape used by the CSV reader.
     * Each report_type JSON entry counts as 1 incident.
     */
    private function _getDbReportStats($month, $year): array
    {
        $query = DB::table('reports')
            ->join('locations', 'reports.location_id', '=', 'locations.location_id')
            ->select('reports.report_type', 'locations.barangay', 'reports.created_at')
            ->where('reports.is_valid', self::REPORT_VALID);

        // Apply date filters (same formats the CSV path supports)
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            [$y, $m] = explode('-', $month);
            $query->whereYear('reports.created_at', $y)
                  ->whereMonth('reports.created_at', intval($m));
        } elseif ($year) {
            $query->whereYear('reports.created_at', $year);
        }

        $rows = $query->orderBy('reports.created_at', 'desc')->limit(10000)->get();

        $crimesByType = [];
        $crimesByLocation = [];
        $monthlyStats = [];

        foreach ($rows as $row) {
            $barangay = trim((string)($row->barangay ?? ''));
            $types = $this->normalizeReportTypes($row->report_type);
            $createdAt = $row->created_at;
            $rowYear = substr($createdAt, 0, 4);
            $rowMonth = substr($createdAt, 5, 2);

            foreach ($types as $rawType) {
                $type = strtoupper(trim($rawType));
                if ($type === '') continue;

                $crimesByType[$type] = ($crimesByType[$type] ?? 0) + 1;

                if ($barangay !== '') {
                    $crimesByLocation[$barangay] = ($crimesByLocation[$barangay] ?? 0) + 1;
                }

                $mKey = "$rowYear-$rowMonth";
                if (!isset($monthlyStats[$mKey])) {
                    $monthlyStats[$mKey] = ['year' => intval($rowYear), 'month' => intval($rowMonth), 'count' => 0];
                }
                $monthlyStats[$mKey]['count'] += 1;
            }
        }

        return [
            'crimesByType' => $crimesByType,
            'crimesByLocation' => $crimesByLocation,
            'monthlyStats' => $monthlyStats,
            'totalRows' => $rows->count(),
        ];
    }

    private function _getCrimeStats($month, $year)
    {
        $cacheKey = 'crime_stats_data_v3' . ($month ? "_$month" : "") . ($year ? "_$year" : "");
            
        return Cache::remember($cacheKey, 3600, function () use ($month, $year) {
            $csvPath = storage_path('app/davao_crime_5years.csv');
            
            if (!file_exists($csvPath)) {
                throw new \Exception('Data file not found at: ' . $csvPath);
            }

            // ── 1. Historical CSV data ─────────────────────────────
            $crimesByType = [];
            $crimesByLocation = [];
            $monthlyStats = [];
            
            $file = fopen($csvPath, 'r');
            $header = fgetcsv($file); 
            $headerMap = array_flip($header);
            $idxDate = $headerMap['date'] ?? 1;
            $idxBarangay = $headerMap['barangay'] ?? 2;
            $idxType = $headerMap['crime_type'] ?? 3;
            $idxCount = $headerMap['crime_count'] ?? 4;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 5) continue;

                $date = $row[$idxDate];
                $barangay = trim($row[$idxBarangay]);
                $type = strtoupper(trim($row[$idxType]));
                $count = floatval($row[$idxCount]);
                
                $rowYear = substr($date, 0, 4);
                $rowMonth = substr($date, 5, 2);

                if ($month && substr($date, 0, 7) !== $month) continue;
                if ($year && $rowYear !== $year) continue;

                $crimesByType[$type] = ($crimesByType[$type] ?? 0) + $count;
                $crimesByLocation[$barangay] = ($crimesByLocation[$barangay] ?? 0) + $count;

                $mKey = "$rowYear-$rowMonth";
                if (!isset($monthlyStats[$mKey])) {
                    $monthlyStats[$mKey] = ['year' => intval($rowYear), 'month' => intval($rowMonth), 'count' => 0];
                }
                $monthlyStats[$mKey]['count'] += $count;
            }
            fclose($file);

            // ── 2. Live DB reports (validated) ─────────────────────
            try {
                $dbStats = $this->_getDbReportStats($month, $year);

                foreach ($dbStats['crimesByType'] as $type => $cnt) {
                    $crimesByType[$type] = ($crimesByType[$type] ?? 0) + $cnt;
                }
                foreach ($dbStats['crimesByLocation'] as $loc => $cnt) {
                    $crimesByLocation[$loc] = ($crimesByLocation[$loc] ?? 0) + $cnt;
                }
                foreach ($dbStats['monthlyStats'] as $mKey => $entry) {
                    if (!isset($monthlyStats[$mKey])) {
                        $monthlyStats[$mKey] = $entry;
                    } else {
                        $monthlyStats[$mKey]['count'] += $entry['count'];
                    }
                }
                $dbReportCount = $dbStats['totalRows'];
            } catch (\Exception $e) {
                \Log::warning('DB report stats merge failed, using CSV only: ' . $e->getMessage());
                $dbReportCount = 0;
            }

            // ── 3. Build final response ────────────────────────────
            $crimeByType = collect($crimesByType)
                ->map(function($c, $t) { return ['type' => $t, 'count' => $c]; })
                ->sortByDesc('count')->values()->take(15);

            $crimeByLocation = collect($crimesByLocation)
                ->map(function($c, $l) { return ['location' => $l, 'count' => $c]; })
                ->sortByDesc('count')->values()->take(10);

            $monthlyStatsFormatted = collect($monthlyStats)
                ->sortBy(function ($item) {
                    return sprintf('%04d-%02d', $item['year'], $item['month']);
                })
                ->values();

            $totalCrimes = array_sum($crimesByType);
            $latest = $monthlyStatsFormatted->last();
            $totalThisMonth = $latest['count'] ?? 0;
            
            $percentChange = 0; 
            if ($monthlyStatsFormatted->count() >= 2) {
                $prev = $monthlyStatsFormatted[$monthlyStatsFormatted->count() - 2];
                if ($prev['count'] > 0) {
                    $percentChange = round((($totalThisMonth - $prev['count']) / $prev['count']) * 100, 2);
                }
            }

            return [
                'monthly' => $monthlyStatsFormatted,
                'byType' => $crimeByType,
                'byStatus' => [],
                'byLocation' => $crimeByLocation,
                'overview' => [
                    'total' => $totalCrimes,
                    'thisMonth' => $totalThisMonth,
                    'lastMonth' => 0,
                    'percentChange' => $percentChange
                ],
                'source' => 'historical_csv+db_reports',
                'dbReports' => $dbReportCount ?? 0,
            ];
        });
    }

    /**
     * Export crime data as CSV with optional filters
     */
    public function exportCrimeData(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $crimeType = $request->input('crime_type');
        
        try {
            // If crime type specified, export from DCPO CSV
            if ($crimeType) {
                return $this->exportDCPOData($crimeType, $year, $month);
            }
            
            // Otherwise export from reports table
            $query = DB::table('reports')->where('is_valid', 'valid');
            
            if ($year) {
                $query->whereYear('created_at', $year);
            }
            if ($month) {
                $query->whereMonth('created_at', $month);
            }
            
            $data = $query->select(
                    DB::raw('EXTRACT(YEAR FROM created_at)::int as "Year"'),
                    DB::raw('EXTRACT(MONTH FROM created_at)::int as "Month"'),
                    DB::raw('COUNT(*) as "Count"'),
                    DB::raw("to_char(created_at, 'YYYY-MM') || '-01' as \"Date\"")
                )
                ->groupBy(DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(MONTH FROM created_at)'), DB::raw("to_char(created_at, 'YYYY-MM')"))
                ->orderBy(DB::raw('EXTRACT(YEAR FROM created_at)'), 'asc')
                ->orderBy(DB::raw('EXTRACT(MONTH FROM created_at)'), 'asc')
                ->get();

            $csv = "Year,Month,Count,Date\n";
            foreach ($data as $row) {
                $csv .= "{$row->Year},{$row->Month},{$row->Count},{$row->Date}\n";
            }
            
            $filename = 'CrimeData';
            if ($year) $filename .= "_{$year}";
            if ($month) $filename .= "_M{$month}";
            $filename .= '_' . date('Y-m-d') . '.csv';

            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export crime data',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Export crime-specific data from DCPO CSV
     */



    /**
     * Get barangay-level crime statistics from DCPO_5years_monthly.csv
     * Supports optional month/year filtering
     */
    public function getBarangayCrimeStats(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
         try {
            $stats = $this->_getBarangayStats($month, $year);
             return response()->json([
                'status' => 'success',
                'data' => $stats['data'],
                'total_barangays' => $stats['total_barangays'],
                'total_crimes' => $stats['total_crimes'],
                'filter' => ['month' => $month, 'year' => $year]
            ]);
        } catch (\Exception $e) {
             return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function _getBarangayStats($month, $year) 
    {
         $cacheKey = 'barangay_crime_stats_v3' . ($month ? "_$month" : "") . ($year ? "_$year" : "");
            
         return Cache::remember($cacheKey, 3600, function () use ($month, $year) {
             $csvPath = storage_path('app/davao_crime_5years.csv');
             if (!file_exists($csvPath)) throw new \Exception('Data file not found at: ' . $csvPath);

             $barangayData = [];
             $barangayCrimeTypes = [];

             // ── 1. Historical CSV data ─────────────────────────────
             $file = fopen($csvPath, 'r');
             $header = fgetcsv($file); 
             $headerMap = array_flip($header);
             $idxDate = $headerMap['date'] ?? 1;
             $idxBarangay = $headerMap['barangay'] ?? 2;
             $idxType = $headerMap['crime_type'] ?? 3;
             $idxCount = $headerMap['crime_count'] ?? 4;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 5) continue;
                
                $date = $row[$idxDate];
                $barangay = trim($row[$idxBarangay]);
                $crimeType = strtoupper(trim($row[$idxType]));
                $count = floatval($row[$idxCount]);
                
                $rowYear = substr($date, 0, 4);

                if ($month && substr($date, 0, 7) !== $month) continue;
                if ($year && $rowYear !== $year) continue;
                
                $barangayData[$barangay] = ($barangayData[$barangay] ?? 0) + $count;
                
                if (!isset($barangayCrimeTypes[$barangay])) {
                    $barangayCrimeTypes[$barangay] = [];
                }
                $barangayCrimeTypes[$barangay][$crimeType] = ($barangayCrimeTypes[$barangay][$crimeType] ?? 0) + $count;
            }
            fclose($file);

            // ── 2. Live DB reports (validated) ─────────────────────
            try {
                $dbQuery = DB::table('reports')
                    ->join('locations', 'reports.location_id', '=', 'locations.location_id')
                    ->select('reports.report_type', 'locations.barangay', 'reports.created_at')
                    ->where('reports.is_valid', self::REPORT_VALID);

                if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
                    [$y, $m] = explode('-', $month);
                    $dbQuery->whereYear('reports.created_at', $y)
                            ->whereMonth('reports.created_at', intval($m));
                } elseif ($year) {
                    $dbQuery->whereYear('reports.created_at', $year);
                }

                $dbRows = $dbQuery->limit(10000)->get();

                foreach ($dbRows as $row) {
                    $barangay = trim((string)($row->barangay ?? ''));
                    if ($barangay === '') continue;

                    $types = $this->normalizeReportTypes($row->report_type);
                    foreach ($types as $rawType) {
                        $crimeType = strtoupper(trim($rawType));
                        if ($crimeType === '') continue;

                        $barangayData[$barangay] = ($barangayData[$barangay] ?? 0) + 1;

                        if (!isset($barangayCrimeTypes[$barangay])) {
                            $barangayCrimeTypes[$barangay] = [];
                        }
                        $barangayCrimeTypes[$barangay][$crimeType] = ($barangayCrimeTypes[$barangay][$crimeType] ?? 0) + 1;
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('DB barangay stats merge failed, using CSV only: ' . $e->getMessage());
            }

            // ── 3. Build result ────────────────────────────────────
            $result = [];
            foreach ($barangayData as $barangay => $totalCrimes) {
                // Sort crime types by count descending and get top 5
                $crimeTypes = $barangayCrimeTypes[$barangay] ?? [];
                arsort($crimeTypes);
                $topCrimes = array_slice($crimeTypes, 0, 5, true);
                
                $crimeBreakdown = [];
                foreach ($topCrimes as $type => $count) {
                    $crimeBreakdown[] = ['type' => $type, 'count' => $count];
                }
                
                $result[] = [
                    'barangay' => $barangay, 
                    'total_crimes' => $totalCrimes,
                    'crime_breakdown' => $crimeBreakdown
                ];
            }
            
            usort($result, function($a, $b) {
                return $b['total_crimes'] - $a['total_crimes'];
            });
            
            return [
                'data' => $result,
                'total_barangays' => count($result),
                'total_crimes' => array_sum($barangayData)
            ];
         });
    }

    /**
     * Clear all statistics caches
     * Useful when CSV files are updated or reports change
     */
    public function clearCache()
    {
        try {
            $cleared = [];

            // Clear crime stats caches (with and without filters)
            foreach (['crime_stats_data_v3', 'crime_stats_data_v2'] as $prefix) {
                Cache::forget($prefix);
                $cleared[] = $prefix;
            }

            // Clear barangay stats caches
            foreach (['barangay_crime_stats_v3', 'barangay_crime_stats_v2'] as $prefix) {
                Cache::forget($prefix);
                $cleared[] = $prefix;
            }
            
            // Clear all forecast horizon caches (6, 12, 18, 24 months)
            foreach ([6, 12, 18, 24] as $horizon) {
                Cache::forget("sarima_forecast_full_{$horizon}");
                $cleared[] = "sarima_forecast_full_{$horizon}";
            }

            // Clear barangay risk caches
            foreach ([3, 6, 12] as $m) {
                Cache::forget("sarima_barangay_risk_v2_{$m}");
                Cache::forget("sarima_barangay_risk_{$m}");
                $cleared[] = "sarima_barangay_risk_v2_{$m}";
            }

            // Clear monthly warning caches
            Cache::forget('sarima_monthly_warning_current');
            $cleared[] = 'sarima_monthly_warning_current';

            // Clear insights caches (pattern-based, clear common variants)
            Cache::forget('statistics_insights_v1');
            $cleared[] = 'statistics_insights_v1';
            
            return response()->json([
                'status' => 'success',
                'message' => 'All statistics caches cleared successfully',
                'cleared' => $cleared,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cache',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get historical monthly data for specific crime type
     * Filters DCPO_5years_monthly.csv by offense type
     */
    private function getHistoricalByCrimeType($crimeType)
    {
        $csvPath = storage_path('app/davao_crime_5years.csv');
        $monthlyData = [];
        
        if (!file_exists($csvPath)) {
            return [];
        }
        
        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);
        $headerMap = array_flip($header);
        $idxDate = $headerMap['date'] ?? 1;
        $idxType = $headerMap['crime_type'] ?? 3;
        $idxCount = $headerMap['crime_count'] ?? 4;
        
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 5) continue;
            
            $offense = trim($row[$idxType]);
            
            if (strcasecmp($offense, $crimeType) === 0) {
                $date = $row[$idxDate];
                $yearMonth = substr($date, 0, 7);
                $count = floatval($row[$idxCount]);
                
                if (!isset($monthlyData[$yearMonth])) {
                    $monthlyData[$yearMonth] = 0;
                }
                $monthlyData[$yearMonth] += $count;
            }
        }
        fclose($file);
        
        $result = [];
        foreach ($monthlyData as $yearMonth => $count) {
            list($year, $month) = explode('-', $yearMonth);
            $result[] = [
                'year' => intval($year),
                'month' => intval($month),
                'count' => $count
            ];
        }
        
        usort($result, function($a, $b) {
            if ($a['year'] != $b['year']) return $a['year'] - $b['year'];
            return $a['month'] - $b['month'];
        });
        
        return $result;
    }

    private function getCombinedHistoricalByCrimeType($crimeType, $month = null, $year = null): array
    {
        $monthly = [];

        // CSV baseline
        foreach ($this->getHistoricalByCrimeType($crimeType) as $row) {
            $rowYear = strval($row['year']);
            $rowMonth = str_pad(strval($row['month']), 2, '0', STR_PAD_LEFT);
            $ym = $rowYear . '-' . $rowMonth;

            if ($month && preg_match('/^\d{4}-\d{2}$/', $month) && $ym !== $month) continue;
            if ($year && $rowYear !== strval($year)) continue;

            if (!isset($monthly[$ym])) {
                $monthly[$ym] = ['year' => intval($rowYear), 'month' => intval($rowMonth), 'count' => 0];
            }
            $monthly[$ym]['count'] += floatval($row['count'] ?? 0);
        }

        // Validated DB reports merge
        try {
            $dbQuery = DB::table('reports')
                ->where('is_valid', self::REPORT_VALID)
                ->whereRaw("UPPER(COALESCE(report_type::text, '')) LIKE ?", ['%' . strtoupper($crimeType) . '%']);

            if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
                [$y, $m] = explode('-', $month);
                $dbQuery->whereYear('created_at', intval($y))
                    ->whereMonth('created_at', intval($m));
            } elseif ($year) {
                $dbQuery->whereYear('created_at', intval($year));
            }

            $dbRows = $dbQuery
                ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, COUNT(*) as c")
                ->groupBy('ym')
                ->orderBy('ym', 'asc')
                ->get();

            foreach ($dbRows as $row) {
                $ym = strval($row->ym);
                [$y, $m] = explode('-', $ym);

                if (!isset($monthly[$ym])) {
                    $monthly[$ym] = ['year' => intval($y), 'month' => intval($m), 'count' => 0];
                }
                $monthly[$ym]['count'] += intval($row->c);
            }
        } catch (\Throwable $e) {
            // keep CSV-only if DB merge fails
        }

        return collect($monthly)
            ->sortBy(function ($item) {
                return sprintf('%04d-%02d', $item['year'], $item['month']);
            })
            ->values()
            ->all();
    }

    /**
     * Export crime-specific data from Historical CSV
     */
    private function exportDCPOData($crimeType, $year = null, $month = null)
    {
        $csvPath = storage_path('app/davao_crime_5years.csv');
        
        if (!file_exists($csvPath)) {
            return response()->json(['status' => 'error', 'message' => 'Data file not found'], 404);
        }
        
        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);
        
        // Map headers
        $headerMap = array_flip($header);
        $idxDate = $headerMap['date'] ?? 1;
        $idxType = $headerMap['crime_type'] ?? 3;
        $idxCount = $headerMap['crime_count'] ?? 4;
        
        $data = [];
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 5) continue;
            
            $offense = trim($row[$idxType]);
            
            if (strcasecmp($offense, $crimeType) === 0) {
                $date = $row[$idxDate];
                $rowYear = substr($date, 0, 4);
                $rowMonth = substr($date, 5, 2);
                
                if ($year && $rowYear != $year) continue;
                if ($month && $rowMonth != $month) continue;
                
                $data[] = [
                    'year' => $rowYear,
                    'month' => $rowMonth,
                    'count' => floatval($row[$idxCount]),
                    'date' => substr($date, 0, 10)
                ];
            }
        }
        fclose($file);
        
        $csv = "Year,Month,Count,Date,CrimeType\n";
        foreach ($data as $row) {
            $csv .= "{$row['year']},{$row['month']},{$row['count']},{$row['date']},{$crimeType}\n";
        }
        
        $filename = 'CrimeData_' . str_replace(' ', '_', $crimeType);
        if ($year) $filename .= "_{$year}";
        if ($month) $filename .= "_M{$month}";
        $filename .= '_' . date('Y-m-d') . '.csv';
        
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get barangay risk assessment from SARIMA API
     * Proxies to /barangay-risk endpoint
     */
    public function getBarangayRisk(Request $request)
    {
        $months = $request->input('months', 6);
        
        try {
            $this->autoStartSarimaApi();
            
            $cacheKey = "sarima_barangay_risk_v2_{$months}";
            
            $data = Cache::remember($cacheKey, 1800, function () use ($months) {
                $response = Http::timeout(30)->get("{$this->sarimaApiUrl}/barangay-risk", [
                    'months' => $months
                ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                throw new \Exception('Failed to fetch barangay risk: ' . $response->status());
            });

            // ── Supplement with live DB report counts ──────────
            try {
                $since = Carbon::now()->subMonths($months);
                $dbCounts = DB::table('reports')
                    ->join('locations', 'reports.location_id', '=', 'locations.location_id')
                    ->select('locations.barangay', DB::raw('COUNT(*) as report_count'))
                    ->where('reports.is_valid', self::REPORT_VALID)
                    ->where('reports.created_at', '>=', $since)
                    ->groupBy('locations.barangay')
                    ->get()
                    ->keyBy('barangay');

                // Merge live_reports into each SARIMA barangay entry
                if (is_array($data)) {
                    foreach ($data as &$entry) {
                        $brgy = $entry['barangay'] ?? '';
                        $dbRow = $dbCounts->get($brgy);
                        $entry['live_reports'] = $dbRow ? $dbRow->report_count : 0;
                        // Upgrade risk if many recent reports
                        if ($entry['live_reports'] >= 5 && ($entry['risk_level'] ?? '') === 'LOW') {
                            $entry['risk_level'] = 'MEDIUM';
                            $entry['warning'] = ($entry['warning'] ?? '') . ' (elevated by recent reports)';
                        }
                    }
                    unset($entry);

                    // Add DB-only barangays not in SARIMA
                    $sarimaBarangays = collect($data)->pluck('barangay')->toArray();
                    foreach ($dbCounts as $brgy => $row) {
                        if (!in_array($brgy, $sarimaBarangays) && $row->report_count >= 2) {
                            $risk = $row->report_count >= 10 ? 'HIGH' : ($row->report_count >= 5 ? 'MEDIUM' : 'LOW');
                            $data[] = [
                                'barangay' => $brgy,
                                'recent_crimes' => 0,
                                'live_reports' => $row->report_count,
                                'risk_level' => $risk,
                                'warning' => "Based on {$row->report_count} validated report(s)",
                                'recommended_action' => $risk === 'HIGH' ? 'Increase patrol frequency' : 'Monitor situation',
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('DB supplement for barangay risk failed: ' . $e->getMessage());
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'months' => $months,
                'source' => 'SARIMA API + DB reports'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch barangay risk assessment',
                'details' => $e->getMessage()
            ], 503);
        }
    }

    /**
     * Get monthly crime warning from SARIMA API
     * Proxies to /monthly-crime-warning endpoint
     */
    public function getMonthlyCrimeWarning(Request $request)
    {
        $date = $request->input('date'); // YYYY-MM format
        
        try {
            $this->autoStartSarimaApi();
            
            $cacheKey = "sarima_monthly_warning_" . ($date ?? 'current');
            
            $data = Cache::remember($cacheKey, 1800, function () use ($date) {
                $params = [];
                if ($date) {
                    $params['date'] = $date;
                }
                
                $response = Http::timeout(30)->get("{$this->sarimaApiUrl}/monthly-crime-warning", $params);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                throw new \Exception('Failed to fetch monthly warning: ' . $response->status());
            });
            
            return response()->json([
                'status' => 'success',
                'data' => $data,
                'date' => $date,
                'source' => 'SARIMA API'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch monthly crime warning',
                'details' => $e->getMessage()
            ], 503);
        }
    }
}


