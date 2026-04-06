@extends('layouts.app')

@section('title', 'Dashboard')

@section('styles')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<!-- Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background-color: #f8fafc;
                color: #1f2937;
                line-height: 1.6;
            }
            
            .dashboard {
                display: flex;
                min-height: 100vh;
            }
            
            /* Sidebar Styles */
            .sidebar {
                width: 250px;
                background: white;
                padding: 2rem 0;
                position: fixed;
                height: 100vh;
                left: 0;
                top: 0;
                z-index: 1000;
                border-right: 1px solid #e5e7eb;
                box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
            }
            
            .sidebar-header {
                padding: 0 1.5rem;
                margin-bottom: 2rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .sidebar-title {
                color: #1D3557;
                font-size: 1.25rem;
                font-weight: 700;
                margin: 0;
            }
            
            .sidebar-close {
                background: none;
                border: none;
                color: #6b7280;
                font-size: 1.25rem;
                margin-left: auto;
                cursor: pointer;
            }
            
            .nav-menu {
                list-style: none;
                padding: 0;
            }
            
            .nav-item {
                margin: 0.25rem 0;
            }
            
            .nav-link {
                display: flex;
                align-items: center;
                padding: 0.875rem 1.5rem;
                color: #6b7280;
                text-decoration: none;
                transition: all 0.3s ease;
                gap: 0.75rem;
                border-radius: 0.375rem;
                margin: 0.125rem 0.75rem;
            }
            
            .nav-link:hover,
            .nav-link.active {
                background: #f3f4f6;
                color: #1D3557;
                border-left: 3px solid #3b82f6;
                font-weight: 500;
            }
            
            .nav-icon {
                width: 20px;
                height: 20px;
                fill: currentColor;
            }
            
            /* Main Content */
            .main-content {
                margin-left: 250px;
                padding: 2rem;
                width: calc(100% - 250px);
            }
            
            .content-header {
                margin-bottom: 2rem;
            }
            
            .content-title {
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 0.5rem;
            }
            
            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            
            .stat-card {
                background: white;
                padding: 1.5rem;
                border-radius: 12px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                border-top: 1px solid #9ca3af;
                border-right: 1px solid #9ca3af;
                border-bottom: 1px solid #9ca3af;
                border-left: 4px solid #3b82f6;
                transition: all 0.3s ease;
                text-decoration: none;
                display: block;
                color: inherit;
                cursor: pointer;
            }
            
            .stat-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            }
            
            .stat-card.total {
                border-left-color: #3b82f6;
            }
            
            .stat-card.verified {
                border-left-color: #10b981;
            }
            
            .stat-card.pending {
                border-left-color: #f59e0b;
            }
            
            .stat-title {
                font-size: 0.875rem;
                color: #6b7280;
                margin-bottom: 0.5rem;
                text-transform: uppercase;
                font-weight: 500;
            }
            
            .stat-value {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 0.25rem;
            }
            
            /* Dashboard Grid */
            .dashboard-grid {
                display: block;
                margin-bottom: 2rem;
            }
            
            .priority-section {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                border: 3px solid #3b82f6;
                width: 100%;
            }
            
            .section-title {
                font-size: 1.125rem;
                font-weight: 600;
                margin-bottom: 1rem;
                color: #1f2937;
            }
            
            .priority-content {
                display: grid;
                grid-template-columns: 350px 1fr;
                gap: 2rem;
                align-items: start;
            }
            
            .priority-cases {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .priority-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
            }
            
            .priority-dot {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                flex-shrink: 0;
            }
            
            .priority-dot.high {
                background-color: #dc2626;
            }
            
            .priority-dot.medium {
                background-color: #f59e0b;
            }
            
            .priority-dot.low {
                background-color: #10b981;
            }
            
            .priority-text {
                font-weight: 500;
            }
            
            .priority-count {
                font-weight: 700;
                margin-left: auto;
            }
            
            .map-placeholder {
                background: #f3f4f6;
                border-radius: 8px;
                height: 500px;
                min-height: 500px;
                width: 100%;
                position: relative;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
            
            #dashboard-mini-map {
                height: 100%;
                width: 100%;
                border-radius: 8px;
                z-index: 1;
            }
            
            .crime-icon-with-count {
                position: relative;
            }
            
            .crime-icon-with-count::after {
                content: attr(data-count);
                position: absolute;
                top: -8px;
                right: -8px;
                background: #ef4444;
                color: white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: bold;
                border: 2px solid white;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            
            .mini-map-controls {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 1000;
                display: flex;
                gap: 5px;
            }
            
            .mini-map-btn {
                background: white;
                border: none;
                padding: 6px 10px;
                border-radius: 4px;
                font-size: 0.75rem;
                cursor: pointer;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                font-weight: 500;
                color: #374151;
                transition: all 0.2s;
            }
            
            .mini-map-btn:hover {
                background: #f3f4f6;
                color: #3b82f6;
            }
            
            .mini-map-btn.active {
                background: #3b82f6;
                color: white;
            }
            
            .gender-chart {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .pie-chart {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                margin: 1rem auto;
                background: conic-gradient(
                    #3b82f6 0deg 180deg,
                    #10b981 180deg 300deg,
                    #6b7280 300deg 360deg
                );
                position: relative;
            }
            
            .pie-chart::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 60px;
                height: 60px;
                background: white;
                border-radius: 50%;
            }
            
            .chart-legend {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                align-items: center;
            }
            
            .legend-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
            }
            
            .legend-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
            }
            
            .legend-dot.male {
                background-color: #3b82f6;
            }
            
            .legend-dot.female {
                background-color: #10b981;
            }
            
            .legend-dot.others {
                background-color: #6b7280;
            }
            
            /* Bottom Chart */
            .bottom-chart {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .line-chart {
                height: 200px;
                background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200" fill="none"><rect width="400" height="200" fill="white"/><polyline points="50,150 100,130 150,120 200,110 250,100 350,80" stroke="%233b82f6" stroke-width="3" fill="none"/><circle cx="50" cy="150" r="4" fill="%233b82f6"/><circle cx="100" cy="130" r="4" fill="%233b82f6"/><circle cx="150" cy="120" r="4" fill="%233b82f6"/><circle cx="200" cy="110" r="4" fill="%233b82f6"/><circle cx="250" cy="100" r="4" fill="%233b82f6"/><circle cx="350" cy="80" r="4" fill="%233b82f6"/></svg>');
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6b7280;
            }
            
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                    transition: transform 0.3s ease;
                }
                
                .main-content {
                    margin-left: 0;
                    width: 100%;
                }
                
                .dashboard-grid {
                    grid-template-columns: 1fr;
                }
                
                .priority-content {
                    grid-template-columns: 1fr;
                    gap: 1.5rem;
                }
            }
            
            /* MAP CLUSTER STYLES COPIED FROM VIEW-MAP */
            /* Professional Cluster Container */
            .professional-cluster-container {
                background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
                border: 3px solid #3b82f6;
                border-radius: 12px;
                padding: 8px;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
                min-width: 80px;
                max-width: 120px;
                position: relative;
                transition: all 0.2s ease;
            }
            
            .professional-cluster-container:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
                border-color: #2563eb;
            }
            
            .cluster-icons-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 4px;
                margin-bottom: 6px;
            }
            
            .cluster-icon-item {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                background: white;
                border-radius: 6px;
                padding: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .cluster-icon-item:hover {
                transform: translateY(-2px);
                box-shadow: 0 3px 6px rgba(0,0,0,0.15);
                background: #f0f9ff;
            }
            
            .cluster-icon-item img {
                display: block;
                filter: drop-shadow(0 0 1px rgba(0,0,0,0.8)) 
                        drop-shadow(0 0 1px rgba(0,0,0,0.8));
                border: 1px solid #374151;
                border-radius: 4px;
                padding: 2px;
                background: white;
            }
            
            .icon-count {
                position: absolute;
                top: -4px;
                right: -4px;
                background: #dc2626;
                color: white;
                font-size: 9px;
                font-weight: 700;
                padding: 1px 4px;
                border-radius: 8px;
                border: 1px solid white;
                min-width: 14px;
                text-align: center;
                line-height: 1.2;
            }
            
            .cluster-total-badge {
                background: #3b82f6;
                color: white;
                font-size: 13px;
                font-weight: 700;
                padding: 4px 8px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            /* Custom cluster icon styles */
            .custom-cluster-icon {
                background: transparent !important;
                border: none !important;
            }
            
            .custom-cluster-icon div {
                text-align: center;
            }
            
            /* Marker cluster group overrides */
            .marker-cluster-small,
            .marker-cluster-medium,
            .marker-cluster-large {
                background: transparent !important;
            }
            
            .marker-cluster-small div,
            .marker-cluster-medium div,
            .marker-cluster-large div {
                background: transparent !important;
                box-shadow: none !important;
            }
            
            .custom-cluster-marker {
                background: transparent;
            }
            
            /* Custom Leaflet Popup Styling */
            .leaflet-popup-content-wrapper {
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .leaflet-popup-content {
                margin: 1rem;
                font-size: 0.875rem;
            }
            
            .popup-title {
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 0.5rem;
            }
            
            .popup-details {
                color: #6b7280;
                line-height: 1.5;
            }

            /* Announcement Modal Styles */
            .modal {
                display: none; 
                position: fixed; 
                z-index: 2000; 
                left: 0;
                top: 0;
                width: 100%; 
                height: 100%; 
                overflow: auto; 
                background-color: rgba(0,0,0,0.5); 
                backdrop-filter: blur(4px);
            }
            .modal-content {
                background-color: #fefefe;
                margin: 5% auto; 
                padding: 0;
                border: 1px solid #888;
                width: 90%;
                max-width: 600px;
                border-radius: 12px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                animation: slideDown 0.3s ease-out;
            }
            @keyframes slideDown {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .modal-header {
                padding: 1.25rem 1.5rem;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f9fafb;
                border-top-left-radius: 12px;
                border-top-right-radius: 12px;
            }
            .modal-title {
                font-size: 1.125rem;
                font-weight: 600;
                color: #111827;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .close-modal {
                color: #9ca3af;
                font-size: 1.5rem;
                line-height: 1;
                cursor: pointer;
                transition: color 0.2s;
            }
            .close-modal:hover {
                color: #111827;
            }
            .modal-body {
                padding: 1.5rem;
            }
            .form-group {
                margin-bottom: 1.25rem;
            }
            .form-label {
                display: block;
                font-size: 0.875rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0.5rem;
            }
            .form-input, .form-textarea {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }
            .form-input:focus, .form-textarea:focus {
                border-color: #3b82f6;
                outline: 0;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            .modal-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid #e5e7eb;
                background-color: #f9fafb;
                border-bottom-left-radius: 12px;
                border-bottom-right-radius: 12px;
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
            }
            .btn {
                padding: 0.625rem 1.25rem;
                border-radius: 0.5rem;
                font-weight: 500;
                font-size: 0.875rem;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
            }
            .btn-secondary {
                background-color: white;
                color: #374151;
                border: 1px solid #d1d5db;
            }
            .btn-secondary:hover {
                background-color: #f3f4f6;
                border-color: #9ca3af;
            }
            .btn-primary {
                background-color: #3b82f6;
                color: white;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }
            .btn-primary:hover {
                background-color: #2563eb;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            .file-upload-wrapper {
              position: relative;
              width: 100%;
              padding: 2rem;
              border: 2px dashed #d1d5db;
              border-radius: 0.5rem;
              display: flex;
              flex-direction: column;
              align-items: center;
              justify-content: center;
              background: #f9fafb;
              transition: all 0.2s;
              cursor: pointer;
              text-align: center;
            }
            .file-upload-wrapper:hover {
                border-color: #3b82f6;
                background: #eff6ff;
            }
            .file-upload-wrapper input[type=file] {
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
                opacity: 0;
                cursor: pointer;
            }
            .upload-icon {
                color: #9ca3af;
                margin-bottom: 0.5rem;
            }
            .file-list {
                margin-top: 0.5rem;
                font-size: 0.75rem;
                color: #4b5563;
                text-align: left;
                width: 100%;
            }
        </style>
@endsection

@section('content')
    @if(session('success'))
        <div style="background-color: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid #10b981; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <svg style="width: 24px; height: 24px; color: #059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span style="font-weight: 500;">{{ session('success') }}</span>
        </div>
    @endif
    <div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            @if($userRole === 'police')
                <h1 class="content-title">Police Station Dashboard</h1>
                <p class="content-subtitle">Manage reports for your assigned station</p>
            @else
                <h1 class="content-title">Dashboard Overview</h1>
                <p class="content-subtitle">System-wide statistics and overview</p>
            @endif
        </div>

        @if($userRole === 'admin' || $userRole === 'super_admin')
            <!-- Add Announcement Button -->
            <button onclick="openAnnouncementModal()" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                Add Announcement
            </button>
        @endif

        <!-- ITEM 15: DATE RANGE FILTER -->
        <form id="dashboardDateFilterForm" action="{{ route('dashboard') }}" method="GET" style="display: flex; gap: 0.5rem; align-items: center; background: white; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">From</label>
                <input id="dashboard-date-from" type="date" name="date_from" value="{{ $dateFrom ?? '' }}" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151;">
            </div>
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">To</label>
                <input id="dashboard-date-to" type="date" name="date_to" value="{{ $dateTo ?? '' }}" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151;">
            </div>
            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 0 1rem; border-radius: 6px; font-weight: 600; font-size: 0.8rem; cursor: pointer; margin-left: 0.5rem; height: 38px; transition: background 0.2s;">Filter</button>
            @if(request('date_from') || request('date_to'))
                <a href="{{ route('dashboard') }}" style="color: #6b7280; text-decoration: none; font-size: 0.8rem; margin-left: 0.5rem; padding: 0.5rem; border-radius: 4px; background: #f3f4f6;">Reset</a>
            @endif
        </form>
    </div>
                
    <!-- Statistics Cards -->
    @php
        $queryParams = [];
        if(isset($dateFrom) && $dateFrom) $queryParams['date_from'] = $dateFrom;
        if(isset($dateTo) && $dateTo) $queryParams['date_to'] = $dateTo;
    @endphp

    <!-- Complaint Summary Cards -->
    <h2 class="section-title">Complaint Summary</h2>
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">

        <!-- Total Complaints -->
        <a href="{{ route('reports', $queryParams) }}" class="stat-card" style="border-left-color: #6366f1; background: #eef2ff;">
            <div class="stat-title" style="color: #4338ca;">TOTAL COMPLAINTS</div>
            <div class="stat-value" style="color: #312e81;">{{ $totalReports }}</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">All submitted reports</div>
        </a>

        <!-- Resolved Complaints -->
        <a href="{{ route('reports', array_merge(['status' => 'resolved'], $queryParams)) }}" class="stat-card" style="border-left-color: #22c55e; background: #f0fdf4;">
            <div class="stat-title" style="color: #15803d;">RESOLVED COMPLAINTS</div>
            <div class="stat-value" style="color: #14532d;">{{ $resolvedReports }}</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">Successfully closed cases</div>
        </a>

        <!-- Fake Reports -->
        <a href="{{ route('reports', array_merge(['validity' => 'invalid'], $queryParams)) }}" class="stat-card" style="border-left-color: #f43f5e; background: #fff1f2;">
            <div class="stat-title" style="color: #be123c;">FAKE REPORTS</div>
            <div class="stat-value" style="color: #881337;">{{ $fakeReports ?? 0 }}</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">Marked as invalid</div>
        </a>

    </div>

    @if($userRole !== 'police')
        <!-- CRIME FORECAST INSIGHTS -->
        <div class="dashboard-grid" style="margin-top: 2rem;">
            <div class="priority-section" style="border-color: #8b5cf6;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 class="section-title" style="margin-bottom: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <svg style="width: 24px; height: 24px; color: #8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        AI Forecast Insights
                    </h2>
                    <span style="background: #f3f4f6; color: #6b7280; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem;">SARIMA Model</span>
                </div>
                
                <div id="forecast-loading" style="text-align: center; padding: 2rem; color: #6b7280;">
                    <div style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #8b5cf6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="margin-top: 0.5rem; font-size: 0.875rem;">Generating predictive analytics...</p>
                </div>
                
                <div id="forecast-content" style="display: none;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <!-- Trend Card -->
                        <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <h3 style="font-size: 0.875rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem; text-transform: uppercase;">Next Month Prediction</h3>
                            <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                                <span id="forecast-count" style="font-size: 2rem; font-weight: 700; color: #1f2937;">--</span>
                                <span style="font-size: 0.875rem; color: #6b7280;">incidents predicted</span>
                            </div>
                            <p style="font-size: 0.8rem; color: #6b7280; margin-top: 0.5rem;">Estimated total incidents for the upcoming month based on historical data patterns.</p>
                        </div>
                        
                        <!-- Recommendation Card -->
                        <div style="background: #eff6ff; padding: 1rem; border-radius: 8px; border: 1px solid #bfdbfe;">
                            <h3 style="font-size: 0.875rem; font-weight: 600; color: #1e40af; margin-bottom: 0.5rem; text-transform: uppercase;">Recommendation</h3>
                            <p id="forecast-recommendation" style="font-size: 0.9rem; color: #1e3a8a; line-height: 1.5;">Analyzing forecast data...</p>
                        </div>
                    </div>
                </div>
                
                <div id="forecast-error" style="display: none; text-align: center; padding: 1.5rem; background: #fef2f2; border-radius: 8px; margin-top: 1rem; border: 1px solid #fecaca;">
                    <svg style="width: 48px; height: 48px; color: #f87171; margin: 0 auto 0.75rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <p style="color: #dc2626; font-weight: 600; margin-bottom: 0.5rem;">AI Forecast Unavailable</p>
                    <p style="color: #7f1d1d; font-size: 0.875rem;">The SARIMA prediction model is not deployed. Contact administrator to enable this feature.</p>
                </div>
            </div>
        </div>
    @else
        <!-- Police Specific Card Extensions if needed (Keeping it simple for now) -->
    @endif
                
                @if($userRole === 'police')
                <!-- Priority Cases and Map (POLICE ONLY) -->
                <div class="dashboard-grid">
                    <div class="priority-section">
                        <h2 class="section-title">Crime Map by Barangay</h2>
                        <div class="priority-content">
                            <div class="priority-cases">
                                <!-- Police View Content -->
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 0.5rem;">FILTER BY BARANGAY</label>
                                    <select id="barangay-filter" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;">
                                        <option value="">All Barangays</option>
                                    </select>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 0.5rem;">FILTER BY CRIME TYPE</label>
                                    <select id="crime-type-filter" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;">
                                        <option value="">All Crime Types</option>
                                    </select>
                                </div>
                                <button onclick="applyDashboardFilters()" style="width: 100%; padding: 0.5rem; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 0.875rem; cursor: pointer; margin-bottom: 0.5rem;">Apply Filters</button>
                                <button onclick="resetDashboardFilters()" style="width: 100%; padding: 0.5rem; background: #6b7280; color: white; border: none; border-radius: 6px; font-size: 0.875rem; cursor: pointer;">Reset</button>
                            </div>
                            
                            <div class="map-placeholder">
                                <div id="dashboard-mini-map"></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
    
    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg style="width: 24px; height: 24px; color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Create New Announcement
                </h3>
                <span class="close-modal" onclick="closeAnnouncementModal()">&times;</span>
            </div>
            <form action="{{ route('announcements.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="title">Announcement Title</label>
                        <input type="text" id="title" name="title" class="form-input" placeholder="e.g., Warning: Heavy Rainfall Expected" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="content">Description / Content</label>
                        <textarea id="content" name="content" class="form-textarea" rows="5" placeholder="Enter the details of the announcement here..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Attachments (Images/PDFs)</label>
                        <div class="file-upload-wrapper" id="drop-zone">
                            <input type="file" name="attachments[]" id="attachments" multiple onchange="updateFileList(this)">
                            <svg class="upload-icon" style="width: 32px; height: 32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <span class="file-upload-text">Click to upload or drag and drop files here<br><span style="font-size: 0.75rem; color: #9ca3af;">(Max 10MB per file)</span></span>
                        </div>
                        <div id="file-list-display" class="file-list"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAnnouncementModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<!-- Leaflet MarkerCluster JavaScript -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
    const dashboardDateFrom = @json($dateFrom ?? null);
    const dashboardDateTo = @json($dateTo ?? null);
    const DASHBOARD_DATE_FILTER_STORAGE_KEY = 'dashboardDateFilterV1';

    function normalizeIsoDate(raw) {
        if (!raw) return null;
        const value = String(raw).trim();
        return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null;
    }

    function readStoredDashboardDates() {
        try {
            const raw = localStorage.getItem(DASHBOARD_DATE_FILTER_STORAGE_KEY);
            if (!raw) return { from: null, to: null };
            const parsed = JSON.parse(raw);
            return {
                from: normalizeIsoDate(parsed?.from),
                to: normalizeIsoDate(parsed?.to),
            };
        } catch (_) {
            return { from: null, to: null };
        }
    }

    function persistDashboardDates(from, to) {
        try {
            localStorage.setItem(
                DASHBOARD_DATE_FILTER_STORAGE_KEY,
                JSON.stringify({
                    from: normalizeIsoDate(from),
                    to: normalizeIsoDate(to),
                })
            );
        } catch (_) {
            // Ignore localStorage errors in restrictive browsers.
        }
    }

    function getNormalizedDashboardDates() {
        const stored = readStoredDashboardDates();
        let from = normalizeIsoDate(dashboardDateFrom) || stored.from;
        let to = normalizeIsoDate(dashboardDateTo) || stored.to;

        if (from && to && from > to) {
            const tmp = from;
            from = to;
            to = tmp;
        }

        return { from, to };
    }

    document.addEventListener('DOMContentLoaded', function() {
        const dateForm = document.getElementById('dashboardDateFilterForm');
        const fromInput = document.getElementById('dashboard-date-from');
        const toInput = document.getElementById('dashboard-date-to');

        if (dateForm && fromInput && toInput) {
            // Persist filter values so accidental page navigation doesn't silently drop context.
            dateForm.addEventListener('submit', function () {
                persistDashboardDates(fromInput.value || null, toInput.value || null);
            });

            // If the backend opened without query params, repopulate UI from stored values.
            if (!normalizeIsoDate(dashboardDateFrom) && !normalizeIsoDate(dashboardDateTo)) {
                const stored = readStoredDashboardDates();
                if (stored.from && !fromInput.value) fromInput.value = stored.from;
                if (stored.to && !toInput.value) toInput.value = stored.to;
            }
        }

        // Keep stored values synced with server-provided active filter.
        const normalizedOnLoad = getNormalizedDashboardDates();
        persistDashboardDates(normalizedOnLoad.from, normalizedOnLoad.to);

        if(document.getElementById('forecast-content')) {
            setTimeout(fetchForecast, 1000); // Small delay to allow UI to settle
        }
        
        // Drag and drop support
        const dropZone = document.getElementById('drop-zone');
        if(dropZone) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#3b82f6';
                dropZone.style.background = '#eff6ff';
            });
            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#d1d5db';
                dropZone.style.background = '#f9fafb';
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#d1d5db';
                dropZone.style.background = '#f9fafb';
                
                const input = document.getElementById('attachments');
                // Note: You can't programmatically set file input value from drag/drop easily due to security,
                // but we can simulate visual feedback. For actual functionality, the click is robust.
                // For this implementation, we'll rely on the input click.
            });
        }
    });

    function openAnnouncementModal() {
        document.getElementById('announcementModal').style.display = 'block';
    }

    function closeAnnouncementModal() {
        document.getElementById('announcementModal').style.display = 'none';
    }
    
    // Close modal if clicked outside
    window.onclick = function(event) {
        const modal = document.getElementById('announcementModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    function updateFileList(input) {
        const list = document.getElementById('file-list-display');
        list.innerHTML = '';
        if (input.files && input.files.length > 0) {
            let html = '<strong>Selected Files:</strong><ul style="list-style: inside; margin-top: 4px;">';
            for (let i = 0; i < input.files.length; i++) {
                html += `<li>${input.files[i].name} <span style="color: #9ca3af;">(${Math.round(input.files[i].size/1024)} KB)</span></li>`;
            }
            html += '</ul>';
            list.innerHTML = html;
        }
    }

    function fetchForecast() {
        // Fetch forecast for next 1 month
        const params = new URLSearchParams({ horizon: '1' });
        const { from: normalizedFrom, to: normalizedTo } = getNormalizedDashboardDates();
        const hasDashboardFilter = !!(normalizedFrom || normalizedTo);

        // Apply dashboard date range filter to AI insight context (exact dates).
        if (normalizedFrom) params.set('date_from', normalizedFrom);
        if (normalizedTo) params.set('date_to', normalizedTo);

        fetch('/api/statistics/forecast?' + params.toString())
            .then(response => response.json())
            .then(data => {
                const loadingEl = document.getElementById('forecast-loading');
                const contentEl = document.getElementById('forecast-content');
                const countEl = document.getElementById('forecast-count');
                const recEl = document.getElementById('forecast-recommendation');
                
                if(loadingEl) loadingEl.style.display = 'none';

                if(data.status === 'success' && data.data) {
                    // SARIMA API returns data as array of ForecastItem objects:
                    // [{date, forecast, lower_ci, upper_ci}, ...]
                    const forecastPoints = Array.isArray(data.data) ? data.data : [];
                    let rawValue = 0;
                    if (Array.isArray(data.data) && data.data.length > 0) {
                        let item = data.data[0];
                        // Extract forecast value from the ForecastItem object
                        if (typeof item === 'object' && item !== null && item.forecast !== undefined) {
                            rawValue = item.forecast;
                        } else if (typeof item === 'number') {
                            rawValue = item;
                        } else if (Array.isArray(item)) {
                            rawValue = item[0];
                        }
                    } else if (typeof data.data === 'number') {
                        rawValue = data.data;
                    }

                    let predictedValue = Math.round(parseFloat(rawValue));

                    if(isNaN(predictedValue)) {
                        console.error('Forecast value is NaN. Raw:', rawValue, 'Data:', data);
                        throw new Error('Invalid forecast value');
                    }

                    if(contentEl) contentEl.style.display = 'block';
                    if(countEl) countEl.innerText = predictedValue;
                    
                    // Generate SPECIFIC & CONCISE recommendation
                    if(recEl) {
                        let recommendation = '';
                        // Future-proofing: Check if API provides a specific recommendation
                        if (data.recommendation) {
                            recommendation = data.recommendation;
                        } else {
                            // Fallback logic with specific directives
                            if(predictedValue > 50) {
                                recommendation = '⚠️ SURGE ALERT: Deploy tactical units to high-density hotspots. Cancel leaves if necessary.';
                            } else if(predictedValue > 30) {
                                recommendation = 'HIGH ACTIVITY: Intensify visibility in commercial zones (18:00-02:00). Check warrant lists.';
                            } else if(predictedValue > 15) {
                                recommendation = 'MODERATE: Focus patrols on residential theft hotspots. conduct checkpoints at key excess points.';
                            } else {
                                recommendation = 'NORMAL: Maintain standard beat patrols. Focus on community engagement and intelligence gathering.';
                            }
                        }
                        if (hasDashboardFilter) {
                            recommendation = `[Filtered Context] ${recommendation}`;
                        }
                        recEl.innerText = recommendation;
                    }
                } else {
                    throw new Error('No forecast data or API offline');
                }
            })
            .catch(err => {
                console.error('Forecast Error:', err);
                const loadingEl = document.getElementById('forecast-loading');
                const errorEl = document.getElementById('forecast-error');
                if(loadingEl) loadingEl.style.display = 'none';
                if(errorEl) errorEl.style.display = 'block';
            });
    }
</script>

<script>
    // Global variables for mini map
    let miniMap;
    let miniMarkers = [];
    let miniMarkerClusterGroup; // Cluster group for performance
    let miniReports = [];
    let miniBarangays = [];
    let miniCrimeTypes = [];
    
    // Crime type to icon mapping (complete mapping from view-map)
    const crimeIcons = {
        // Violent Crimes
        'murder': '/legends/squareMURDER.png',
        'homicide': '/legends/diamondHOMICIDE.png',
        'rape': '/legends/moonRAPE.png',
        'sexual assault': '/legends/tearSEXUALASSAULT.png',
        'physical injury': '/legends/001-pointed-star.png',
        'assault': '/legends/001-pointed-star.png',
        
        // Domestic & Personal
        'domestic violence': '/legends/flagDomesticViolence.png',
        'harassment': '/legends/quoteHARASSMENT.png',
        'threats': '/legends/playTHREATS.png',
        'threatening': '/legends/playTHREATS.png',
        
        // Property Crimes
        'robbery': '/legends/002-rectangle.png',
        'burglary': '/legends/circleBurglary.png',
        'break-in': '/legends/hexagonalBreak-in.png',
        'breaking and entering': '/legends/hexagonalBreak-in.png',
        'theft': '/legends/003-ellipse.png',
        'carnapping': '/legends/001-close.png',
        'motornapping': '/legends/002-plus.png',
        'vehicle theft': '/legends/001-close.png',
        'motorcycle theft': '/legends/002-plus.png',
        
        // Financial & Cyber
        'fraud': '/legends/bookmarkFRAUD.png',
        'cyber crime': '/legends/webCyberCRIME.png',
        'cybercrime': '/legends/webCyberCRIME.png',
        'hacking': '/legends/webCyberCRIME.png',
        
        // Missing Person
        'missing person': '/legends/missingPerson.png',
        'missing': '/legends/missingPerson.png',
        
        // Others
        'others': '/legends/moreOTHERS.png',
        'other': '/legends/moreOTHERS.png'
    };
    
    // Davao City bounds (approximate)
    const davaoCityBounds = [
        [6.9, 125.2],  // Southwest corner
        [7.5, 125.7]   // Northeast corner
    ];
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            // Initialize the mini map centered on Davao City with bounds restriction
            miniMap = L.map('dashboard-mini-map', {
                zoomControl: true, // Enabled zoom control for better UX
                attributionControl: false,
                maxBounds: davaoCityBounds,
                maxBoundsViscosity: 1.0,
                minZoom: 11,
                maxZoom: 18
            }).setView([7.1907, 125.4553], 12);
    
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '',
                maxZoom: 18,
            }).addTo(miniMap);
            
            // Initialize marker cluster group with custom icons (NO ANIMATIONS)
            miniMarkerClusterGroup = L.markerClusterGroup({
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
                spiderfyOnMaxZoom: false, // Disable spider animation
                animate: false,           // Disable all animations
                animateAddingMarkers: false,
                disableClusteringAtZoom: 16,
                removeOutsideVisibleBounds: true,
                chunkedLoading: true,
                chunkInterval: 200,
                chunkDelay: 50,
                iconCreateFunction: function(cluster) {
                    const childMarkers = cluster.getAllChildMarkers();
                    const crimeTypes = new Map(); // Use Map to track counts
                    
                    // Collect crime types with counts from all markers in cluster
                    childMarkers.forEach(marker => {
                        // We store crimeType in the marker options for easy access
                        if (marker.options.crimeType) {
                            const type = marker.options.crimeType.toLowerCase();
                            crimeTypes.set(type, (crimeTypes.get(type) || 0) + 1);
                        }
                    });
                    
                    // Get top 6 crime types by count
                    const sortedTypes = Array.from(crimeTypes.entries())
                        .sort((a, b) => b[1] - a[1])
                        .slice(0, 6);
                    
                    const count = childMarkers.length;
                    
                    // Build crime type icons HTML with tooltips
                    let iconsHtml = '';
                    sortedTypes.forEach(([type, typeCount]) => {
                        const icon = getCrimeIcon(type);
                        if (icon) {
                            iconsHtml += `<div class="cluster-icon-item" title="${type}: ${typeCount}">
                                <img src="${icon}" style="width: 20px; height: 20px;" alt="${type}"/>
                                <span class="icon-count">${typeCount}</span>
                            </div>`;
                        }
                    });
                    
                    // Professional cluster container with crime icons
                    const clusterHtml = `
                        <div class="professional-cluster-container" title="${count} total crimes">
                            <div class="cluster-icons-grid">
                                ${iconsHtml}
                            </div>
                            <div class="cluster-total-badge">${count}</div>
                        </div>
                    `;
                    
                    // Use larger icon size to prevent clipping since content is ~80-120px wide
                    return L.divIcon({
                        html: clusterHtml,
                        className: 'custom-cluster-icon',
                        iconSize: L.point(100, 100),
                        iconAnchor: L.point(50, 50)
                    });
                }
            });
            
            // Add cluster group to map
            miniMap.addLayer(miniMarkerClusterGroup);
            
            // Load initial data
            loadMiniMapReports();
        }, 100);
    });
    
    // Function to load reports from API
    function loadMiniMapReports(filters = {}) {
        const { from: normalizedFrom, to: normalizedTo } = getNormalizedDashboardDates();
        const effectiveFilters = { ...filters };
        if (normalizedFrom) effectiveFilters.date_from = normalizedFrom;
        if (normalizedTo) effectiveFilters.date_to = normalizedTo;

        const params = new URLSearchParams(effectiveFilters).toString();
        const url = '{{ route("api.reports") }}' + (params ? '?' + params : '');
        
        console.log('Loading mini map reports from:', url);
        
        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                // Check content type
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    console.error('Expected JSON response but got:', contentType);
                    return response.text().then(text => {
                        throw new Error('Server returned non-JSON response');
                    });
                }
            })
            .then(data => {
                console.log('Reports count:', data.reports ? data.reports.length : 0);
                
                miniReports = data.reports || [];
                miniBarangays = data.barangays || [];
                miniCrimeTypes = data.crime_types || [];
                
                // Populate filter dropdowns
                populateBarangayFilter();
                populateCrimeTypeFilter();
                
                updateMiniMapMarkers(miniReports);
            })
            .catch(error => {
                console.error('Error loading mini map reports:', error);
                // Silent fail for dashboard is better than alert loop
            });
    }
    
    // Function to clean crime type text
    function cleanCrimeType(crimeType) {
        if (!crimeType) return '';
        
        // Decode HTML entities
        const textarea = document.createElement('textarea');
        textarea.innerHTML = crimeType;
        let cleaned = textarea.value;
        
        // Remove brackets and quotes
        cleaned = cleaned.replace(/[\[\]"']/g, '');
        
        // Remove extra whitespace
        cleaned = cleaned.trim().replace(/\s+/g, ' ');
        
        return cleaned;
    }
    
    // Shared clean text function
    function cleanText(str) {
        return cleanCrimeType(str);
    }
    
    // Function to get icon for crime type
    function getCrimeIcon(crimeType) {
        if (!crimeType) return null;
        
        // Clean the crime type name first
        const cleanedType = cleanCrimeType(crimeType);
        const normalizedType = cleanedType.toLowerCase().trim();
        return crimeIcons[normalizedType] || null;
    }
    
    // Function to get color for crime type (for popups)
    function getCrimeColor(offense) {
        if (!offense) return '#6b7280';
        const offenseLower = offense.toLowerCase();
        
        if (offenseLower.includes('physical injury') || offenseLower.includes('assault') || offenseLower.includes('murder') || offenseLower.includes('homicide')) return '#dc2626';
        if (offenseLower.includes('theft') || offenseLower.includes('robbery') || offenseLower.includes('burglary')) return '#ea580c';
        if (offenseLower.includes('drug')) return '#7c2d12';
        return '#3b82f6';
    }

    // Function to create custom marker icon
    function createCrimeMarker(crimeType) {
        const iconUrl = getCrimeIcon(crimeType);
        
        if (iconUrl) {
            // Single crime with custom icon
            return L.icon({
                iconUrl: iconUrl,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14]
            });
        } else {
            // Default marker for unknown crime type
            return L.divIcon({
                className: 'custom-marker',
                html: '<div style="background-color: #6b7280; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
        }
    }
    
     function populateBarangayFilter() {
         const select = document.getElementById('barangay-filter');
         const currentValue = select.value;
         
         // Keep "All Barangays" option
         select.innerHTML = '<option value="">All Barangays</option>';
         
         miniBarangays.forEach(barangay => {
             const option = document.createElement('option');
             const cleanedBarangay = cleanText(barangay);
             option.value = cleanedBarangay;
             option.textContent = cleanedBarangay;
             select.appendChild(option);
         });
         
         select.value = currentValue;
     }
     
     function populateCrimeTypeFilter() {
         const select = document.getElementById('crime-type-filter');
         const currentValue = select.value;
         
         // Keep "All Crime Types" option
         select.innerHTML = '<option value="">All Crime Types</option>';
         
         miniCrimeTypes.forEach(crimeType => {
             const option = document.createElement('option');
             const cleanedCrimeType = cleanText(crimeType);
             option.value = cleanedCrimeType;
             option.textContent = cleanedCrimeType.charAt(0).toUpperCase() + cleanedCrimeType.slice(1);
             select.appendChild(option);
         });
         
         select.value = currentValue;
     }
    
    // Function to update map markers
    function updateMiniMapMarkers(reports) {
        if (!miniMap || !reports) return;
        console.log('Updating mini map markers with', reports.length, 'reports');
        
        // Clear existing markers from cluster group
        if (miniMarkerClusterGroup) {
            miniMarkerClusterGroup.clearLayers();
        }
        miniMarkers = [];
        
        // Prepare markers
        const markersToAdd = [];
        
        reports.forEach(report => {
            // Support both naming conventions (lat/lng vs latitude/longitude)
            const lat = parseFloat(report.lat || report.latitude);
            const lng = parseFloat(report.lng || report.longitude);
            
            if (isNaN(lat) || isNaN(lng)) return;
            
            // Determine crime type
            let crimeType = 'other';
            let dateCommitted = 'N/A';
            let timeCommitted = '';
            
            if (report.crimes && report.crimes.length > 0) {
                // If it's a cluster/group of crimes
                crimeType = report.crimes[0].crime_type;
                dateCommitted = report.crimes[0].date_committed || 'N/A';
            } else if (report.crime_type) {
                // Single report
                crimeType = report.crime_type;
                dateCommitted = report.date_committed || 'N/A';
                timeCommitted = report.time_committed || '';
            } else if (report.offense) {
                // CSV data format
                crimeType = report.offense;
                dateCommitted = report.date_committed || 'N/A';
                timeCommitted = report.time_committed || '';
            }
            
            const markerIcon = createCrimeMarker(crimeType);
            const marker = L.marker([lat, lng], { 
                icon: markerIcon,
                crimeType: crimeType // Store for cluster function
            });
            
            // Create popup content
            let popupContent = `
                <div style="min-width: 200px;">
                    <div style="font-weight: 700; font-size: 1rem; color: #1f2937; margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                        ${cleanText(crimeType)}
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem;">
                        <strong>Location:</strong> ${report.barangay || 'Unknown'}
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280;">
                        <strong>Date:</strong> ${dateCommitted} ${timeCommitted}
                    </div>
                    ${report.status ? `<div style="margin-top: 0.5rem; display: inline-block; padding: 2px 8px; border-radius: 4px; background: ${report.status === 'resolved' ? '#dcfce7' : '#fef9c3'}; color: ${report.status === 'resolved' ? '#166534' : '#854d0e'}; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">${report.status}</div>` : ''}
                </div>
            `;
            
            marker.bindPopup(popupContent, {
                className: 'custom-popup'
            });
            
            markersToAdd.push(marker);
        });
        
        // Add all markers to cluster group at once
        if (markersToAdd.length > 0) {
            miniMarkerClusterGroup.addLayers(markersToAdd);
            
            // Fit bounds to show markers if not filtering by default
            if (markersToAdd.length < 500) { 
                miniMap.fitBounds(miniMarkerClusterGroup.getBounds().pad(0.1));
            }
        }
    }
    
    // Filter functions
    window.applyDashboardFilters = function() {
        const barangay = document.getElementById('barangay-filter').value;
        const crimeType = document.getElementById('crime-type-filter').value;
        
        const filters = {};
        if (barangay) filters.barangay = barangay;
        if (crimeType) filters.crime_type = crimeType;
        
        loadMiniMapReports(filters);
    };
    
    window.resetDashboardFilters = function() {
        document.getElementById('barangay-filter').value = '';
        document.getElementById('crime-type-filter').value = '';
        loadMiniMapReports({});
    };
    // Lightweight dashboard stat refresh (every 30s + on socket events)
    function checkForNewStats() {
        if (document.visibilityState !== 'visible') return;
        fetch('/api/reports/counts', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data) return;
            const statCards = document.querySelectorAll('.stat-value');
            // Update the 3 complaint summary cards
            const newValues = [data.total ?? '', data.resolved ?? '', data.fake ?? ''];
            statCards.forEach((card, i) => {
                if (newValues[i] !== undefined && card.textContent.trim() !== String(newValues[i])) {
                    card.textContent = newValues[i];
                    card.style.animation = 'flash 0.5s';
                    setTimeout(() => card.style.animation = '', 500);
                }
            });
        })
        .catch(() => {});
    }
    
    // Refresh every 30 seconds + immediately on socket live updates
    setInterval(checkForNewStats, 30000);
    window.addEventListener('adminLiveUpdate', () => {
        checkForNewStats();
        if (document.getElementById('forecast-content')) {
            fetchForecast();
        }
    });
</script>

<style>
@keyframes flash {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; background-color: #fef3c7; }
}
</style>
@endsection
