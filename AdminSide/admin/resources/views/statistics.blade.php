@extends('layouts.app')

@section('title', 'Crime Statistics & Analytics')

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    :root {
        --primary: #1D3557;
        --primary-light: #457B9D;
        --accent: #E63946;
        --success: #10B981;
        --warning: #F59E0B;
        --danger: #EF4444;
        --gray-50: #F9FAFB;
        --gray-100: #F3F4F6;
        --gray-200: #E5E7EB;
        --gray-300: #D1D5DB;
        --gray-500: #6B7280;
        --gray-700: #374151;
        --gray-900: #111827;
    }

    * { box-sizing: border-box; }

    .stats-page {
        padding: 1.5rem;
        max-width: 1600px;
        margin: 0 auto;
        background: var(--gray-50);
        min-height: 100vh;
    }

    /* Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-900);
        margin: 0;
    }

    .page-subtitle {
        color: var(--gray-500);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .header-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Filters */
    .filter-bar {
        background: white;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-label {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--gray-500);
    }

    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 0.875rem;
        color: var(--gray-700);
        background: white;
        min-width: 120px;
        cursor: pointer;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(29, 53, 87, 0.1);
    }

    .btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: #162a44;
    }

    .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-700);
        border: 1px solid var(--gray-200);
    }

    .btn-secondary:hover {
        background: var(--gray-200);
    }

    .btn-success {
        background: var(--success);
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    /* Quick Stats Grid */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }

    .stat-card.blue::before { background: var(--primary); }
    .stat-card.green::before { background: var(--success); }
    .stat-card.orange::before { background: var(--warning); }
    .stat-card.red::before { background: var(--danger); }
    .stat-card.purple::before { background: #8B5CF6; }

    .stat-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-900);
    }

    .stat-meta {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 0.25rem;
    }

    .stat-change {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.125rem 0.5rem;
        border-radius: 999px;
        margin-top: 0.5rem;
    }

    .stat-change.up { background: #D1FAE5; color: #065F46; }
    .stat-change.down { background: #FEE2E2; color: #991B1B; }

    /* Cards */
    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--gray-100);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-body {
        padding: 1.25rem;
    }

    .card-controls {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Charts */
    .chart-container {
        position: relative;
        height: 350px;
    }

    .chart-container.tall {
        height: 400px;
    }

    /* Operational Metrics */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
    }

    .metric-box {
        padding: 1rem;
        border-radius: 10px;
        text-align: center;
    }

    .metric-box.critical { background: #FEF2F2; border: 1px solid #FECACA; }
    .metric-box.warning { background: #FFFBEB; border: 1px solid #FED7AA; }
    .metric-box.success { background: #F0FDF4; border: 1px solid #BBF7D0; }
    .metric-box.info { background: #EFF6FF; border: 1px solid #BFDBFE; }

    .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .metric-box.critical .metric-value { color: #DC2626; }
    .metric-box.warning .metric-value { color: #D97706; }
    .metric-box.success .metric-value { color: #16A34A; }
    .metric-box.info .metric-value { color: #2563EB; }

    .metric-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        margin-top: 0.25rem;
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .data-table th {
        text-align: left;
        padding: 0.75rem;
        font-weight: 600;
        color: var(--gray-500);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--gray-200);
        background: var(--gray-50);
    }

    .data-table td {
        padding: 0.75rem;
        border-bottom: 1px solid var(--gray-100);
        color: var(--gray-700);
    }

    .data-table tr:hover {
        background: var(--gray-50);
    }

    /* Risk Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-high { background: #FEE2E2; color: #991B1B; }
    .badge-medium { background: #FEF3C7; color: #92400E; }
    .badge-low { background: #D1FAE5; color: #065F46; }

    /* Recommendations */
    .recommendation-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .recommendation-list li {
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--gray-100);
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: var(--gray-700);
    }

    .recommendation-list li:last-child {
        border-bottom: none;
    }

    .recommendation-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.75rem;
    }

    .recommendation-icon.alert { background: #FEF3C7; color: #D97706; }
    .recommendation-icon.info { background: #DBEAFE; color: #2563EB; }
    .recommendation-icon.success { background: #D1FAE5; color: #059669; }

    /* Forecast Legend */
    .forecast-legend {
        display: flex;
        gap: 1.5rem;
        margin-top: 1rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        color: var(--gray-500);
    }

    .legend-color {
        width: 14px;
        height: 14px;
        border-radius: 3px;
    }

    /* Loading */
    .loading-spinner {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
    }

    .spinner {
        width: 36px;
        height: 36px;
        border: 3px solid var(--gray-200);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--gray-500);
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    /* Forecast Info */
    .forecast-info {
        background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
        border: 1px solid #BFDBFE;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }

    .forecast-info strong {
        color: var(--primary);
    }

    /* Export Section */
    .export-section {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Two Column Layout */
    .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    @media (max-width: 900px) {
        .two-col {
            grid-template-columns: 1fr;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-page {
            padding: 1rem;
        }

        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .filter-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-group {
            justify-content: space-between;
        }
    }

    /* Status Indicator */
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 0.5rem;
    }

    .status-dot.online { background: var(--success); }
    .status-dot.offline { background: var(--danger); }

    /* Compact Card */
    .compact-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .compact-stat {
        text-align: center;
        padding: 0.75rem;
        background: var(--gray-50);
        border-radius: 8px;
    }

    .compact-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-900);
    }

    .compact-stat-label {
        font-size: 0.6875rem;
        color: var(--gray-500);
        text-transform: uppercase;
    }
</style>
@endsection

@section('content')
<div class="stats-page">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Crime Statistics & Analytics</h1>
            <p class="page-subtitle">Real-time crime data analysis and SARIMA-based forecasting</p>
        </div>
        <div class="header-actions">
            <span id="apiStatus" style="display: flex; align-items: center; font-size: 0.875rem; color: var(--gray-500);">
                <span class="status-dot offline" id="apiStatusDot"></span>
                <span id="apiStatusText">Checking API...</span>
            </span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <span class="filter-label">Year:</span>
            <select class="filter-select" id="yearFilter">
                <option value="">All Years</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Month:</span>
            <select class="filter-select" id="monthFilter">
                <option value="">All Months</option>
                <option value="01">January</option>
                <option value="02">February</option>
                <option value="03">March</option>
                <option value="04">April</option>
                <option value="05">May</option>
                <option value="06">June</option>
                <option value="07">July</option>
                <option value="08">August</option>
                <option value="09">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>
        </div>
        <button class="btn btn-primary" id="applyFilter">Apply Filter</button>
        <button class="btn btn-secondary" id="clearFilter">Clear</button>
        <div style="flex: 1;"></div>
        <span id="filterStatus" style="font-size: 0.8125rem; color: var(--gray-500);">Showing: All data</span>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card blue">
            <div class="stat-label">Total Reports</div>
            <div class="stat-value" id="totalReports">-</div>
            <div class="stat-meta">All time records</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Validated Complaints</div>
            <div class="stat-value" id="validComplaints">-</div>
            <div class="stat-meta">Verified incidents</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-label">Resolved Cases</div>
            <div class="stat-value" id="resolvedCases">-</div>
            <div class="stat-meta">Successfully closed</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Pending Review</div>
            <div class="stat-value" id="pendingReview">-</div>
            <div class="stat-meta">Awaiting validation</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-label">Active Dispatches</div>
            <div class="stat-value" id="activeDispatches">-</div>
            <div class="stat-meta">Ongoing responses</div>
        </div>
    </div>

    <!-- Operational Status -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">� Operational Overview</h3>
            <span style="font-size: 0.75rem; color: var(--gray-500);">Real-time dispatch metrics</span>
        </div>
        <div class="card-body">
            <div class="metrics-grid">
                <div class="metric-box purple">
                    <div class="metric-value" id="activeDispatches2">-</div>
                    <div class="metric-label">Active Dispatches</div>
                </div>
                <div class="metric-box warning" id="overdueBox">
                    <div class="metric-value" id="overdueDispatches">-</div>
                    <div class="metric-label">Overdue (>3min)</div>
                </div>
                <div class="metric-box critical">
                    <div class="metric-value" id="fakeReports">-</div>
                    <div class="metric-label">False Reports</div>
                </div>
                <div class="metric-box info">
                    <div class="metric-value" id="hoaxReports">-</div>
                    <div class="metric-label">Marked Invalid</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Charts Section -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">📈 Crime Trends & SARIMA Forecast</h3>
            <div class="card-controls">
                <select class="filter-select" id="crimeTypeFilter">
                    <option value="">All Crime Types</option>
                    <option value="ASSAULT">Assault</option>
                    <option value="CARNAPPING">Carnapping</option>
                    <option value="CYBER FRAUD">Cyber Fraud</option>
                    <option value="HOMICIDE">Homicide</option>
                    <option value="ILLEGAL DRUGS">Illegal Drugs</option>
                    <option value="MURDER">Murder</option>
                    <option value="PHYSICAL INJURY">Physical Injury</option>
                    <option value="ROBBERY">Robbery</option>
                    <option value="THEFT">Theft</option>
                    <option value="VANDALISM">Vandalism</option>
                </select>
                <select class="filter-select" id="forecastHorizon">
                    <option value="6">6 Month Forecast</option>
                    <option value="12" selected>12 Month Forecast</option>
                    <option value="18">18 Month Forecast</option>
                    <option value="24">24 Month Forecast</option>
                </select>
                <button class="btn btn-secondary" id="refreshForecast">🔄 Refresh</button>
            </div>
        </div>
        <div class="card-body">
            <div id="forecastInfo" class="forecast-info" style="display: none;">
                <strong>Forecast Details:</strong> <span id="forecastInfoText"></span>
            </div>
            <div class="chart-container tall" id="trendChartContainer">
                <canvas id="trendChart"></canvas>
            </div>
            <div class="forecast-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #1D3557;"></div>
                    <span>Historical Data</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #E63946;"></div>
                    <span>SARIMA Forecast</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: rgba(230, 57, 70, 0.2);"></div>
                    <span>95% Confidence Interval</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Charts -->
    <div class="two-col" style="margin-bottom: 1.5rem;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📊 Crime Distribution by Type</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📍 Top Crime Locations</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Risk Assessment & Recommendations -->
    <div class="two-col" style="margin-bottom: 1.5rem;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🚨 Barangay Risk Assessment</h3>
                <div class="card-controls">
                    <select class="filter-select" id="riskMonthsFilter">
                        <option value="1">Last 1 Month</option>
                        <option value="3" selected>Last 3 Months</option>
                        <option value="6">Last 6 Months</option>
                    </select>
                </div>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div id="riskSummary" class="compact-stats" style="margin-bottom: 1rem;">
                    <div class="compact-stat" style="background: #FEF2F2;">
                        <div class="compact-stat-value" style="color: #DC2626;" id="highRiskCount">-</div>
                        <div class="compact-stat-label">High Risk</div>
                    </div>
                    <div class="compact-stat" style="background: #FFFBEB;">
                        <div class="compact-stat-value" style="color: #D97706;" id="mediumRiskCount">-</div>
                        <div class="compact-stat-label">Medium Risk</div>
                    </div>
                    <div class="compact-stat" style="background: #F0FDF4;">
                        <div class="compact-stat-value" style="color: #16A34A;" id="lowRiskCount">-</div>
                        <div class="compact-stat-label">Low Risk</div>
                    </div>
                </div>
                <table class="data-table" id="riskTable">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th style="text-align: center;">Crimes</th>
                            <th style="text-align: center;">Risk</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="riskTableBody">
                        <tr><td colspan="4" class="loading-spinner"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💡 AI Recommendations</h3>
            </div>
            <div class="card-body">
                <ul class="recommendation-list" id="recommendationsList">
                    <li class="loading-spinner"><div class="spinner"></div></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Monthly Warning -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">⚠️ Monthly Crime Forecast Warning</h3>
            <div class="card-controls">
                <input type="month" class="filter-select" id="warningMonthFilter">
                <button class="btn btn-secondary" id="refreshWarning">Get Warning</button>
            </div>
        </div>
        <div class="card-body" id="monthlyWarningContainer">
            <div class="loading-spinner"><div class="spinner"></div></div>
        </div>
    </div>

    <!-- Forecast Deployment Insights -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">🚔 Forecast Deployment Insights</h3>
            <span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">AI-POWERED</span>
        </div>
        <div class="card-body">
            <p style="color: var(--gray-500); margin-bottom: 1rem; font-size: 0.8rem;">
                Actionable deployment recommendations based on SARIMA forecast trends, barangay crime patterns, and station workload analysis.
            </p>

            <div id="forecastInsightsLoading" style="text-align: center; padding: 2rem;">
                <div class="spinner"></div>
                <p style="margin-top: 0.5rem; color: var(--gray-500); font-size: 0.8rem;">Analyzing forecast data...</p>
            </div>

            <div id="forecastInsightsContent" style="display: none;">
                <!-- Crimes Predicted to Increase -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--danger); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        📈 Crimes Predicted to Increase
                    </h4>
                    <div id="crimesIncreasing" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Deployment Suggestions per Area -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 0.9rem; font-weight: 700; color: #1e40af; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        👮 Deployment Suggestions by Area
                    </h4>
                    <div id="deploymentSuggestions">
                        <table class="data-table" style="width: 100%; font-size: 0.8rem;">
                            <thead>
                                <tr>
                                    <th>Station / Area</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody id="deploymentTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Well-Managed Areas -->
                <div>
                    <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--success); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        ✅ Well-Managed Crime Areas
                    </h4>
                    <div id="wellManagedAreas" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <div id="forecastInsightsError" style="display: none; text-align: center; padding: 1.5rem; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                <p style="color: #dc2626; font-weight: 600;">Unable to generate deployment insights</p>
                <p style="color: #7f1d1d; font-size: 0.8rem;">Ensure the SARIMA API is online and forecast data is available.</p>
            </div>
        </div>
    </div>

    <!-- Data Export -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📥 Data Export</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--gray-500); margin-bottom: 1rem; font-size: 0.875rem;">
                Export crime data for external analysis, reporting, or archival purposes.
            </p>
            <div class="export-section">
                <button class="btn btn-success" onclick="exportCrimeDataCSV()">
                    📄 Export Crime Data (CSV)
                </button>
                <button class="btn btn-success" onclick="exportForecastJSON()">
                    📊 Export Forecast Data (JSON)
                </button>
                <button class="btn btn-secondary" onclick="exportFullReport()">
                    📋 Export Full Report (CSV)
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Global state
let trendChart, typeChart, locationChart;
let crimeStats = null;
let forecastData = null;
let currentFilter = { month: '', year: '' };
let insightsData = null;

// Utility functions
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function formatNumber(num) {
    return num ? num.toLocaleString() : '0';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    populateYearFilter();
    setDefaultWarningMonth();
    attachEventListeners();
    
    // Load data in sequence
    loadCrimeStats();
    loadForecast();
    loadInsights();
    loadBarangayRisk();
    loadMonthlyWarning();
    loadForecastInsights();
});

function populateYearFilter() {
    const select = document.getElementById('yearFilter');
    const currentYear = new Date().getFullYear();
    for (let year = 2020; year <= currentYear; year++) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        select.appendChild(option);
    }
}

function setDefaultWarningMonth() {
    const input = document.getElementById('warningMonthFilter');
    const now = new Date();
    input.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function attachEventListeners() {
    document.getElementById('applyFilter').addEventListener('click', applyFilter);
    document.getElementById('clearFilter').addEventListener('click', clearFilter);
    document.getElementById('crimeTypeFilter').addEventListener('change', loadForecast);
    document.getElementById('forecastHorizon').addEventListener('change', loadForecast);
    document.getElementById('refreshForecast').addEventListener('click', loadForecast);
    document.getElementById('riskMonthsFilter').addEventListener('change', loadBarangayRisk);
    document.getElementById('refreshWarning').addEventListener('click', loadMonthlyWarning);
}

function applyFilter() {
    const year = document.getElementById('yearFilter').value;
    const month = document.getElementById('monthFilter').value;
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    if (year && month) {
        currentFilter = { month: `${year}-${month}`, year: '' };
        document.getElementById('filterStatus').textContent = `Showing: ${monthNames[parseInt(month) - 1]} ${year}`;
    } else if (year) {
        currentFilter = { month: '', year: year };
        document.getElementById('filterStatus').textContent = `Showing: Year ${year}`;
    } else {
        currentFilter = { month: '', year: '' };
        document.getElementById('filterStatus').textContent = 'Showing: All data';
    }
    
    loadCrimeStats();
    loadInsights();
    loadForecastInsights();
}

function clearFilter() {
    document.getElementById('yearFilter').value = '';
    document.getElementById('monthFilter').value = '';
    currentFilter = { month: '', year: '' };
    document.getElementById('filterStatus').textContent = 'Showing: All data';
    loadCrimeStats();
    loadInsights();
    loadForecastInsights();
}

// Load Crime Statistics
async function loadCrimeStats() {
    try {
        let url = '/api/statistics/crime-stats';
        const params = new URLSearchParams();
        if (currentFilter.month) params.append('month', currentFilter.month);
        else if (currentFilter.year) params.append('year', currentFilter.year);
        if (params.toString()) url += '?' + params;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            crimeStats = data.data;
            updateQuickStats(data.data.overview);
            renderTypeChart(data.data.byType);
            renderLocationChart(data.data.byLocation);
        }
    } catch (error) {
        console.error('Error loading crime stats:', error);
    }
    
    // Also load DB summary
    loadDbSummary();
}

async function loadDbSummary() {
    try {
        let url = '/api/statistics/report-summary';
        const params = new URLSearchParams();
        if (currentFilter.month) params.append('month', currentFilter.month);
        else if (currentFilter.year) params.append('year', currentFilter.year);
        if (params.toString()) url += '?' + params;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            const d = data.data;
            document.getElementById('totalReports').textContent = formatNumber(d.total);
            document.getElementById('validComplaints').textContent = formatNumber(d.complaints || d.valid);
            document.getElementById('resolvedCases').textContent = formatNumber(d.resolved);
            document.getElementById('pendingReview').textContent = formatNumber(d.checking);
            document.getElementById('activeDispatches').textContent = formatNumber(d.active_dispatches);
            const ad2 = document.getElementById('activeDispatches2');
            if (ad2) ad2.textContent = formatNumber(d.active_dispatches);
            document.getElementById('fakeReports').textContent = formatNumber(d.fake_reports);
            document.getElementById('hoaxReports').textContent = formatNumber(d.invalid);
        }
    } catch (error) {
        console.error('Error loading DB summary:', error);
    }
}

function updateQuickStats(overview) {
    // Additional updates if needed
}

// Load Insights (Recommendations, Stations)
async function loadInsights() {
    try {
        let url = '/api/statistics/insights';
        const params = new URLSearchParams();
        if (currentFilter.month) params.append('month', currentFilter.month);
        else if (currentFilter.year) params.append('year', currentFilter.year);
        if (params.toString()) url += '?' + params;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            insightsData = data.data;
            
            // Update operational metrics
            const dep = data.data.deployment || {};
            document.getElementById('overdueDispatches').textContent = formatNumber(dep.overdue_dispatches);
            
            // Update overdue box styling
            const overdueBox = document.getElementById('overdueBox');
            if (dep.overdue_dispatches > 0) {
                overdueBox.className = 'metric-box critical';
            } else {
                overdueBox.className = 'metric-box success';
            }
            
            // Update recommendations
            renderRecommendations(data.data.recommendations || []);
        }
    } catch (error) {
        console.error('Error loading insights:', error);
    }
}

function renderRecommendations(recommendations) {
    const list = document.getElementById('recommendationsList');
    if (!recommendations.length) {
        list.innerHTML = `<li><span class="recommendation-icon success">✓</span>No critical issues detected. Operations running smoothly.</li>`;
        return;
    }
    
    list.innerHTML = recommendations.map(rec => {
        const isAlert = rec.includes('overdue') || rec.includes('High') || rec.includes('No patrol');
        const iconClass = isAlert ? 'alert' : 'info';
        const icon = isAlert ? '⚠' : 'ℹ';
        return `<li><span class="recommendation-icon ${iconClass}">${icon}</span>${escapeHtml(rec)}</li>`;
    }).join('');
}

// Load Forecast Deployment Insights
async function loadForecastInsights() {
    const loadingEl = document.getElementById('forecastInsightsLoading');
    const contentEl = document.getElementById('forecastInsightsContent');
    const errorEl = document.getElementById('forecastInsightsError');

    loadingEl.style.display = 'block';
    contentEl.style.display = 'none';
    errorEl.style.display = 'none';

    try {
        // Fetch insights (stations + correlation + seasonality) and forecast in parallel
        const params = new URLSearchParams();
        if (currentFilter.month) params.append('month', currentFilter.month);
        else if (currentFilter.year) params.append('year', currentFilter.year);
        const qs = params.toString() ? '?' + params : '';

        const [insightsRes, forecastRes, riskRes] = await Promise.all([
            fetch('/api/statistics/insights' + qs),
            fetch('/api/statistics/forecast?horizon=6'),
            fetch('/api/statistics/barangay-risk?months=3')
        ]);

        const insightsJson = await insightsRes.json();
        const forecastJson = await forecastRes.json();
        const riskJson = await riskRes.json();

        const insights = insightsJson.status === 'success' ? insightsJson.data : null;
        const forecast = forecastJson.status === 'success' ? (forecastJson.data || forecastJson) : null;
        const riskData = riskJson.status === 'success' ? riskJson.data : null;

        if (!insights && !forecast) {
            throw new Error('No data available');
        }

        // --- 1. Crimes Predicted to Increase ---
        const increasingEl = document.getElementById('crimesIncreasing');
        let increasingHtml = '';

        // Analyze forecast trend: compare first 3 months avg vs last 3 months avg
        if (forecast && Array.isArray(forecast) && forecast.length >= 2) {
            const half = Math.floor(forecast.length / 2);
            const firstHalf = forecast.slice(0, half);
            const secondHalf = forecast.slice(half);
            const avgFirst = firstHalf.reduce((s, d) => s + parseFloat(d.forecast || 0), 0) / firstHalf.length;
            const avgSecond = secondHalf.reduce((s, d) => s + parseFloat(d.forecast || 0), 0) / secondHalf.length;
            const changePct = avgFirst > 0 ? ((avgSecond - avgFirst) / avgFirst * 100).toFixed(1) : 0;
            const nextMonth = forecast[0]?.date?.substring(0, 7) || 'Next Period';
            const predictedCount = parseFloat(forecast[0]?.forecast || 0).toFixed(0);

            if (changePct > 0) {
                increasingHtml += `
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <span style="color: #dc2626; font-weight: 700; font-size: 1.25rem;">↑ ${changePct}%</span>
                            <span style="color: #7f1d1d; font-size: 0.8rem;">projected increase</span>
                        </div>
                        <p style="color: #991b1b; font-size: 0.85rem; margin: 0;">
                            Overall crime incidents are predicted to rise. <strong>${nextMonth}</strong> forecast: ~<strong>${predictedCount}</strong> incidents.
                            Consider increasing patrol visibility and crime prevention efforts.
                        </p>
                    </div>`;
            }
        }

        // Analyze barangay risk: show HIGH risk areas with top crimes
        if (riskData && Array.isArray(riskData)) {
            const highRisk = riskData.filter(b => b.risk_level === 'HIGH' || b.risk_level === 'CRITICAL');
            highRisk.slice(0, 4).forEach(area => {
                const topCrimes = (area.top_crimes || []).slice(0, 2).map(c => c.crime_type || c).join(', ');
                const totalCrimes = area.total_crimes || area.crime_count || '?';
                increasingHtml += `
                    <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 1rem;">
                        <div style="font-weight: 700; color: #9a3412; font-size: 0.85rem; margin-bottom: 0.25rem;">
                            📍 ${escapeHtml(area.barangay)}
                        </div>
                        <div style="color: #c2410c; font-size: 0.8rem;">
                            <strong>${totalCrimes}</strong> incidents (3 months) — ${area.risk_level} risk
                        </div>
                        ${topCrimes ? `<div style="color: #78350f; font-size: 0.75rem; margin-top: 0.25rem;">Top crimes: ${escapeHtml(topCrimes)}</div>` : ''}
                        <div style="color: #92400e; font-size: 0.75rem; margin-top: 0.5rem; font-style: italic;">
                            ⚠ Increase patrol presence in this area. Prioritize ${topCrimes ? escapeHtml(topCrimes.split(',')[0]) : 'focus crime'} response.
                        </div>
                    </div>`;
            });
        }

        // Also use correlation data for crime-type specific insights
        if (insights && insights.correlation && insights.correlation.topPairs) {
            const topPairs = insights.correlation.topPairs.slice(0, 3);
            topPairs.forEach(pair => {
                if (pair.count >= 3) {
                    increasingHtml += `
                        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 1rem;">
                            <div style="font-weight: 700; color: #854d0e; font-size: 0.85rem; margin-bottom: 0.25rem;">
                                🔥 ${escapeHtml(pair.crimeType)} in ${escapeHtml(pair.barangay)}
                            </div>
                            <div style="color: #713f12; font-size: 0.8rem;">
                                <strong>${pair.count}</strong> reported incidents — hotspot pattern detected
                            </div>
                            <div style="color: #92400e; font-size: 0.75rem; margin-top: 0.5rem; font-style: italic;">
                                Increase patrol coverage for ${escapeHtml(pair.crimeType)} in this barangay.
                            </div>
                        </div>`;
                }
            });
        }

        if (!increasingHtml) {
            increasingHtml = `<div style="color: var(--gray-500); font-size: 0.85rem; padding: 0.75rem;">No significant crime increase patterns detected in the current forecast period.</div>`;
        }
        increasingEl.innerHTML = increasingHtml;

        // --- 2. Deployment Suggestions by Station ---
        const deployBody = document.getElementById('deploymentTableBody');
        let deployHtml = '';

        if (insights && insights.stations && insights.stations.length > 0) {
            insights.stations.forEach(station => {
                const isOk = station.suggestion === 'OK';
                const rowBg = station.overdue_dispatches > 0 ? '#fef2f2' : (isOk ? '#f0fdf4' : '#fffbeb');
                const sugColor = station.overdue_dispatches > 0 ? '#dc2626' : (isOk ? '#16a34a' : '#d97706');
                const sugText = isOk 
                    ? 'Operations normal. Crime rate well managed in this area.' 
                    : station.suggestion;
                
                deployHtml += `
                    <tr style="background: ${rowBg};">
                        <td style="font-weight: 600;">${escapeHtml(station.station_name)}</td>
                        <td style="color: ${sugColor}; font-size: 0.8rem;">${escapeHtml(sugText)}</td>
                    </tr>`;
            });
        } else {
            deployHtml = `<tr><td colspan="2" style="text-align: center; color: var(--gray-500); padding: 1rem;">No station data available.</td></tr>`;
        }
        deployBody.innerHTML = deployHtml;

        // --- 3. Well-Managed Areas ---
        const wellManagedEl = document.getElementById('wellManagedAreas');
        let wellManagedHtml = '';

        // Check forecast: if overall trend is declining, highlight that
        if (forecast && Array.isArray(forecast) && forecast.length >= 2) {
            const half = Math.floor(forecast.length / 2);
            const firstHalf = forecast.slice(0, half);
            const secondHalf = forecast.slice(half);
            const avgFirst = firstHalf.reduce((s, d) => s + parseFloat(d.forecast || 0), 0) / firstHalf.length;
            const avgSecond = secondHalf.reduce((s, d) => s + parseFloat(d.forecast || 0), 0) / secondHalf.length;
            const changePct = avgFirst > 0 ? ((avgSecond - avgFirst) / avgFirst * 100).toFixed(1) : 0;

            if (changePct <= 0) {
                wellManagedHtml += `
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <span style="color: #16a34a; font-weight: 700; font-size: 1.25rem;">↓ ${Math.abs(changePct)}%</span>
                            <span style="color: #166534; font-size: 0.8rem;">projected decrease</span>
                        </div>
                        <p style="color: #14532d; font-size: 0.85rem; margin: 0;">
                            Overall crime rate is well managed and predicted to decrease. Current policing strategies are effective — maintain current deployment levels.
                        </p>
                    </div>`;
            }
        }

        // Stations with OK status = well-managed
        if (insights && insights.stations) {
            const okStations = insights.stations.filter(s => s.suggestion === 'OK' && s.overdue_dispatches === 0);
            okStations.slice(0, 4).forEach(station => {
                wellManagedHtml += `
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem;">
                        <div style="font-weight: 700; color: #166534; font-size: 0.85rem; margin-bottom: 0.25rem;">
                            🏢 ${escapeHtml(station.station_name)}
                        </div>
                        <div style="color: #166534; font-size: 0.75rem; margin-top: 0.25rem;">
                            ✅ No overdue dispatches. Crime rate well managed in this area.
                        </div>
                    </div>`;
            });
        }

        // Low-risk barangays
        if (riskData && Array.isArray(riskData)) {
            const lowRisk = riskData.filter(b => b.risk_level === 'LOW');
            if (lowRisk.length > 0) {
                wellManagedHtml += `
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 1rem;">
                        <div style="font-weight: 700; color: #065f46; font-size: 0.85rem; margin-bottom: 0.25rem;">
                            🏘️ ${lowRisk.length} Low-Risk Barangay${lowRisk.length > 1 ? 's' : ''}
                        </div>
                        <div style="color: #047857; font-size: 0.8rem;">
                            ${lowRisk.slice(0, 5).map(b => escapeHtml(b.barangay)).join(', ')}${lowRisk.length > 5 ? ` and ${lowRisk.length - 5} more` : ''}
                        </div>
                        <div style="color: #065f46; font-size: 0.75rem; margin-top: 0.25rem;">
                            ✅ These areas show low crime activity. Current policing strategy is effective.
                        </div>
                    </div>`;
            }
        }

        // Seasonality-based insight
        if (insights && insights.seasonality && insights.seasonality.monthAverages) {
            const currentMonth = new Date().getMonth() + 1;
            const currentMonthData = insights.seasonality.monthAverages.find(m => m.month === currentMonth);
            const topMonth = insights.seasonality.topMonths && insights.seasonality.topMonths[0];
            if (currentMonthData && topMonth && currentMonthData.month !== topMonth.month) {
                const ratio = topMonth.averageCount > 0 ? (currentMonthData.averageCount / topMonth.averageCount * 100).toFixed(0) : 0;
                if (ratio < 80) {
                    wellManagedHtml += `
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1rem;">
                            <div style="font-weight: 700; color: #1e40af; font-size: 0.85rem; margin-bottom: 0.25rem;">
                                📅 Seasonal Insight
                            </div>
                            <div style="color: #1d4ed8; font-size: 0.8rem;">
                                This month historically sees ${ratio}% of peak-month crime levels (peak: ${escapeHtml(topMonth.monthName)}).
                            </div>
                            <div style="color: #1e3a8a; font-size: 0.75rem; margin-top: 0.25rem;">
                                Current period is comparatively well-managed. Prepare for increased activity towards ${escapeHtml(topMonth.monthName)}.
                            </div>
                        </div>`;
                }
            }
        }

        if (!wellManagedHtml) {
            wellManagedHtml = `<div style="color: var(--gray-500); font-size: 0.85rem; padding: 0.75rem;">Insufficient data to identify well-managed areas at this time.</div>`;
        }
        wellManagedEl.innerHTML = wellManagedHtml;

        // Show content
        loadingEl.style.display = 'none';
        contentEl.style.display = 'block';

    } catch (error) {
        console.error('Error loading forecast insights:', error);
        loadingEl.style.display = 'none';
        errorEl.style.display = 'block';
    }
}

// Load SARIMA Forecast
async function loadForecast() {
    const horizon = document.getElementById('forecastHorizon').value;
    const crimeType = document.getElementById('crimeTypeFilter').value;
    const container = document.getElementById('trendChartContainer');
    const infoBox = document.getElementById('forecastInfo');
    const statusDot = document.getElementById('apiStatusDot');
    const statusText = document.getElementById('apiStatusText');
    
    // Show loading
    if (trendChart) {
        trendChart.destroy();
        trendChart = null;
    }
    container.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div><canvas id="trendChart" style="display:none;"></canvas>';
    
    try {
        let url = `/api/statistics/forecast?horizon=${horizon}`;
        if (crimeType) url += `&crime_type=${encodeURIComponent(crimeType)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success' && data.data) {
            forecastData = data.data;
            
            // Update API status
            statusDot.className = 'status-dot online';
            statusText.textContent = 'SARIMA API Online';
            
            // Show forecast info
            infoBox.style.display = 'block';
            const firstDate = data.data[0]?.date?.substring(0, 7) || 'N/A';
            const lastDate = data.data[data.data.length - 1]?.date?.substring(0, 7) || 'N/A';
            const avgForecast = (data.data.reduce((sum, d) => sum + parseFloat(d.forecast || 0), 0) / data.data.length).toFixed(1);
            
            document.getElementById('forecastInfoText').innerHTML = 
                `Period: ${firstDate} to ${lastDate} | Scope: ${crimeType || 'All Crimes'} | Avg Predicted: ${avgForecast}/month | Model: SARIMA(0,1,1)(0,1,1)[12]`;
            
            // Restore canvas
            container.innerHTML = '<canvas id="trendChart"></canvas>';
            
            const historicalData = data.historical?.length > 0 ? data.historical : (crimeStats?.monthly || []);
            renderTrendChart(historicalData, data.data);
        } else {
            throw new Error(data.message || 'Invalid forecast data');
        }
    } catch (error) {
        console.error('Error loading forecast:', error);
        statusDot.className = 'status-dot offline';
        statusText.textContent = 'SARIMA API Offline';
        infoBox.style.display = 'none';
        
        container.innerHTML = '<canvas id="trendChart"></canvas>';
        if (crimeStats?.monthly?.length > 0) {
            renderTrendChart(crimeStats.monthly, []);
        } else {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📊</div><p>No forecast data available</p></div>';
        }
    }
}

// Render Trend Chart
function renderTrendChart(historical, forecast) {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;
    
    const historicalLabels = historical.map(d => `${d.year}-${String(d.month).padStart(2, '0')}`);
    const historicalData = historical.map(d => parseInt(d.count) || 0);
    
    const forecastLabels = forecast.map(d => d.date.substring(0, 7));
    const forecastValues = forecast.map(d => parseFloat(d.forecast) || 0);
    const lowerCI = forecast.map(d => parseFloat(d.lower_ci) || 0);
    const upperCI = forecast.map(d => parseFloat(d.upper_ci) || 0);
    
    const allLabels = [...historicalLabels, ...forecastLabels];
    const historicalDataset = [...historicalData, ...Array(forecastLabels.length).fill(null)];
    const forecastDataset = [...Array(historicalLabels.length).fill(null), ...forecastValues];
    
    // Connect historical to forecast
    if (historicalData.length > 0 && forecastValues.length > 0) {
        forecastDataset[historicalLabels.length - 1] = historicalData[historicalData.length - 1];
    }
    
    const allValues = [...historicalData, ...forecastValues].filter(v => v !== null && !isNaN(v));
    const maxValue = Math.max(...allValues, 0);
    const suggestedMax = Math.ceil(maxValue * 1.3);
    
    if (trendChart) trendChart.destroy();
    
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: [
                {
                    label: 'Historical Data',
                    data: historicalDataset,
                    borderColor: '#1D3557',
                    backgroundColor: 'rgba(29, 53, 87, 0.1)',
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: false,
                    pointRadius: 2,
                    pointHoverRadius: 5
                },
                {
                    label: 'SARIMA Forecast',
                    data: forecastDataset,
                    borderColor: '#E63946',
                    borderWidth: 2.5,
                    borderDash: [6, 3],
                    tension: 0.4,
                    fill: false,
                    pointRadius: 3,
                    pointHoverRadius: 6
                },
                {
                    label: 'Upper CI',
                    data: [...Array(historicalLabels.length).fill(null), ...upperCI],
                    borderColor: 'rgba(230, 57, 70, 0.25)',
                    backgroundColor: 'rgba(230, 57, 70, 0.1)',
                    borderWidth: 1,
                    fill: '+1',
                    pointRadius: 0,
                    tension: 0.4
                },
                {
                    label: 'Lower CI',
                    data: [...Array(historicalLabels.length).fill(null), ...lowerCI],
                    borderColor: 'rgba(230, 57, 70, 0.25)',
                    borderWidth: 1,
                    fill: false,
                    pointRadius: 0,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        filter: item => !item.text.includes('CI')
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label.includes('CI')) return null;
                            return `${context.dataset.label}: ${Math.round(context.parsed.y)} crimes`;
                        },
                        afterBody: function(tooltipItems) {
                            // Show crime type breakdown when hovering on a forecast point
                            const forecastItem = tooltipItems.find(t => t.dataset.label === 'SARIMA Forecast' && t.parsed.y !== null);
                            if (!forecastItem || !crimeStats?.byType?.length) return [];

                            const totalHistorical = crimeStats.byType.reduce((sum, d) => sum + (parseInt(d.count) || 0), 0);
                            if (totalHistorical === 0) return [];

                            const forecastTotal = Math.round(forecastItem.parsed.y);
                            const lines = ['\n── Crime Type Breakdown ──'];
                            
                            // Sort by count descending and show top types
                            const sorted = [...crimeStats.byType].sort((a, b) => (parseInt(b.count) || 0) - (parseInt(a.count) || 0));
                            const topTypes = sorted.slice(0, 8);
                            
                            topTypes.forEach(d => {
                                const pct = (parseInt(d.count) || 0) / totalHistorical;
                                const estimated = Math.round(forecastTotal * pct);
                                if (estimated > 0) {
                                    lines.push(`  ${d.type}: ~${estimated}`);
                                }
                            });
                            
                            if (sorted.length > 8) {
                                const otherPct = sorted.slice(8).reduce((sum, d) => sum + ((parseInt(d.count) || 0) / totalHistorical), 0);
                                const otherEst = Math.round(forecastTotal * otherPct);
                                if (otherEst > 0) lines.push(`  Others: ~${otherEst}`);
                            }
                            
                            return lines;
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    max: suggestedMax,
                    title: { display: true, text: 'Number of Crimes', font: { weight: 'bold' } }
                },
                x: { 
                    title: { display: true, text: 'Period', font: { weight: 'bold' } },
                    ticks: { maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });
}

// Render Charts
function renderTypeChart(data) {
    const ctx = document.getElementById('typeChart');
    if (!ctx || !data?.length) return;
    
    if (typeChart) typeChart.destroy();
    
    typeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.type || 'Unknown'),
            datasets: [{
                data: data.map(d => parseInt(d.count) || 0),
                backgroundColor: ['#1D3557', '#457B9D', '#A8DADC', '#F77F00', '#E63946', '#06D6A0', '#8338EC', '#3A86FF', '#FB5607', '#FF006E']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } }
            }
        }
    });
}

function renderLocationChart(data) {
    const ctx = document.getElementById('locationChart');
    if (!ctx || !data?.length) return;
    
    if (locationChart) locationChart.destroy();
    
    locationChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.location || 'Unknown'),
            datasets: [{
                label: 'Reports',
                data: data.map(d => parseInt(d.count) || 0),
                backgroundColor: '#1D3557'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

// Load Barangay Risk Assessment
async function loadBarangayRisk() {
    const months = document.getElementById('riskMonthsFilter').value;
    const tbody = document.getElementById('riskTableBody');
    
    tbody.innerHTML = '<tr><td colspan="4" class="loading-spinner"><div class="spinner"></div></td></tr>';
    
    try {
        const response = await fetch(`/api/statistics/barangay-risk?months=${months}`);
        const result = await response.json();
        const data = result.data || result;
        
        if (!Array.isArray(data) || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--gray-500);">No risk data available</td></tr>';
            return;
        }
        
        // Update risk counts
        const highRisk = data.filter(d => d.risk_level === 'HIGH').length;
        const mediumRisk = data.filter(d => d.risk_level === 'MEDIUM').length;
        const lowRisk = data.filter(d => d.risk_level === 'LOW').length;
        
        document.getElementById('highRiskCount').textContent = highRisk;
        document.getElementById('mediumRiskCount').textContent = mediumRisk;
        document.getElementById('lowRiskCount').textContent = lowRisk;
        
        // Render table
        tbody.innerHTML = data.slice(0, 15).map(item => {
            const badgeClass = item.risk_level === 'HIGH' ? 'badge-high' : 
                              item.risk_level === 'MEDIUM' ? 'badge-medium' : 'badge-low';
            return `
                <tr>
                    <td style="font-weight: 500;">${escapeHtml(item.barangay)}</td>
                    <td style="text-align: center; font-weight: 600;">${item.recent_crimes}</td>
                    <td style="text-align: center;"><span class="badge ${badgeClass}">${item.risk_level}</span></td>
                    <td style="font-size: 0.8125rem;">${escapeHtml(item.recommended_action)}</td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading risk data:', error);
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--danger);">Failed to load risk data</td></tr>';
    }
}

// Load Monthly Warning
async function loadMonthlyWarning() {
    const container = document.getElementById('monthlyWarningContainer');
    const monthInput = document.getElementById('warningMonthFilter');
    
    let dateParam = monthInput.value ? monthInput.value + '-01' : 
        `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}-01`;
    
    container.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';
    
    try {
        const response = await fetch(`/api/statistics/monthly-warning?date=${dateParam}`);
        const result = await response.json();
        const data = result.data || result;
        
        container.innerHTML = `
            <div style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border: 1px solid #FCD34D; border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.5rem;">⚠️</span>
                    <div>
                        <div style="font-size: 1.125rem; font-weight: 700; color: #92400E;">Crime Alert: ${escapeHtml(data.month)}</div>
                        <div style="color: #A16207; font-size: 0.875rem;">${escapeHtml(data.warning)}</div>
                    </div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div style="background: var(--gray-50); border-radius: 10px; padding: 1rem;">
                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">📊 Likely Crime Types</div>
                    ${data.likely_crimes?.length > 0 ? `
                        <ul style="margin: 0; padding-left: 1.25rem; color: var(--gray-700); font-size: 0.875rem;">
                            ${data.likely_crimes.map(c => `<li style="margin-bottom: 0.25rem;"><strong>${escapeHtml(c.crime_type)}</strong> (${c.historical_total} historical)</li>`).join('')}
                        </ul>
                    ` : '<p style="color: var(--gray-500); font-size: 0.875rem;">No patterns detected</p>'}
                </div>
                <div style="background: #EFF6FF; border-radius: 10px; padding: 1rem;">
                    <div style="font-weight: 600; color: #1E40AF; margin-bottom: 0.5rem;">👮 Patrol Recommendation</div>
                    <p style="color: #1E3A8A; margin: 0; font-size: 0.875rem; line-height: 1.5;">${escapeHtml(data.recommended_action)}</p>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error loading monthly warning:', error);
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Failed to load monthly warning</p></div>';
    }
}

// Export Functions
function exportCrimeDataCSV() {
    const year = document.getElementById('yearFilter').value;
    const month = document.getElementById('monthFilter').value;
    const crimeType = document.getElementById('crimeTypeFilter').value;
    
    let url = '/api/statistics/export-crime-data';
    const params = [];
    if (year) params.push(`year=${year}`);
    if (month) params.push(`month=${month}`);
    if (crimeType) params.push(`crime_type=${encodeURIComponent(crimeType)}`);
    if (params.length > 0) url += '?' + params.join('&');
    
    window.location.href = url;
}

function exportForecastJSON() {
    if (!forecastData || forecastData.length === 0) {
        alert('No forecast data available. Please load a forecast first.');
        return;
    }
    
    const horizon = document.getElementById('forecastHorizon').value;
    const crimeType = document.getElementById('crimeTypeFilter').value;
    
    const exportData = {
        metadata: {
            exported_at: new Date().toISOString(),
            crime_type: crimeType || 'All Crimes',
            forecast_horizon: `${horizon} months`,
            model: 'SARIMA(0,1,1)(0,1,1)[12]',
            total_points: forecastData.length
        },
        forecast: forecastData
    };
    
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `forecast_${(crimeType || 'all_crimes').toLowerCase().replace(/ /g, '_')}_${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
}

function exportFullReport() {
    // Generate comprehensive CSV report
    let csv = 'Alert Davao - Crime Statistics Report\n';
    csv += `Generated: ${new Date().toLocaleString()}\n\n`;
    
    // Summary section
    csv += 'SUMMARY\n';
    csv += 'Metric,Value\n';
    csv += `Total Reports,${document.getElementById('totalReports').textContent}\n`;
    csv += `Validated Complaints,${document.getElementById('validComplaints').textContent}\n`;
    csv += `Resolved Cases,${document.getElementById('resolvedCases').textContent}\n`;
    csv += `Pending Review,${document.getElementById('pendingReview').textContent}\n`;
    csv += `Active Dispatches,${document.getElementById('activeDispatches').textContent}\n`;
    csv += `Total Patrol Users,${document.getElementById('patrolOnDuty').textContent}\n\n`;
    
    // Crime by type
    if (crimeStats?.byType?.length > 0) {
        csv += 'CRIME BY TYPE\n';
        csv += 'Crime Type,Count\n';
        crimeStats.byType.forEach(d => {
            csv += `"${d.type}",${d.count}\n`;
        });
        csv += '\n';
    }
    
    // Crime by location
    if (crimeStats?.byLocation?.length > 0) {
        csv += 'TOP LOCATIONS\n';
        csv += 'Location,Count\n';
        crimeStats.byLocation.forEach(d => {
            csv += `"${d.location}",${d.count}\n`;
        });
        csv += '\n';
    }
    
    // Forecast data
    if (forecastData?.length > 0) {
        csv += 'SARIMA FORECAST\n';
        csv += 'Date,Forecast,Lower CI,Upper CI\n';
        forecastData.forEach(d => {
            csv += `${d.date},${d.forecast},${d.lower_ci},${d.upper_ci}\n`;
        });
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `alert_davao_full_report_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
</script>
@endsection
