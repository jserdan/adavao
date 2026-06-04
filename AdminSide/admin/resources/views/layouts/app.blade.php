<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>AlertDavao - @yield('title')</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            
            /* Darken container borders for better UI visibility */
            .reports-table-container,
            .chart-section,
            .map-container,
            .flagged-users-table-container,
            .content-body,
            .modal-content,
            .user-button,
            .dropdown-menu {
                border-color: #9ca3af !important;
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
                text-decoration: none;
                cursor: pointer;
                transition: color 0.2s ease;
            }
            
            .sidebar-title:hover {
                color: #3b82f6;
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
            
            /* Top Navigation Bar */
            .top-nav {
                display: flex;
                justify-content: flex-end;
                align-items: center;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .user-menu {
                position: relative;
                display: inline-block;
            }
            
            .user-button {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem 1rem;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }
            
            .user-button:hover {
                background: #f9fafb;
                border-color: #d1d5db;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .user-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 0.875rem;
            }
            
            .user-info {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .user-name {
                font-weight: 600;
                font-size: 0.875rem;
                color: #1f2937;
                line-height: 1.2;
            }
            
            .user-email {
                font-size: 0.75rem;
                color: #6b7280;
                line-height: 1.2;
            }
            
            .dropdown-icon {
                width: 16px;
                height: 16px;
                fill: #6b7280;
                transition: transform 0.2s ease;
            }
            
            .user-menu.active .dropdown-icon {
                transform: rotate(180deg);
            }
            
            .dropdown-menu {
                position: absolute;
                top: calc(100% + 0.5rem);
                right: 0;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                min-width: 200px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.2s ease;
                z-index: 1000;
            }
            
            .user-menu.active .dropdown-menu {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }
            
            .dropdown-item {
                padding: 0.75rem 1rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: #374151;
                text-decoration: none;
                transition: background 0.2s ease;
                font-size: 0.875rem;
                border: none;
                background: none;
                width: 100%;
                text-align: left;
                cursor: pointer;
            }
            
            .dropdown-item:hover {
                background: #f3f4f6;
            }
            
            .dropdown-item:first-child {
                border-radius: 8px 8px 0 0;
            }
            
            .dropdown-item:last-child {
                border-radius: 0 0 8px 8px;
                border-top: 1px solid #e5e7eb;
                color: #dc2626;
            }
            
            .dropdown-item:last-child:hover {
                background: #fee2e2;
            }
            
            .dropdown-icon-small {
                width: 18px;
                height: 18px;
                fill: currentColor;
            }
            
            .content-header {
                margin-bottom: 2rem;
            }
            
            .content-title {
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 0.5rem;
            }
            
            .content-subtitle {
                color: #6b7280;
                font-size: 1rem;
            }
            
            .content-body {
                background: white;
                border-radius: 12px;
                padding: 2rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                min-height: 400px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                gap: 1rem;
            }
            
            .placeholder-text {
                color: #6b7280;
                font-size: 1.125rem;
                text-align: center;
            }
            
            .placeholder-icon {
                width: 64px;
                height: 64px;
                opacity: 0.3;
                fill: #6b7280;
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
                
                .user-info {
                    display: none;
                }
                
                .user-button {
                    padding: 0.5rem;
                }
            }
        </style>
        @yield('styles')
    </head>
    <body>
        <div class="dashboard">
            <!-- Sidebar -->
            <nav class="sidebar">
                <div class="sidebar-header">
                    <a href="{{ route('dashboard') }}" class="sidebar-title">AlertDavao</a>
                </div>
                
                <ul class="nav-menu">
                    <!-- Navigation for all users -->
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reports') }}" class="nav-link {{ request()->routeIs('reports') || request()->routeIs('reassignment-requests') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                            </svg>
                            Reports
                        </a>
                        @if(auth()->user() && auth()->user()->hasRole('admin'))
                        <ul style="padding-left: 2rem; margin-top: 0.5rem;">
                            <li class="nav-item">
                                <a href="{{ route('reassignment-requests') }}" class="nav-link {{ request()->routeIs('reassignment-requests') ? 'active' : '' }}" style="font-size: 0.875rem;">
                                    Requests
                                </a>
                            </li>
                        </ul>
                        @endif
                    </li>

                    {{-- Unified Dispatches View for Admin and Enforcer --}}
                    @php
                        $userRole = '';
                        if(auth()->check()) {
                            $userRole = strtolower(auth()->user()->role ?? auth()->user()->user_role ?? '');
                        }
                    @endphp
                    @if(auth()->check() && (in_array($userRole, ['admin', 'police', 'enforcer']) || str_contains(auth()->user()->email, 'alertdavao.ph')))
                    <li class="nav-item">
                        <a href="{{ route('dispatches') }}" class="nav-link {{ request()->routeIs('dispatches') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M3 11h18l-1 8H4l-1-8zm2-3h14l1 3H4l1-3zm3 14a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/>
                            </svg>
                            Dispatches
                        </a>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a href="{{ route('messages') }}" class="nav-link {{ request()->routeIs('messages') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            Messages
                        </a>
                    </li>
                    
                    <!-- Debug: Show current user role -->
                    @if(auth()->user())
                        <!-- User: {{ auth()->user()->email }} | Role: {{ auth()->user()->role }} -->
                    @endif
                    
                    <!-- Admin-only navigation -->
                    @if(auth()->user() && (auth()->user()->role === 'admin' || str_contains(auth()->user()->email, 'alertdavao.ph')))
                    <li class="nav-item">
                        <a href="{{ route('users') }}" class="nav-link {{ request()->routeIs('users') || request()->routeIs('flagged-users') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Users
                        </a>
                        <ul style="padding-left: 2rem; margin-top: 0.5rem;">
                            <li class="nav-item">
                                <a href="{{ route('flagged-users') }}" class="nav-link {{ request()->routeIs('flagged-users') ? 'active' : '' }}" style="font-size: 0.875rem;">
                                    Flagged
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('personnel') }}" class="nav-link {{ request()->routeIs('personnel') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
                            Personnel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('verification') }}" class="nav-link {{ request()->routeIs('verification') || request()->routeIs('verification-history') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Verification
                        </a>
                        <ul style="padding-left: 2rem; margin-top: 0.5rem;">
                            <li class="nav-item">
                                <a href="{{ route('verification-history') }}" class="nav-link {{ request()->routeIs('verification-history') ? 'active' : '' }}" style="font-size: 0.875rem;">
                                    <svg class="nav-icon" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    History
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('statistics') }}" class="nav-link {{ request()->routeIs('statistics') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <rect x="7" y="7" width="3" height="9"/>
                                <rect x="14" y="7" width="3" height="5"/>
                            </svg>
                            Statistics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('view-map') }}" class="nav-link {{ request()->routeIs('view-map') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            View Map
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('decryption-tester') }}" class="nav-link {{ request()->routeIs('decryption-tester') || request()->routeIs('decryption-tester.decrypt') ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="10" rx="2" ry="2"/>
                                <path d="M7 11V8a5 5 0 0 1 10 0v3"/>
                            </svg>
                            Decryption Tester
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="main-content">
                <!-- Top Navigation Bar -->
                <div class="top-nav">
                    <div class="user-menu" id="userMenu">
                        <button class="user-button" onclick="toggleUserMenu()">
                            <div class="user-avatar">
                                @if(auth()->user() && auth()->user()->firstname)
                                    {{ strtoupper(substr(auth()->user()->firstname, 0, 1)) }}{{ strtoupper(substr(auth()->user()->lastname ?? '', 0, 1)) }}
                                @else
                                    A
                                @endif
                            </div>
                            <div class="user-info">
                                <span class="user-name">{{ auth()->user()->firstname ?? 'Admin' }} {{ auth()->user()->lastname ?? '' }}</span>
                                <span class="user-email">{{ auth()->user()->email ?? '' }}</span>
                            </div>
                            <svg class="dropdown-icon" viewBox="0 0 20 20">
                                <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                            </svg>
                        </button>
                        <div class="dropdown-menu">
                            <a href="{{ route('profile') }}" class="dropdown-item">
                                <svg class="dropdown-icon-small" viewBox="0 0 24 24">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                My Profile
                            </a>
                            <a href="#" class="dropdown-item">
                                <svg class="dropdown-icon-small" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M12 1v6m0 6v6m-8.66-4l5.2-3M16.46 9l5.2 3m-5.2 3l5.2-3M4.54 15l5.2 3M1 12h6m6 0h6"/>
                                </svg>
                                Settings
                            </a>
                            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <svg class="dropdown-icon-small" viewBox="0 0 24 24">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16 17 21 12 16 7"/>
                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                @yield('content')
            </main>
        </div>
        
        <!-- Custom Alert Modal -->
        <div id="customAlertModal" class="custom-modal-overlay">
            <div class="custom-modal">
                <div class="custom-modal-icon" id="alertIcon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h3 class="custom-modal-title" id="alertTitle">Alert</h3>
                <p class="custom-modal-message" id="alertMessage"></p>
                <div class="custom-modal-buttons">
                    <button class="custom-modal-btn custom-modal-btn-primary" id="alertOkBtn">OK</button>
                </div>
            </div>
        </div>
        
        <!-- Custom Confirm Modal -->
        <div id="customConfirmModal" class="custom-modal-overlay">
            <div class="custom-modal">
                <div class="custom-modal-icon confirm-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h3 class="custom-modal-title" id="confirmTitle">Confirm</h3>
                <p class="custom-modal-message" id="confirmMessage"></p>
                <div class="custom-modal-buttons">
                    <button class="custom-modal-btn custom-modal-btn-secondary" id="confirmCancelBtn">Cancel</button>
                    <button class="custom-modal-btn custom-modal-btn-primary" id="confirmOkBtn">Confirm</button>
                </div>
            </div>
        </div>
        
        <style>
            .custom-modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 9999;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.2s ease;
            }
            
            .custom-modal-overlay.active {
                display: flex;
            }
            
            .custom-modal {
                background: white;
                border-radius: 16px;
                padding: 2rem;
                max-width: 450px;
                width: 90%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                animation: slideUp 0.3s ease;
                text-align: center;
            }
            
            .custom-modal-icon {
                width: 64px;
                height: 64px;
                margin: 0 auto 1.5rem;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #dbeafe;
                color: #1e40af;
            }
            
            .custom-modal-icon.error-icon {
                background: #fee2e2;
                color: #991b1b;
            }
            
            .custom-modal-icon.success-icon {
                background: #d1fae5;
                color: #065f46;
            }
            
            .custom-modal-icon.confirm-icon {
                background: #fef3c7;
                color: #92400e;
            }
            
            .custom-modal-icon svg {
                width: 32px;
                height: 32px;
            }
            
            /* Global Loading Overlay */
            #globalLoading {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.2s ease;
            }
            
            .loading-container {
                background: white;
                border-radius: 12px;
                padding: 2rem;
                text-align: center;
                min-width: 200px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }
            
            .loading-spinner {
                width: 48px;
                height: 48px;
                border: 4px solid #e5e7eb;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 1rem;
            }
            
            .loading-message {
                color: #1f2937;
                font-size: 14px;
                font-weight: 500;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            .custom-modal-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 0.75rem;
            }
            
            .custom-modal-message {
                font-size: 1rem;
                color: #6b7280;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            
            .custom-modal-buttons {
                display: flex;
                gap: 0.75rem;
                justify-content: center;
            }
            
            .custom-modal-btn {
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
                border: none;
                transition: all 0.2s ease;
                min-width: 100px;
            }
            
            .custom-modal-btn-primary {
                background: #3b82f6;
                color: white;
            }
            
            .custom-modal-btn-primary:hover {
                background: #2563eb;
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
            }
            
            .custom-modal-btn-secondary {
                background: #f3f4f6;
                color: #374151;
            }
            
            .custom-modal-btn-secondary:hover {
                background: #e5e7eb;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
        
        <!-- Global Loading Overlay -->
    <body>
        <div id="globalLoading">
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <div class="loading-message" id="loadingMessage">Loading...</div>
            </div>
        </div>
        
        <script src="https://cdn.socket.io/4.7.4/socket.io.min.js"></script>
        @yield('scripts')
        
        <script>
            // Custom Alert Function
            function customAlert(message, type = 'info', title = '') {
                return new Promise((resolve) => {
                    const modal = document.getElementById('customAlertModal');
                    const icon = document.getElementById('alertIcon');
                    const titleEl = document.getElementById('alertTitle');
                    const messageEl = document.getElementById('alertMessage');
                    const okBtn = document.getElementById('alertOkBtn');
                    
                    // Set icon based on type
                    icon.className = 'custom-modal-icon';
                    if (type === 'success') {
                        icon.className += ' success-icon';
                        icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>`;
                        titleEl.textContent = title || 'Success';
                    } else if (type === 'error') {
                        icon.className += ' error-icon';
                        icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>`;
                        titleEl.textContent = title || 'Error';
                    } else {
                        icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>`;
                        titleEl.textContent = title || 'Alert';
                    }
                    
                    messageEl.textContent = message;
                    modal.classList.add('active');
                    
                    const handleOk = () => {
                        modal.classList.remove('active');
                        okBtn.removeEventListener('click', handleOk);
                        resolve(true);
                    };
                    
                    okBtn.addEventListener('click', handleOk);
                });
            }
            
            // Custom Confirm Function
            function customConfirm(message, title = 'Confirm Action') {
                return new Promise((resolve) => {
                    const modal = document.getElementById('customConfirmModal');
                    const titleEl = document.getElementById('confirmTitle');
                    const messageEl = document.getElementById('confirmMessage');
                    const okBtn = document.getElementById('confirmOkBtn');
                    const cancelBtn = document.getElementById('confirmCancelBtn');
                    
                    titleEl.textContent = title;
                    messageEl.textContent = message;
                    modal.classList.add('active');
                    
                    const handleOk = () => {
                        modal.classList.remove('active');
                        okBtn.removeEventListener('click', handleOk);
                        cancelBtn.removeEventListener('click', handleCancel);
                        resolve(true);
                    };
                    
                    const handleCancel = () => {
                        modal.classList.remove('active');
                        okBtn.removeEventListener('click', handleOk);
                        cancelBtn.removeEventListener('click', handleCancel);
                        resolve(false);
                    };
                    
                    okBtn.addEventListener('click', handleOk);
                    cancelBtn.addEventListener('click', handleCancel);
                });
            }
            
            // Override native alert and confirm
            window.alert = customAlert;
            window.confirm = customConfirm;
            
            // Global Loading Functions
            function showLoading(message = 'Loading...') {
                const loadingEl = document.getElementById('globalLoading');
                const messageEl = document.getElementById('loadingMessage');
                if (loadingEl) {
                    messageEl.textContent = message;
                    loadingEl.style.display = 'flex';
                }
            }
            
            function hideLoading() {
                const loadingEl = document.getElementById('globalLoading');
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
            }
            
            // Make functions globally accessible
            window.showLoading = showLoading;
            window.hideLoading = hideLoading;
            
            function toggleUserMenu() {
                const userMenu = document.getElementById('userMenu');
                userMenu.classList.toggle('active');
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const userMenu = document.getElementById('userMenu');
                const isClickInside = userMenu.contains(event.target);
                
                if (!isClickInside && userMenu.classList.contains('active')) {
                    userMenu.classList.remove('active');
                }
            });

            // Socket.io live update — dispatches a custom event instead of full page reload
            (function initSocketAutoRefresh() {
                if (typeof io === 'undefined') return;

                const sseUrl = "{{ env('SSE_URL', 'https://userside-node-server.onrender.com/api/stream') }}";
                const apiUrl = sseUrl.replace('/api/stream', '');
                
                let lastDispatch = 0;
                
                const socket = io(apiUrl, {
                    transports: ['websocket', 'polling'],
                    reconnectionDelayMax: 5000,
                });

                const handleUpdate = () => {
                    const now = Date.now();
                    if (document.visibilityState !== 'visible') return;
                    if (now - lastDispatch < 3000) return; // 3s debounce
                    lastDispatch = now;
                    
                    // Dispatch a lightweight custom event. Pages can listen and update their own data.
                    // If a page calls e.preventDefault(), it means the page handled the update itself.
                    const event = new CustomEvent('adminLiveUpdate', { cancelable: true });
                    const defaultPrevented = !window.dispatchEvent(event);
                    
                    if (!defaultPrevented) {
                        performGenericAutoRefresh();
                    }
                };

                const performGenericAutoRefresh = async () => {
                    // Prevent refresh if a modal is open (so we don't interrupt the admin)
                    if (document.querySelector('.custom-modal-overlay.active, .modal.show, [class*="modal"][style*="display: block"]')) {
                        return;
                    }
                    
                    // Prevent refresh if user is typing in an input field
                    const activeEl = document.activeElement;
                    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT')) {
                        return;
                    }
                    
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('live_update_refresh', Date.now()); // bust cache
                        
                        const response = await fetch(url.toString(), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const html = await response.text();
                        
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        const newMain = doc.querySelector('.main-content');
                        const oldMain = document.querySelector('.main-content');
                        
                        if (newMain && oldMain) {
                            oldMain.innerHTML = newMain.innerHTML;
                            // Re-trigger global scripts if needed, though most inline scripts in views
                            // won't re-run this way. That's why complex pages should preventDefault and handle themselves.
                        }
                    } catch (err) {
                        console.error('Failed generic auto-refresh:', err);
                    }
                };

                socket.on('update', handleUpdate);

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        if (!socket.connected) socket.connect();
                    }
                });
            })();
        </script>
    </body>
</html>
