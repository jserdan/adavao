@extends('layouts.app')

@section('title', 'Reports')

@section('styles')
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .reports-title-section h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .reports-title-section p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.25rem;
            height: 1.25rem;
            fill: none;
            stroke: #9ca3af;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            pointer-events: none;
        }

        /* Date Range Filter Styles */
        .filter-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        .filter-input {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .filter-btn {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .filter-btn:hover {
            background-color: #2563eb;
        }
        
        .reset-link {
            font-size: 0.875rem;
            color: #6b7280;
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .reset-link:hover {
            background-color: #f3f4f6;
            color: #374151;
        }

        /* Custom Station Selector Styles */
        .station-selector-ui {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            background: #f9fafb;
        }
        
        .station-search {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.875rem;
        }

        /* Status Tabs Styles */
        .status-tabs-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 0 0.5rem;
        }

        .status-tabs {
            display: flex;
            gap: 0.75rem;
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .status-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: white;
            border: 1px solid transparent;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .status-tab:hover {
            background: #f1f5f9;
            color: #475569;
        }

        .status-tab.active {
            background: white;
            color: #1e293b;
            border-color: #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .status-tab.pending-tab.active {
            background: linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
            border-color: #fecaca;
            color: #dc2626;
        }

        .status-tab.investigating-tab.active {
            background: linear-gradient(135deg, #fefce8 0%, #fff7ed 100%);
            border-color: #fde68a;
            color: #d97706;
        }

        .status-tab.resolved-tab.active {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-color: #bbf7d0;
            color: #16a34a;
        }

        .tab-icon {
            font-size: 1rem;
        }

        .tab-label {
            font-weight: 600;
        }

        .tab-count {
            background: #e2e8f0;
            color: #64748b;
            padding: 0.125rem 0.5rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 1.5rem;
            text-align: center;
        }

        .status-tab.active .tab-count {
            background: rgba(0,0,0,0.1);
            color: inherit;
        }

        .status-tab.pending-tab .tab-count.pending-count {
            background: #fee2e2;
            color: #dc2626;
        }

        .tab-pulse {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }


        
        .station-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 4px; /* Space for scrollbar */
        }
        
        .station-card {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .station-card:hover {
            border-color: #9ca3af;
            background: #f3f4f6;
        }
        
        .station-card.selected {
            border-color: #3b82f6;
            background: #eff6ff;
            box-shadow: 0 0 0 1px #3b82f6;
        }
        
        .station-card.return-admin {
            border-color: #fca5a5;
            background: #fef2f2;
            margin-bottom: 8px;
        }
        
        .station-card.return-admin:hover {
            border-color: #f87171;
            background: #fee2e2;
        }
        
        .station-card.return-admin.selected {
            border-color: #ef4444;
            background: #fee2e2;
            box-shadow: 0 0 0 1px #ef4444;
        }
        
        .station-card-content {
            display: flex;
            flex-direction: column;
        }
        
        .station-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1f2937;
        }
        
        .station-address {
            font-size: 0.75rem;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
        
        .check-icon {
            color: #3b82f6;
            opacity: 0;
            transition: opacity 0.15s;
        }
        
        .station-card.selected .check-icon {
            opacity: 1;
        }
        
        .station-card.return-admin .check-icon {
            color: #ef4444;
        }



        .reports-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            overflow-y: visible;
        }

        .reports-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }

        .reports-table thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .reports-table th {
            padding: 0.5rem 0.35rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .reports-table th:hover {
            background: #f3f4f6;
        }

        .reports-table th.sortable::after {
            content: '\2195';
            margin-left: 0.5rem;
            opacity: 0.3;
        }

        .reports-table th.sorted-asc::after {
            content: '\2191';
            margin-left: 0.5rem;
            opacity: 1;
        }

        .reports-table th.sorted-desc::after {
            content: '\2193';
            margin-left: 0.5rem;
            opacity: 1;
        }

        .reports-table td {
            padding: 0.5rem 0.35rem;
            font-size: 0.8rem;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .reports-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .reports-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .reports-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Column widths - Optimized for 100% zoom */
        .reports-table th:nth-child(1),
        .reports-table td:nth-child(1) {
            width: 65px;
            max-width: 65px;
        }

        /* Report ID */

        .reports-table th:nth-child(2),
        .reports-table td:nth-child(2) {
            width: 70px;
            max-width: 70px;
        }

        /* User */

        .reports-table th:nth-child(3),
        .reports-table td:nth-child(3) {
            width: 80px;
            max-width: 80px;
        }

        /* Type */

        .reports-table th:nth-child(4),
        .reports-table td:nth-child(4) {
            width: 85px;
            max-width: 85px;
        }

        /* Urgency */

        .reports-table th:nth-child(5),
        .reports-table td:nth-child(5) {
            width: 80px;
            max-width: 80px;
        }

        /* SLA Timer */

        .reports-table th:nth-child(6),
        .reports-table td:nth-child(6) {
            width: 90px;
            max-width: 90px;
        }

        /* Rule Status */

        .reports-table th:nth-child(7),
        .reports-table td:nth-child(7) {
            width: 75px;
            max-width: 75px;
        }

        /* User Status */

        .reports-table th:nth-child(8),
        .reports-table td:nth-child(8) {
            width: 100px;
            max-width: 100px;
        }

        /* Date Reported */

        .reports-table th:nth-child(9),
        .reports-table td:nth-child(9) {
            width: 100px;
            max-width: 100px;
        }

        /* Updated At */

        .reports-table th:nth-child(10),
        .reports-table td:nth-child(10) {
            width: 110px;
            max-width: 110px;
        }

        /* Report Status */

        .reports-table th:nth-child(11),
        .reports-table td:nth-child(11) {
            width: 115px;
            max-width: 115px;
        }

        /* Validity */

        .reports-table th:nth-child(12),
        .reports-table td:nth-child(12) {
            width: 110px;
            max-width: 110px;
        }

        /* Action */

        .verified-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .verified-badge.verified {
            color: #065f46;
        }

        .verified-badge.unverified {
            color: #991b1b;
        }

        .verified-badge.pending {
            color: #3730a3;
        }

        .status-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.pending {
            color: #3730a3;
        }

        .status-badge.investigating {
            color: #1e40af;
        }

        .status-badge.resolved {
            color: #065f46;
        }

        .validity-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .validity-badge.valid {
            color: #065f46;
        }

        .validity-badge.invalid {
            color: #991b1b;
        }

        .validity-badge.checking_for_report_validity {
            color: #3730a3;
        }

        .validity-select {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            font-size: 0.813rem;
            font-weight: 500;
            background-color: white;
            cursor: pointer;
            width: 100%;
            max-width: 150px;
            transition: all 0.2s ease;
            color: #374151;
        }

        .validity-select:hover {
            border-color: #3b82f6;
            background-color: #f9fafb;
        }

        .validity-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .status-select {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            font-size: 0.813rem;
            font-weight: 500;
            background-color: white;
            cursor: pointer;
            width: 100%;
            max-width: 140px;
            transition: all 0.2s ease;
            color: #374151;
        }

        .status-select:hover {
            border-color: #10b981;
            background-color: #f9fafb;
        }

        .status-select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Urgency Badge Styles */
        .urgency-badge {
            display: inline-block;
            padding: 2px 0;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .urgency-critical {
            color: #991b1b;
        }

        .urgency-high {
            color: #c2410c;
        }

        .urgency-medium {
            color: #92400e;
        }

        .urgency-low {
            color: #6b7280;
        }

        .overdue-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.68rem;
            font-weight: 700;
            color: #991b1b;
            background: #fee2e2;
            border: 1px solid #fca5a5;
            white-space: nowrap;
        }

        /* SLA Timer Badge Styles */
        .sla-timer {
            display: inline-block;
            padding: 2px 0;
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            text-align: center;
        }

        .sla-timer.countdown {
            color: #1e40af;
        }

        .sla-timer.exceeded {
            color: #991b1b;
        }

        /* Rule Status Badge Styles */
        .rule-status {
            display: inline-block;
            padding: 2px 0;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .rule-status.within-sla {
            color: #065f46;
        }

        .rule-status.exceeded {
            color: #991b1b;
        }

        .rule-status.pending {
            color: #6b7280;
        }

        .timeline-section {
            margin-top: 1rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }

        .timeline-header {
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeline-item {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.6rem 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-event {
            font-weight: 700;
            font-size: 0.85rem;
            color: #111827;
        }

        .timeline-meta {
            font-size: 0.75rem;
            color: #6b7280;
            white-space: nowrap;
        }

        .timeline-notes {
            margin-top: 0.25rem;
            font-size: 0.8rem;
            color: #374151;
            white-space: pre-wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: #6b7280;
            margin-right: 0.25rem;
            width: 36px;
            height: 36px;
        }

        .action-btn svg {
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .action-btn:hover {
            background: #f9fafb;
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .report-id {
            font-family: 'Courier New', monospace;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .no-results {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }

        .pagination {
            display: flex;
            justify-content: center;
            padding: 1.5rem;
            gap: 0.5rem;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination a {
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }

        .pagination a:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .pagination .active {
            background: #3b82f6;
            color: white;
            border: 1px solid #3b82f6;
        }

        .pagination .disabled {
            color: #d1d5db;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            width: 1000px;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        .modal-body {
            padding: 1rem 1.25rem;
        }

        .report-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            margin-bottom: 0.75rem;
        }

        .detail-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.2rem;
        }

        .detail-value {
            font-size: 0.8rem;
            color: #1f2937;
            line-height: 1.4;
        }

        .media-container {
            margin-top: 1rem;
        }

        .media-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 0.75rem;
        }

        .media-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            position: relative;
            cursor: pointer;
        }

        .media-item.sensitive img,
        .media-item.sensitive video {
            filter: blur(14px);
        }

        .media-item.sensitive.revealed img,
        .media-item.sensitive.revealed video {
            filter: none;
        }

        .sensitive-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: rgba(17, 24, 39, 0.45);
            color: #fff;
            padding: 0.75rem;
            text-align: center;
        }

        .media-item.sensitive.revealed .sensitive-overlay {
            display: none;
        }

        .sensitive-overlay .reveal-btn {
            border: none;
            background: rgba(255, 255, 255, 0.92);
            color: #111827;
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }

        .sensitive-overlay .reveal-btn:hover {
            background: #ffffff;
        }

        .media-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            transition: transform 0.2s ease;
        }

        .media-item:hover img {
            transform: scale(1.05);
        }

        .enlarge-icon {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .media-item:hover .enlarge-icon {
            opacity: 1;
        }

        .media-item.video {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            height: 120px;
        }

        .media-placeholder {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #6b7280;
        }

        /* Image Lightbox */
        .lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }

        .lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .lightbox-content {
            max-width: 90%;
            max-height: 90vh;
        }

        .lightbox-content img {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
        }

        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.6);
            border: 2px solid rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10002;
            padding: 0;
            line-height: 1;
        }

        .lightbox-close:hover {
            background: rgba(0, 0, 0, 0.8);
            border-color: white;
            transform: scale(1.1);
        }

        /* Map Container Styles */
        .report-map-container {
            margin-top: 1rem;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .report-map-header {
            background: #f9fafb;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .report-map-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        #reportDetailMap {
            width: 100%;
            height: 250px;
            background: #f3f4f6;
        }

        /* Custom Leaflet popup styles */
        .leaflet-popup-content-wrapper {
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .leaflet-popup-content {
            margin: 12px 16px;
            line-height: 1.5;
        }

        /* Action Buttons Section */
        .actions-section {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            padding-left: 0;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: #2563eb;
        }

        .btn-warning {
            background-color: #f59e0b;
            color: white;
        }

        .btn-warning:hover:not(:disabled) {
            background-color: #d97706;
        }

        .btn-success {
            background-color: #10b981;
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            background-color: #059669;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            background-color: #dc2626;
        }

        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }

        .btn-secondary:hover:not(:disabled) {
            background-color: #4b5563;
        }

        /* Station Assignment Modal */
        .station-select-container {
            margin: 1rem 0;
        }

        .station-select-container label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .station-select-container select,
        .station-select-container textarea {
            width: 100%;
            padding: 0.625rem;
            border: 1.5px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .station-select-container select:focus,
        .station-select-container textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Enhanced Modal Styles */
        .report-info-section {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .report-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .report-info-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .report-id-badge {
            background: #3b82f6;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .map-container {
            margin: 1.5rem 0;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
        }

        .map-header {
            background: #1f2937;
            color: white;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #reportDetailMap {
            height: 400px;
            width: 100%;
        }

        .attachments-section {
            margin-top: 1.5rem;
        }

        .attachments-header {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .media-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .media-item:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .media-item img {
            width: 100%;
            height: 220px;
            object-fit: contain;
            background: #f3f4f6;
            display: block;
        }

        .media-item video {
            width: 100%;
            height: 220px;
            object-fit: contain;
            background: #000;
            display: block;
        }

        .media-type-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .no-media-message {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-size: 0.875rem;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
        }

        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .info-item-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .reports-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box {
                width: 100%;
            }

            .reports-table-container {
                overflow-x: auto;
            }

            .reports-table {
                min-width: 850px;
            }

            .report-details-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
            }

            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .modal-content {
                width: 100%;
                max-width: 100%;
                max-height: 95vh;
            }
        }

        /* PDF Styles */
        .pdf-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .alertWelcome {
            color: "#1D3557";
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }

        .davao {
            color: black;
            margin-left: 5px;
            font-size: 30px;
            font-weight: bold;
        }
    </style>
@endsection

@section('content')
    @if(!empty($csvReports) && auth()->user() && auth()->user()->email === 'alertdavao.ph')
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem;">
            <span style="font-size: 2rem;">📊</span>
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600;">DCPO Historical Data Import</h3>
        </div>
        <p style="margin: 0 0 1rem 0; opacity: 0.95; font-size: 0.875rem;">
            Displaying {{ count($csvReports) }} DCPO historical crime records from CSV file.
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div style="background: rgba(255,255,255,0.15); padding: 0.75rem; border-radius: 8px; backdrop-filter: blur(10px);">
                <div style="font-size: 0.75rem; opacity: 0.9; margin-bottom: 0.25rem;">Assigned to Stations</div>
                <div style="font-size: 1.5rem; font-weight: 700;">{{ collect($csvReports)->whereNotNull('assigned_station_id')->count() }}</div>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 0.75rem; border-radius: 8px; backdrop-filter: blur(10px);">
                <div style="font-size: 0.75rem; opacity: 0.9; margin-bottom: 0.25rem;">Unassigned</div>
                <div style="font-size: 1.5rem; font-weight: 700;">{{ collect($csvReports)->whereNull('assigned_station_id')->count() }}</div>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 0.75rem; border-radius: 8px; backdrop-filter: blur(10px);">
                <div style="font-size: 0.75rem; opacity: 0.9; margin-bottom: 0.25rem;">Color Legend</div>
                <div style="font-size: 0.75rem; display: flex; gap: 0.5rem; align-items: center;">
                    <span style="display: inline-block; width: 12px; height: 12px; background: #e0f2fe; border-radius: 3px;"></span> Assigned
                    <span style="display: inline-block; width: 12px; height: 12px; background: #fef3c7; border-radius: 3px; margin-left: 0.5rem;"></span> Unassigned
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="reports-header">
        <div class="reports-title-section">
            <h1>Reports</h1>
            <p>Manage and view all incident reports</p>
        </div>

        <!-- ITEM 15: DATE RANGE FILTER -->
        <form action="{{ route('reports') }}" method="GET" style="display: flex; gap: 0.75rem; align-items: center; background: white; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); flex-wrap: wrap;">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151;">
            </div>
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151;">
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">Sort</label>
                <select name="sort" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151; height: 26px;">
                    <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest</option>
                    <option value="urgent" {{ request('sort') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    <option value="severe" {{ request('sort') === 'severe' ? 'selected' : '' }}>Severe (Focus)</option>
                    <option value="needs_info" {{ request('sort') === 'needs_info' ? 'selected' : '' }}>Needs Info</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">Threat Level</label>
                <select name="urgency" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151; height: 26px;">
                    <option value="" {{ !request('urgency') ? 'selected' : '' }}>All Levels</option>
                    <option value="critical" {{ request('urgency') === 'critical' ? 'selected' : '' }}>🔴 Critical</option>
                    <option value="high" {{ request('urgency') === 'high' ? 'selected' : '' }}>🟠 High</option>
                    <option value="medium" {{ request('urgency') === 'medium' ? 'selected' : '' }}>🟡 Medium</option>
                    <option value="low" {{ request('urgency') === 'low' ? 'selected' : '' }}>⚪ Low</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">Validity</label>
                <select name="validity" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 4px; font-size: 0.8rem; color: #374151; height: 26px;">
                    <option value="" {{ !request('validity') ? 'selected' : '' }}>All</option>
                    <option value="valid" {{ request('validity') === 'valid' ? 'selected' : '' }}>Valid</option>
                    <option value="invalid" {{ request('validity') === 'invalid' ? 'selected' : '' }}>Invalid (Fake)</option>
                    <option value="checking_for_report_validity" {{ request('validity') === 'checking_for_report_validity' ? 'selected' : '' }}>Checking</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; justify-content: flex-end;">
                <label style="font-size: 0.65rem; color: #6b7280; font-weight: 700; text-transform: uppercase;">Flags</label>
                <div style="display:flex; gap: 0.5rem; align-items:center; height: 26px;">
                    <label style="display:flex; gap: 0.35rem; align-items:center; font-size: 0.78rem; color: #374151;">
                        <input type="checkbox" name="overdue" value="1" {{ request('overdue') ? 'checked' : '' }}>
                        24h+
                    </label>
                    <label style="display:flex; gap: 0.35rem; align-items:center; font-size: 0.78rem; color: #374151;">
                        <input type="checkbox" name="focus" value="1" {{ request('focus') ? 'checked' : '' }}>
                        Focus
                    </label>
                </div>
            </div>

            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 0 1rem; border-radius: 6px; font-weight: 600; font-size: 0.8rem; cursor: pointer; margin-left: 0.5rem; height: 38px; transition: background 0.2s;">Filter</button>
            @if(request('date_from') || request('date_to') || request('status') || request('sort') || request('overdue') || request('focus') || request('urgency') || request('validity'))
                <a href="{{ route('reports') }}" style="color: #6b7280; text-decoration: none; font-size: 0.8rem; margin-left: 0.5rem; padding: 0.5rem; border-radius: 4px; background: #f3f4f6;">Reset</a>
            @endif
        </form>

        <button onclick="exportFilteredReportsPDF()" style="background: #059669; color: white; border: none; padding: 0 1rem; border-radius: 6px; font-weight: 600; font-size: 0.8rem; cursor: pointer; height: 38px; transition: background 0.2s; display: flex; align-items: center; gap: 0.35rem; white-space: nowrap;" title="Export currently filtered reports as PDF">
            📄 Export PDF
        </button>

        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            <input type="text" class="search-input" placeholder="Search reports..." id="searchInput"
                onkeyup="searchReports()">
        </div>
    </div>

    <!-- Status Tabs for Report Filtering -->
    @php
        $currentStatus = request('status', 'all');
    @endphp
    <div class="status-tabs-container">
        <div class="status-tabs">
            <a href="{{ route('reports', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}" 
               class="status-tab {{ $currentStatus === 'all' ? 'active' : '' }}">
                <span class="tab-icon">📋</span>
                <span class="tab-label">All Reports</span>
                <span class="tab-count" id="allCount">{{ $allCount ?? 0 }}</span>
            </a>
            <a href="{{ route('reports', array_merge(request()->except('status', 'page'), ['status' => 'pending'])) }}" 
               class="status-tab {{ $currentStatus === 'pending' ? 'active' : '' }} pending-tab">
                <span class="tab-icon">🔴</span>
                <span class="tab-label">Pending</span>
                <span class="tab-count pending-count" id="pendingCount">{{ $pendingCount ?? 0 }}</span>
                @if(($pendingCount ?? 0) > 0)
                <span class="tab-pulse"></span>
                @endif
            </a>
            <a href="{{ route('reports', array_merge(request()->except('status', 'page'), ['status' => 'investigating'])) }}" 
               class="status-tab {{ $currentStatus === 'investigating' ? 'active' : '' }} investigating-tab">
                <span class="tab-icon">🟡</span>
                <span class="tab-label">Investigating</span>
                <span class="tab-count" id="investigatingCount">{{ $investigatingCount ?? 0 }}</span>
            </a>
            <a href="{{ route('reports', array_merge(request()->except('status', 'page'), ['status' => 'resolved'])) }}" 
               class="status-tab {{ $currentStatus === 'resolved' ? 'active' : '' }} resolved-tab">
                <span class="tab-icon">🟢</span>
                <span class="tab-label">Resolved</span>
                <span class="tab-count" id="resolvedCount">{{ $resolvedCount ?? 0 }}</span>
            </a>
        </div>
    </div>

    <div class="reports-table-container">
        <table class="reports-table" id="reportsTable">
            <thead>
                <tr>
                    <th class="sortable" data-column="0" style="width: 65px;" onclick="sortTable(0)">ID</th>
                    <th class="sortable" data-column="1" style="width: 70px;" onclick="sortTable(1)">User</th>
                    <th class="sortable" data-column="2" style="width: 80px;" onclick="sortTable(2)">Type</th>
                    <th class="sortable" data-column="3" style="width: 85px;" onclick="sortTable(3)">Urgency</th>
                    <th class="sortable" data-column="4" style="width: 80px;" onclick="sortTable(4)">Response Time</th>
                    <th class="sortable" data-column="5" style="width: 90px;" onclick="sortTable(5)">Rule Status</th>
                    <th class="sortable" data-column="6" style="width: 75px;" onclick="sortTable(6)">User Status</th>
                     <th class="sortable" data-column="7" style="width: 100px;" onclick="sortTable(7)">Date</th>
                     <th class="sortable" data-column="8" style="width: 100px;" onclick="sortTable(8)">Updated</th>
                     <th class="sortable" data-column="9" style="width: 110px;" onclick="sortTable(9)">Report Status</th>
                     <th style="width: 115px;">Validity</th>
                     <th style="width: 110px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                            <tr data-report-id="{{ $report->report_id }}">
                                <td class="report-id">{{ str_pad($report->report_id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>
                                    @if($report->is_anonymous)
                                        Anonymous
                                    @elseif($report->user)
                                        {{ substr($report->user->firstname, 0, 1) }}. {{ substr($report->user->lastname, 0, 1) }}.
                                    @else
                                        Unknown
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $reportType = $report->report_type ?? 'N/A';
                                        if (is_string($reportType)) {
                                            $decoded = json_decode($reportType, true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                $reportType = implode(', ', $decoded);
                                            }
                                        } elseif (is_array($reportType)) {
                                            $reportType = implode(', ', $reportType);
                                        }
                                    @endphp
                                    {{ \Illuminate\Support\Str::limit($reportType, 20) }}
                                </td>
                                <td>
                                    @php
                                        $urgencyScore = $report->urgency_score ?? 0;
                                        if ($urgencyScore >= 90) {
                                            $urgencyClass = 'critical';
                                            $urgencyLabel = 'CRITICAL';
                                            $urgencyIcon = '🔴';
                                        } elseif ($urgencyScore >= 70) {
                                            $urgencyClass = 'high';
                                            $urgencyLabel = 'HIGH';
                                            $urgencyIcon = '🟠';
                                        } elseif ($urgencyScore >= 50) {
                                            $urgencyClass = 'medium';
                                            $urgencyLabel = 'MEDIUM';
                                            $urgencyIcon = '🟡';
                                        } else {
                                            $urgencyClass = 'low';
                                            $urgencyLabel = 'LOW';
                                            $urgencyIcon = '⚪';
                                        }
                                    @endphp
                                    <span class="urgency-badge urgency-{{ $urgencyClass }}" title="Urgency Score: {{ $urgencyScore }}">
                                        {{ $urgencyIcon }} {{ $urgencyLabel }}
                                    </span>

                                    @php
                                        $isOverdue24h = in_array($report->status, ['pending', 'investigating'], true)
                                            && $report->created_at
                                            && $report->created_at->lte(\Carbon\Carbon::now()->subHours(24));
                                    @endphp
                                    @if($isOverdue24h)
                                        <span class="overdue-badge" title="Unaddressed for 24+ hours">⏰ 24H+</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $createdAt = $report->created_at;
                                        $arrivedAt = optional($report->dispatch)->arrived_at;
                                        $threeMinutes = 180; // 3 minutes in seconds

                                        if ($arrivedAt) {
                                            // Officer arrived — freeze timer at arrival time
                                            $elapsedSeconds = $createdAt->diffInSeconds($arrivedAt);
                                        } else {
                                            $elapsedSeconds = $createdAt->diffInSeconds(\Carbon\Carbon::now());
                                        }

                                        $formatTime = function($totalSec) {
                                            $m = floor($totalSec / 60);
                                            $s = $totalSec % 60;
                                            return sprintf('%dm %ds', $m, $s);
                                        };

                                        if ($elapsedSeconds <= $threeMinutes) {
                                            $timerClass = 'countdown'; // Safe styling
                                            $timerDisplay = $formatTime($elapsedSeconds);
                                        } else {
                                            $timerClass = 'exceeded';
                                            $timerDisplay = $formatTime($elapsedSeconds);
                                        }
                                    @endphp
                                    <span class="sla-timer {{ $timerClass }}" 
                                          data-created-at="{{ $createdAt->timestamp }}"
                                          data-arrived-at="{{ $arrivedAt ? \Carbon\Carbon::parse($arrivedAt)->timestamp : '' }}"
                                          data-report-id="{{ $report->report_id }}">
                                        {{ $timerDisplay }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $validatedAt = $report->validated_at;
                                        $isValid = $report->is_valid;
                                        
                                        if ($isValid === 'checking_for_report_validity' || !$validatedAt) {
                                            // Still pending validation - check if already past 3 min
                                            $elapsedSinceCreation = $createdAt->diffInSeconds(\Carbon\Carbon::now());
                                            if ($elapsedSinceCreation > 180) {
                                                $ruleStatus = 'Exceeded';
                                                $ruleClass = 'exceeded';
                                            } else {
                                                $ruleStatus = 'Pending';
                                                $ruleClass = 'pending';
                                            }
                                        } else {
                                            // Validated - check if within 3 minutes
                                            $validationTime = $createdAt->diffInSeconds($validatedAt);
                                            if ($validationTime <= 180) {
                                                $ruleStatus = 'Within 3 Min';
                                                $ruleClass = 'within-sla';
                                            } else {
                                                $ruleStatus = 'Exceeded';
                                                $ruleClass = 'exceeded';
                                            }
                                        }
                                    @endphp
                                    <span class="rule-status {{ $ruleClass }}"
                                          data-rule-created-at="{{ $createdAt->timestamp }}"
                                          data-rule-validated-at="{{ $validatedAt ? \Carbon\Carbon::parse($validatedAt)->timestamp : '' }}"
                                          data-rule-is-valid="{{ $isValid }}">
                                        {{ $ruleStatus }}
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $user = $report->user;
                    $verification = $user ? $user->verification : null;
                    $verifiedStatus = $verification ? $verification->is_verified : null;
                    $verificationStatus = 'unverified';
                    // Use loose comparison to handle bool/int/string
                    if ($verifiedStatus == 1 || $verifiedStatus === true) {
                        $verificationStatus = 'verified';
                    } elseif ($verifiedStatus === 0 || $verifiedStatus === false || $verifiedStatus === '0') {
                        $verificationStatus = 'pending';
                    }
                    ?>
                                    <span class="verified-badge {{ $verificationStatus }}">
                                        @if($verificationStatus === 'verified')
                                            Verified
                                        @elseif($verificationStatus === 'pending')
                                            Pending
                                        @else
                                            Unverified
                                        @endif
                                    </span>
                                </td>
                                <td>{{ $report->created_at->timezone('Asia/Manila')->format('m/d/Y H:i') }}</td>
                                <td>{{ $report->updated_at->timezone('Asia/Manila')->format('m/d/Y H:i') }}</td>
                                <td>
                                    <?php    $reportId = $report->report_id;
                                $status = $report->status; ?>
                                    <select class="status-select" onchange="updateStatus(<?php    echo $reportId; ?>, this.value, this)"
                                        data-original-status="<?php    echo $status; ?>">
                                        <option value="pending" <?php    echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="investigating" <?php    echo $status === 'investigating' ? 'selected' : ''; ?>>
                                            Investigating</option>
                                        <option value="resolved" <?php    echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    </select>
                                </td>
                                <td>
                                    <?php    $reportId = $report->report_id;
                                $isValid = $report->is_valid ?? 'checking_for_report_validity'; ?>
                                    <select class="validity-select" onchange="updateValidity(<?php    echo $reportId; ?>, this.value, this)"
                                        data-original-validity="<?php    echo $isValid; ?>">
                                        <option value="checking_for_report_validity" <?php    echo $isValid === 'checking_for_report_validity' ? 'selected' : ''; ?>></option>
                                        <option value="valid" <?php    echo $isValid === 'valid' ? 'selected' : ''; ?>>Valid</option>
                                        <option value="invalid" <?php    echo $isValid === 'invalid' ? 'selected' : ''; ?>>Invalid</option>
                                    </select>
                                </td>
                                <td>
                                    <?php    $reportId = $report->report_id; ?>
                                    <button class="action-btn" title="View Details"
                                        onclick="showReportDetails(<?php    echo $reportId; ?>)">
                                        <svg class="action-icon" viewBox="0 0 24 24" width="18" height="18">
                                            <path d="m9 18 6-6-6-6" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                @empty
                    @if(empty($csvReports))
                    <tr>
                        <td colspan="12" class="no-results">
                            <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.3;" viewBox="0 0 24 24"
                                fill="currentColor">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                            </svg>
                            <p>No reports found</p>
                        </td>
                    </tr>
                    @endif
                @endforelse
                
                @if(!empty($csvReports) && auth()->user() && auth()->user()->email === 'alertdavao.ph')
                    @foreach($csvReports as $csvReport)
                    <tr style="background-color: {{ $csvReport->assigned_station_id ? '#e0f2fe' : '#fef3c7' }};">
                        <td class="report-id">{{ $csvReport->report_id }}</td>
                        <td>{{ $csvReport->user->username }}</td>
                        <td>{{ $csvReport->report_type }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($csvReport->title, 30) }}</td>
                        <td>
                            <span class="verified-badge {{ $csvReport->user_status }}">
                                {{ ucfirst($csvReport->user_status) }}
                            </span>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($csvReport->date_reported)->format('m/d/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($csvReport->created_at)->format('m/d/Y') }}</td>
                        <td>
                            <span class="status-badge {{ $csvReport->status }}">{{ ucfirst($csvReport->status) }}</span>
                        </td>
                        <td>
                            <span class="validity-badge {{ $csvReport->is_valid }}">{{ ucfirst(str_replace('_', ' ', $csvReport->is_valid)) }}</span>
                        </td>
                        <td>
                            @if($csvReport->assigned_station_id)
                                <span style="font-size: 0.75rem; color: #1e40af; font-weight: 600;">
                                    Station {{ $csvReport->assigned_station_id }}
                                </span>
                            @else
                                <span style="font-size: 0.75rem; color: #92400e; font-weight: 600;">
                                    Unassigned
                                </span>
                            @endif
                        </td>
                        <td>
                            <span style="font-size: 0.75rem; color: #6b7280;">{{ $csvReport->barangay }}</span>
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>

    <!-- Report Details Modal -->
    <div class="modal-overlay" id="reportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Report Details</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="action-btn" title="Download as PDF" onclick="downloadModalAsPDF()" style="padding: 8px 16px; background: #3b82f6; color: white; border-radius: 6px; font-size: 14px; font-weight: 500;">
                        <svg style="display: inline-block; vertical-align: middle; margin-right: 6px;" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z" />
                        </svg>
                        Download PDF
                    </button>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div class="lightbox-overlay" id="lightbox">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="Enlarged view" onerror="console.error('LightBox Image Load Error'); this.src='https://placehold.co/600x400?text=Image+Not+Found';">
        </div>
    </div>

    <!-- Assign Station Modal (Admin) -->
    <div class="modal-overlay" id="assignStationModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Assign Report to Station</h2>
                <button class="modal-close" onclick="closeAssignStationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="station-select-container">
                    <label>Select Destination</label>
                    <input type="hidden" id="selectedAssignStationId">
                    
                    <div class="station-selector-ui">
                        <!-- Return to Admin Option -->
                        <div class="station-card return-admin" onclick="selectAssignStation('unassign')" id="assign-card-unassign">
                            <div class="station-card-content">
                                <span class="station-name" style="color: #b91c1c;">⚠️ Return to Admin</span>
                                <span class="station-address">Unassign this report</span>
                            </div>
                            <svg class="check-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                        </div>

                        <div style="border-top: 1px solid #e5e7eb; margin: 8px 0;"></div>
                        
                        <!-- Search -->
                        <input type="text" class="station-search" placeholder="Search stations..." onkeyup="filterAssignStations(this.value)">
                        
                        <!-- Station List -->
                        <div id="assignStationList" class="station-list">
                            <!-- Stations will be populated here -->
                        </div>
                    </div>
                </div>
                <div class="actions-section" style="margin-top: 0; border-top: none; background: white;">
                    <button class="btn btn-primary" onclick="submitAssignStation()">
                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        Assign Station
                    </button>
                    <button class="btn btn-secondary" onclick="closeAssignStationModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Reassignment Modal (Police) -->
    <div class="modal-overlay" id="requestReassignmentModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Request Report Reassignment</h2>
                <button class="modal-close" onclick="closeReassignmentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="station-select-container">
                    <label>Select Destination</label>
                    <input type="hidden" id="selectedReassignStationId">
                    
                    <div class="station-selector-ui">
                        <!-- Return to Admin Option -->
                        <div class="station-card return-admin" onclick="selectStation('unassign')" id="station-card-unassign">
                            <div class="station-card-content">
                                <span class="station-name" style="color: #b91c1c;">⚠️ Return to Admin</span>
                                <span class="station-address">Unassign this report and return to pool</span>
                            </div>
                            <svg class="check-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                        </div>

                        <div style="border-top: 1px solid #e5e7eb; margin: 8px 0;"></div>
                        
                        <!-- Search -->
                        <input type="text" class="station-search" placeholder="Search stations..." onkeyup="filterStations(this.value)">
                        
                        <!-- Station List -->
                        <div id="reassignStationList" class="station-list">
                            <!-- Stations will be populated here -->
                        </div>
                    </div>
                </div>
                <div class="station-select-container">
                    <label for="reassignReason">Reason for Reassignment</label>
                    <textarea id="reassignReason" rows="3" placeholder="Optional: Provide a reason for this reassignment request..."></textarea>
                </div>
                <div class="actions-section" style="margin-top: 0; border-top: none; background: white;">
                    <button class="btn btn-warning" onclick="submitReassignmentRequest()">
                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/>
                        </svg>
                        Submit Request
                    </button>
                    <button class="btn btn-secondary" onclick="closeReassignmentModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pagination -->
@if($reports->hasPages())
<div class="pagination">
    {{-- Previous Page Link --}}
    @if ($reports->onFirstPage())
        <span class="disabled">&laquo;</span>
    @else
        <a href="{{ $reports->previousPageUrl() }}">&laquo;</a>
    @endif

    {{-- Pagination Elements --}}
    @php
        $currentPage = $reports->currentPage();
        $lastPage = $reports->lastPage();
        $maxPagesToShow = 10;
        $halfMax = floor($maxPagesToShow / 2);
        
        // Calculate start and end pages
        if ($lastPage <= $maxPagesToShow) {
            $startPage = 1;
            $endPage = $lastPage;
        } else {
            if ($currentPage <= $halfMax) {
                $startPage = 1;
                $endPage = $maxPagesToShow;
            } elseif ($currentPage >= $lastPage - $halfMax) {
                $startPage = $lastPage - $maxPagesToShow + 1;
                $endPage = $lastPage;
            } else {
                $startPage = $currentPage - $halfMax;
                $endPage = $currentPage + $halfMax;
            }
        }
    @endphp

    {{-- First Page --}}
    @if ($startPage > 1)
        <a href="{{ $reports->url(1) }}">1</a>
        @if ($startPage > 2)
            <span class="disabled">...</span>
        @endif
    @endif

    {{-- Page Numbers --}}
    @for ($i = $startPage; $i <= $endPage; $i++)
        @if ($i == $currentPage)
            <span class="active">{{ $i }}</span>
        @else
            <a href="{{ $reports->url($i) }}">{{ $i }}</a>
        @endif
    @endfor

    {{-- Last Page --}}
    @if ($endPage < $lastPage)
        @if ($endPage < $lastPage - 1)
            <span class="disabled">...</span>
        @endif
        <a href="{{ $reports->url($lastPage) }}">{{ $lastPage }}</a>
    @endif

    {{-- Next Page Link --}}
    @if ($reports->hasMorePages())
        <a href="{{ $reports->nextPageUrl() }}">&raquo;</a>
    @else
        <span class="disabled">&raquo;</span>
    @endif
</div>
@endif

<script>
// Define onclick functions immediately in the body so they're available when HTML loads
window.showReportDetails = function(reportId) {
    console.log('Loading report details for ID:', reportId);
};
window.updateStatus = function(reportId, status) {};
window.updateValidity = function(reportId, isValid) {};
window.closeModal = function() {};
window.downloadModalAsPDF = function() {};

// Update Response Time timers in real-time
function updateSLATimers() {
    const timers = document.querySelectorAll('.sla-timer');
    const now = Math.floor(Date.now() / 1000);
    
    timers.forEach(timer => {
        const createdAt = parseInt(timer.getAttribute('data-created-at'));
        if (!createdAt) return;

        const arrivedAt = timer.getAttribute('data-arrived-at');
        // If officer has arrived, freeze the timer at arrival time
        const endTime = arrivedAt ? parseInt(arrivedAt) : now;
        const elapsedSeconds = endTime - createdAt;
        const threeMinutes = 180;
        
        function formatTime(totalSec) {
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            if (h > 0) return `${h}h ${m}m ${s}sec`;
            if (m > 0) return `${m}m ${s}sec`;
            return `${s}sec`;
        }

        if (elapsedSeconds <= threeMinutes) {
            const remainingSeconds = threeMinutes - elapsedSeconds;
            timer.textContent = formatTime(remainingSeconds);
            timer.className = 'sla-timer countdown';
        } else {
            const exceededSeconds = elapsedSeconds - threeMinutes;
            timer.textContent = '+' + formatTime(exceededSeconds);
            timer.className = 'sla-timer exceeded';
        }
    });
}

updateSLATimers();
setInterval(updateSLATimers, 1000);

// Auto-update Rule Status column in real-time
function updateRuleStatuses() {
    const ruleElements = document.querySelectorAll('.rule-status');
    const now = Math.floor(Date.now() / 1000);
    
    ruleElements.forEach(el => {
        const createdAt = parseInt(el.getAttribute('data-rule-created-at'));
        const validatedAt = el.getAttribute('data-rule-validated-at');
        const isValid = el.getAttribute('data-rule-is-valid');
        
        if (!createdAt) return;
        
        // If already validated, status is frozen
        if (validatedAt) return;
        
        // If still pending/checking, auto-update to exceeded when past 3 min
        const isPendingRule =
            isValid === 'checking_for_report_validity' ||
            !isValid ||
            el.classList.contains('pending') ||
            el.textContent.trim().toLowerCase() === 'pending';

        if (isPendingRule) {
            const elapsed = now - createdAt;
            if (elapsed > 180) {
                el.textContent = 'Exceeded';
                el.className = 'rule-status exceeded';
            } else {
                el.textContent = 'Pending';
                el.className = 'rule-status pending';
            }
        }
    });
}

updateRuleStatuses();
setInterval(updateRuleStatuses, 1000);

// Report data for PDF export
</script>
@php
    $reportsExportData = $reports->map(function($report) {
        $reportType = $report->report_type ?? 'N/A';
        if (is_string($reportType)) {
            $decoded = json_decode($reportType, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $reportType = implode(', ', $decoded);
            }
        } elseif (is_array($reportType)) {
            $reportType = implode(', ', $reportType);
        }

        $urgencyScore = $report->urgency_score ?? 0;
        if ($urgencyScore >= 90) { $urgencyLabel = 'CRITICAL'; }
        elseif ($urgencyScore >= 70) { $urgencyLabel = 'HIGH'; }
        elseif ($urgencyScore >= 50) { $urgencyLabel = 'MEDIUM'; }
        else { $urgencyLabel = 'LOW'; }

        $userName = 'Unknown';
        if ($report->is_anonymous) { $userName = 'Anonymous'; }
        elseif ($report->user) { $userName = $report->user->firstname . ' ' . $report->user->lastname; }

        $barangay = optional($report->location)->barangay ?? 'N/A';

        return [
            'id' => str_pad($report->report_id, 5, '0', STR_PAD_LEFT),
            'user' => $userName,
            'type' => $reportType,
            'urgency' => $urgencyLabel,
            'urgency_score' => $urgencyScore,
            'status' => ucfirst($report->status),
            'validity' => $report->is_valid === 'valid' ? 'Valid' : ($report->is_valid === 'invalid' ? 'Invalid' : 'Checking'),
            'date' => optional($report->created_at)->format('M d, Y h:i A') ?? 'N/A',
            'barangay' => $barangay,
            'description' => \Illuminate\Support\Str::limit($report->description ?? '', 120),
            'title' => $report->title ?? '',
        ];
    })->values();
@endphp
<script>
var reportsDataForExport = @json($reportsExportData);

function exportFilteredReportsPDF() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('landscape', 'mm', 'a4');

    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const margin = 14;
    let y = margin;

    // --- Header ---
    pdf.setFillColor(29, 53, 87); // --primary
    pdf.rect(0, 0, pageWidth, 28, 'F');
    pdf.setTextColor(255, 255, 255);
    pdf.setFontSize(16);
    pdf.setFont('helvetica', 'bold');
    pdf.text('AlertDavao — Filtered Reports Summary', margin, 12);
    pdf.setFontSize(9);
    pdf.setFont('helvetica', 'normal');

    // Show active filters
    const filters = [];
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') && urlParams.get('status') !== 'all') filters.push('Status: ' + urlParams.get('status'));
    if (urlParams.get('urgency')) filters.push('Threat: ' + urlParams.get('urgency'));
    if (urlParams.get('validity')) filters.push('Validity: ' + urlParams.get('validity'));
    if (urlParams.get('date_from')) filters.push('From: ' + urlParams.get('date_from'));
    if (urlParams.get('date_to')) filters.push('To: ' + urlParams.get('date_to'));
    const filterText = filters.length > 0 ? 'Filters: ' + filters.join(' | ') : 'Showing: All Reports';
    pdf.text(filterText + '   |   Generated: ' + new Date().toLocaleString(), margin, 22);

    y = 34;

    // --- Table Header ---
    const colWidths = [18, 35, 45, 24, 24, 22, 35, 65];
    const colHeaders = ['ID', 'User', 'Crime Type', 'Urgency', 'Status', 'Validity', 'Date', 'Description'];
    const tableWidth = colWidths.reduce((a, b) => a + b, 0);

    function drawTableHeader() {
        pdf.setFillColor(241, 245, 249);
        pdf.rect(margin, y, tableWidth, 8, 'F');
        pdf.setDrawColor(209, 213, 219);
        pdf.line(margin, y + 8, margin + tableWidth, y + 8);
        pdf.setTextColor(55, 65, 81);
        pdf.setFontSize(7.5);
        pdf.setFont('helvetica', 'bold');
        let x = margin;
        colHeaders.forEach((h, i) => {
            pdf.text(h, x + 1.5, y + 5.5);
            x += colWidths[i];
        });
        y += 10;
        pdf.setFont('helvetica', 'normal');
    }

    drawTableHeader();

    // --- Table Rows ---
    pdf.setFontSize(7);
    const data = reportsDataForExport;

    if (data.length === 0) {
        pdf.setTextColor(107, 114, 128);
        pdf.text('No reports found with the current filters.', margin, y + 5);
    } else {
        data.forEach((report, idx) => {
            if (y + 8 > pageHeight - 20) {
                // Footer on current page
                drawFooter(pdf, pageWidth, pageHeight, margin);
                pdf.addPage();
                y = margin;
                drawTableHeader();
            }

            // Alternate row background
            if (idx % 2 === 0) {
                pdf.setFillColor(249, 250, 251);
                pdf.rect(margin, y, tableWidth, 7, 'F');
            }

            // Urgency color coding
            let urgencyColor = [107, 114, 128]; // gray
            if (report.urgency === 'CRITICAL') urgencyColor = [220, 38, 38];
            else if (report.urgency === 'HIGH') urgencyColor = [234, 88, 12];
            else if (report.urgency === 'MEDIUM') urgencyColor = [202, 138, 4];

            let x = margin;
            pdf.setTextColor(55, 65, 81);

            // ID
            pdf.text(String(report.id), x + 1.5, y + 5);
            x += colWidths[0];

            // User
            pdf.text(String(report.user || '').substring(0, 18), x + 1.5, y + 5);
            x += colWidths[1];

            // Type
            pdf.text(String(report.type || '').substring(0, 28), x + 1.5, y + 5);
            x += colWidths[2];

            // Urgency (colored)
            pdf.setTextColor(...urgencyColor);
            pdf.setFont('helvetica', 'bold');
            pdf.text(report.urgency, x + 1.5, y + 5);
            pdf.setFont('helvetica', 'normal');
            pdf.setTextColor(55, 65, 81);
            x += colWidths[3];

            // Status
            pdf.text(String(report.status || ''), x + 1.5, y + 5);
            x += colWidths[4];

            // Validity
            const validColor = report.validity === 'Valid' ? [22, 163, 74] : (report.validity === 'Invalid' ? [220, 38, 38] : [107, 114, 128]);
            pdf.setTextColor(...validColor);
            pdf.text(report.validity, x + 1.5, y + 5);
            pdf.setTextColor(55, 65, 81);
            x += colWidths[5];

            // Date
            pdf.text(String(report.date || '').substring(0, 18), x + 1.5, y + 5);
            x += colWidths[6];

            // Description (truncated)
            const desc = String(report.description || report.title || 'No description').substring(0, 50);
            pdf.text(desc, x + 1.5, y + 5);

            y += 7;
        });
    }

    // Final footer
    drawFooter(pdf, pageWidth, pageHeight, margin);

    // --- Summary Box ---
    if (y + 30 < pageHeight - 20) {
        y += 5;
        pdf.setFillColor(238, 242, 255);
        pdf.roundedRect(margin, y, tableWidth, 20, 2, 2, 'F');
        pdf.setFontSize(8);
        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(67, 56, 202);
        pdf.text('Summary', margin + 4, y + 6);
        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor(55, 65, 81);

        const totalCount = data.length;
        const criticalCount = data.filter(r => r.urgency === 'CRITICAL').length;
        const highCount = data.filter(r => r.urgency === 'HIGH').length;
        const invalidCount = data.filter(r => r.validity === 'Invalid').length;
        const resolvedCount = data.filter(r => r.status === 'Resolved').length;

        pdf.text(`Total Reports: ${totalCount}   |   Critical: ${criticalCount}   |   High: ${highCount}   |   Resolved: ${resolvedCount}   |   Invalid/Fake: ${invalidCount}`, margin + 4, y + 14);
    }

    // Save
    const timestamp = new Date().toISOString().slice(0, 10);
    pdf.save(`AlertDavao_Reports_${timestamp}.pdf`);
}

function drawFooter(pdf, pageWidth, pageHeight, margin) {
    pdf.setDrawColor(209, 213, 219);
    pdf.line(margin, pageHeight - 12, pageWidth - margin, pageHeight - 12);
    pdf.setFontSize(7);
    pdf.setTextColor(156, 163, 175);
    pdf.text('AlertDavao Crime Reporting System — Confidential', margin, pageHeight - 7);
    pdf.text('Page ' + pdf.internal.getCurrentPageInfo().pageNumber, pageWidth - margin - 20, pageHeight - 7);
}

</script>

@endsection

@section('scripts')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function revealSensitiveMedia(mediaId) {
            const item = document.getElementById(`media-item-${mediaId}`);
            if (!item) return;

            item.classList.add('revealed');
            item.dataset.revealed = '1';

            const img = item.querySelector('img');
            if (img) {
                const imageIndexRaw = item.dataset.imageIndex;
                const imageUrlsRaw = item.dataset.imageUrls;
                if (imageIndexRaw && imageUrlsRaw) {
                    const imageIndex = Number(imageIndexRaw);
                    const imageUrlsJson = imageUrlsRaw;
                    item.onclick = () => openLightbox(imageIndex, imageUrlsJson);
                }
            }

            const vid = item.querySelector('video');
            if (vid) {
                vid.controls = true;
            }
        }
        // ========== GLOBAL VARIABLES ==========
        let currentImages = [];
        let currentImageIndex = 0;
        let reportDetailMap = null;
        let autoRefreshInterval = null;
        let lastReportCount = {{ $reports->total() ?? 0 }};

        // Initialize jsPDF
        const { jsPDF } = window.jspdf;

        // ========== DISPATCH (ROBUST WIRING) ==========
        function ensureDispatchModalStyles() {
            if (document.getElementById('dispatchModalStyles')) return;
            const style = document.createElement('style');
            style.id = 'dispatchModalStyles';
            style.textContent = `
                .dispatch-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 99999;
                }
                .dispatch-modal-content {
                    background: #fff;
                    border-radius: 16px;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    max-width: 450px;
                    width: min(450px, 92vw);
                    overflow: hidden;
                    animation: dispatchModalSlideIn 0.25s ease;
                }
                @keyframes dispatchModalSlideIn {
                    from { opacity: 0; transform: translateY(-12px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `;
            document.head.appendChild(style);
        }

        window.ensureDispatchModalExists = function ensureDispatchModalExists() {
            ensureDispatchModalStyles();
            let modal = document.getElementById('dispatchModal');
            if (modal) return modal;

            modal = document.createElement('div');
            modal.id = 'dispatchModal';
            modal.className = 'dispatch-modal';
            modal.innerHTML = `
                <div class="dispatch-modal-content">
                    <div class="modal-header">
                        <h2>🚓 Dispatch Patrol Officer</h2>
                        <button class="modal-close" type="button" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body" style="text-align:center; padding:24px;">
                        <div id="dispatch-loading" style="display:none;">
                            <div style="width:60px; height:60px; border:4px solid #e5e7eb; border-top-color:#3b82f6; border-radius:50%; animation: spin 1s linear infinite; margin:0 auto 16px;"></div>
                            <p style="color:#666; font-size:14px;">Broadcasting dispatch to all patrol officers...</p>
                        </div>
                        <div id="dispatch-confirm" style="display:block;">
                            <div style="width:80px; height:80px; background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                                <span style="font-size:36px;">🚓</span>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; color:#1f2937;">Ready to Dispatch</h3>
                            <p style="margin:0 0 16px; color:#666; font-size:14px; line-height:1.5;">
                                Select an available patrol officer to respond to this report.
                            </p>
                            <input type="hidden" id="dispatch_report_id" />
                            <div style="margin-bottom:16px; text-align:left;">
                                <label for="patrol_officer_select" style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:8px;">Available Officers</label>
                                <select id="patrol_officer_select" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; background-color:#f9fafb; box-sizing:border-box;">
                                    <option value="">Loading officers...</option>
                                </select>
                            </div>
                            <div style="display:flex; gap:12px; justify-content:center;">
                                <button type="button" data-dispatch-cancel style="padding:12px 24px; background:#f3f4f6; border:none; border-radius:8px; cursor:pointer; font-size:14px; font-weight:500;">Cancel</button>
                                <button type="button" data-dispatch-confirm style="padding:12px 24px; background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px; box-shadow:0 4px 12px rgba(59,130,246,0.3);">🚓 Dispatch Now</button>
                            </div>
                        </div>
                        <div id="dispatch-success" style="display:none;">
                            <div style="width:80px; height:80px; background:#dcfce7; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                                <span style="font-size:36px;">✅</span>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; color:#16a34a;">Dispatch Broadcast Sent!</h3>
                            <p style="margin:0 0 24px; color:#666; font-size:14px; line-height:1.5;">
                                All patrol officers have been notified. The assigned officer will accept and respond to this report.
                            </p>
                            <button type="button" data-dispatch-done style="padding:12px 24px; background:#16a34a; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px;">Done</button>
                        </div>
                        <div id="dispatch-error" style="display:none;">
                            <div style="width:80px; height:80px; background:#fee2e2; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                                <span style="font-size:36px;">❌</span>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; color:#dc2626;">Dispatch Failed</h3>
                            <p id="dispatch-error-message" style="margin:0 0 24px; color:#666; font-size:14px; line-height:1.5;">Failed to dispatch.</p>
                            <button type="button" data-dispatch-close style="padding:12px 24px; background:#f3f4f6; border:none; border-radius:8px; cursor:pointer; font-weight:500; font-size:14px;">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) window.closeDispatchModal();
            });
            // Close on X
            modal.querySelector('.modal-close')?.addEventListener('click', () => window.closeDispatchModal());
            modal.querySelector('[data-dispatch-cancel]')?.addEventListener('click', () => window.closeDispatchModal());
            modal.querySelector('[data-dispatch-close]')?.addEventListener('click', () => window.closeDispatchModal());
            modal.querySelector('[data-dispatch-done]')?.addEventListener('click', () => { window.closeDispatchModal(); location.reload(); });
            modal.querySelector('[data-dispatch-confirm]')?.addEventListener('click', () => window.dispatchToNearestPatrol());

            return modal;
        };

        window.openDispatchModal = function openDispatchModal(reportId) {
            const modal = window.ensureDispatchModalExists();
            const reportIdInput = modal.querySelector('#dispatch_report_id');
            if (reportIdInput) reportIdInput.value = String(reportId);

            // Reset states
            modal.querySelector('#dispatch-loading')?.style && (modal.querySelector('#dispatch-loading').style.display = 'none');
            modal.querySelector('#dispatch-confirm')?.style && (modal.querySelector('#dispatch-confirm').style.display = 'block');
            modal.querySelector('#dispatch-success')?.style && (modal.querySelector('#dispatch-success').style.display = 'none');
            modal.querySelector('#dispatch-error')?.style && (modal.querySelector('#dispatch-error').style.display = 'none');

            modal.style.display = 'flex';

            // Fetch patrol officers
            const selectElement = modal.querySelector('#patrol_officer_select');
            if (selectElement) {
                selectElement.innerHTML = '<option value="">Loading officers...</option>';
                selectElement.disabled = true;

                const apiUrl = "{{ url('/api/on-duty-officers') }}";
                console.log('🚓 Fetching officers from:', apiUrl);
                fetch(apiUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                })
                .then(res => {
                    console.log('🚓 Officers API response:', res.status, res.headers.get('content-type'));
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const ct = res.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) {
                        throw new Error('Non-JSON response (possible auth redirect)');
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('🚓 Officers data:', data);
                    const officers = data.officers || [];
                    selectElement.innerHTML = '<option value="">-- Select an Officer --</option>';
                    if (officers.length === 0) {
                        selectElement.innerHTML = '<option value="">No officers currently available</option>';
                    } else {
                        officers.forEach(officer => {
                            const option = document.createElement('option');
                            option.value = officer.id;
                            const locationText = officer.station_name ? ` (${officer.station_name})` : '';
                            const dutyStatus = officer.is_on_duty ? ' 🟢 Online' : ' ⚫ Offline';
                            option.textContent = officer.name + dutyStatus + locationText;
                            selectElement.appendChild(option);
                        });
                        selectElement.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('🚓 Error fetching officers:', err);
                    selectElement.innerHTML = `<option value="">Error: ${err.message}</option>`;
                });
            }
        };

        window.closeDispatchModal = function closeDispatchModal() {
            const modal = document.getElementById('dispatchModal');
            if (modal) modal.style.display = 'none';
        };

        window.dispatchToNearestPatrol = async function dispatchToNearestPatrol() {
            const modal = window.ensureDispatchModalExists();
            const reportId = modal.querySelector('#dispatch_report_id')?.value;
            const officerId = modal.querySelector('#patrol_officer_select')?.value;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            if (!officerId) {
                alert('Please select an available patrol officer.');
                return;
            }

            modal.querySelector('#dispatch-confirm').style.display = 'none';
            modal.querySelector('#dispatch-loading').style.display = 'block';

            try {
                const res = await fetch('/dispatches', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ report_id: reportId, patrol_officer_id: officerId, notes: 'Dispatched from Admin dashboard' })
                });

                const data = await res.json().catch(() => ({}));
                modal.querySelector('#dispatch-loading').style.display = 'none';

                if (data && data.success) {
                    modal.querySelector('#dispatch-success').style.display = 'block';
                } else {
                    const msg = (data && data.message) ? data.message : `Dispatch failed (HTTP ${res.status}).`;
                    const msgEl = modal.querySelector('#dispatch-error-message');
                    if (msgEl) msgEl.textContent = msg;
                    modal.querySelector('#dispatch-error').style.display = 'block';
                }
            } catch (e) {
                modal.querySelector('#dispatch-loading').style.display = 'none';
                const msgEl = modal.querySelector('#dispatch-error-message');
                if (msgEl) msgEl.textContent = `Failed to dispatch. ${e?.message || ''}`.trim();
                modal.querySelector('#dispatch-error').style.display = 'block';
            }
        };

        function checkForNewReports() {
            // Get current URL with all query parameters
            const currentUrl = window.location.href;
            const url = new URL(currentUrl);
            
            // Add a timestamp to prevent caching
            url.searchParams.set('check_new', Date.now());
            
            fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Parse the HTML response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTableBody = doc.querySelector('#reportsTable tbody');
                const currentTableBody = document.querySelector('#reportsTable tbody');
                
                if (newTableBody && currentTableBody) {
                    const newRowCount = newTableBody.querySelectorAll('tr').length;
                    const currentRowCount = currentTableBody.querySelectorAll('tr').length;
                    
                    // Compare full content — detect new reports AND status/validity changes from patrol officers
                    const newContent = newTableBody.innerHTML.trim();
                    const currentContent = currentTableBody.innerHTML.trim();
                    
                    if (newContent !== currentContent) {
                        // Detect if there are truly new reports (row count increased)
                        const newRows = newTableBody.querySelectorAll('tr[data-report-id]');
                        const currentIds = new Set(Array.from(currentTableBody.querySelectorAll('tr[data-report-id]')).map(r => r.dataset.reportId));
                        let firstNewReportId = null;
                        let newReportCount = 0;
                        newRows.forEach(row => {
                            if (!currentIds.has(row.dataset.reportId)) {
                                newReportCount++;
                                if (!firstNewReportId) firstNewReportId = row.dataset.reportId;
                            }
                        });
                        
                        // Update the table content
                        currentTableBody.innerHTML = newTableBody.innerHTML;
                        
                        // Update pagination if exists
                        const newPagination = doc.querySelector('.pagination');
                        const currentPagination = document.querySelector('.pagination');
                        if (newPagination && currentPagination) {
                            currentPagination.innerHTML = newPagination.innerHTML;
                        }

                        // Update status tab counts
                        const newDoc = doc;
                        ['allCount', 'pendingCount', 'investigatingCount', 'resolvedCount'].forEach(id => {
                            const newEl = newDoc.getElementById(id);
                            const curEl = document.getElementById(id);
                            if (newEl && curEl) curEl.textContent = newEl.textContent;
                        });
                        
                        // Auto-sort by urgency after update
                        sortTableByUrgency();
                        
                        // Only show notification for genuinely new reports (not status updates)
                        if (newReportCount > 0) {
                            showNewReportNotification(newReportCount, firstNewReportId);
                        } else {
                            console.log('📋 Reports table updated (status/validity changed by patrol officer)');
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for new reports:', error);
            });
        }

        function showNewReportNotification(count, reportId = null) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(220, 38, 38, 0.4);
                z-index: 9999;
                font-weight: 600;
                animation: slideIn 0.3s ease-out, pulse 2s infinite;
                cursor: ${reportId ? 'pointer' : 'default'};
                max-width: 350px;
                border: 2px solid rgba(255,255,255,0.2);
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 28px; animation: shake 0.5s ease-in-out infinite;">🚨</div>
                    <div>
                        <div style="font-size: 16px; font-weight: 700;">${count === 1 ? 'NEW REPORT RECEIVED!' : count + ' NEW REPORTS!'}</div>
                        ${reportId ? '<div style="font-size: 12px; opacity: 0.9; margin-top: 4px;">👆 Click to view report details</div>' : ''}
                    </div>
                </div>
            `;
            
            // Make notification clickable to open report
            if (reportId) {
                notification.onclick = function() {
                    notification.remove();
                    // Find and click the view button for this report
                    const row = document.querySelector(`tr[data-report-id="${reportId}"]`);
                    if (row) {
                        const viewBtn = row.querySelector('.action-btn');
                        if (viewBtn) viewBtn.click();
                    } else {
                        // Fallback: fetch and show report directly
                        showReport(reportId);
                    }
                };
            }
            
            document.body.appendChild(notification);
            
            // Remove notification after 10 seconds (longer so user can click)
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }, 10000);
            
            // Play notification sound
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIGWi77eafTRALT6fk77RgGwU7k9bxy3ktBSJ1xe/glEILElyx6OyrVhYMQp3e8bhlHQYogczx2Ik2CBlouezmn00QC06m5O+0YRsGOJHX8ct5LQUidMXu4ZVDBBFYrOfqrFgWDT+a3vK6aB4HI33H8NqJNwgZaLvt559NEAtOpuTvtGEbBjiR1/HLeS0FInXF7+KWRAUSVqnm6axaGQ0+m97yuWgeBx9+yPDaiTYHGGi77+SfTBEMTKbk7bNhHAQ4kdXzyn0tBSJ1xe/jl0QGEVan5eitWhsMPpne87ppHwcdfMbv2ok3CBdpvO7kn0wRDU2m4+60YRsGOZPY88p9LQQhdsfv45dFBhFWp+XprVsbDD6Y3/K6ah8HHn7J79qKNggXabzv5J9MEAJQ');
                audio.volume = 0.5;
                audio.play();
            } catch (e) {
                // Ignore audio errors
            }
        }
        
        // Function to sort table by urgency (Critical -> High -> Medium -> Low)
        function sortTableByUrgency() {
            const table = document.getElementById('reportsTable');
            if (!table) return;
            
            const tbody = table.getElementsByTagName('tbody')[0];
            if (!tbody) return;
            
            const rows = Array.from(tbody.getElementsByTagName('tr'));
            
            // Urgency priority mapping
            const urgencyPriority = {
                'CRITICAL': 1,
                'HIGH': 2,
                'MEDIUM': 3,
                'LOW': 4
            };
            
            rows.sort((a, b) => {
                // Column 3 is urgency (0-indexed)
                const aUrgencyCell = a.getElementsByTagName('td')[3];
                const bUrgencyCell = b.getElementsByTagName('td')[3];
                
                if (!aUrgencyCell || !bUrgencyCell) return 0;
                
                const aText = aUrgencyCell.textContent.trim().toUpperCase();
                const bText = bUrgencyCell.textContent.trim().toUpperCase();
                
                // Extract urgency level
                let aPriority = 5, bPriority = 5;
                for (const [key, val] of Object.entries(urgencyPriority)) {
                    if (aText.includes(key)) aPriority = val;
                    if (bText.includes(key)) bPriority = val;
                }
                
                return aPriority - bPriority;
            });
            
            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
            console.log('Table sorted by urgency: Critical → High → Medium → Low');
        }

        // Start auto-refresh when page loads (15s interval + socket events)
        document.addEventListener('DOMContentLoaded', function() {
            autoRefreshInterval = setInterval(checkForNewReports, 15000);
            sortTableByUrgency();
            // Also refresh on live socket events
            window.addEventListener('adminLiveUpdate', checkForNewReports);
        });

        // Pause auto-refresh when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
            } else {
                if (!autoRefreshInterval) {
                    autoRefreshInterval = setInterval(checkForNewReports, 15000);
                    checkForNewReports();
                }
            }
        });

        // Add CSS animation for notification
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
            @keyframes shake {
                0%, 100% { transform: rotate(0deg); }
                25% { transform: rotate(-10deg); }
                75% { transform: rotate(10deg); }
            }
            @keyframes pulse {
                0%, 100% { box-shadow: 0 8px 32px rgba(220, 38, 38, 0.4); }
                50% { box-shadow: 0 8px 48px rgba(220, 38, 38, 0.6); }
            }
        `;
        document.head.appendChild(style);

        function searchReports() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('reportsTable');
            const tr = table.getElementsByTagName('tr');
            const pagination = document.querySelector('.pagination');

            // Show/hide pagination based on search
            if (filter.length > 0) {
                // Hide pagination during search
                if (pagination) pagination.style.display = 'none';
            } else {
                // Show pagination when no search
                if (pagination) pagination.style.display = 'flex';
            }

            for (let i = 1; i < tr.length; i++) {
                let txtValue = tr[i].textContent || tr[i].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }

        let sortDirections = {};

        function sortTable(columnIndex) {
            const table = document.getElementById('reportsTable');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = Array.from(tbody.getElementsByTagName('tr'));
            const headers = table.getElementsByTagName('th');

            // Toggle sort direction
            if (!sortDirections[columnIndex]) {
                sortDirections[columnIndex] = 'asc';
            } else if (sortDirections[columnIndex] === 'asc') {
                sortDirections[columnIndex] = 'desc';
            } else {
                sortDirections[columnIndex] = 'asc';
            }

            const direction = sortDirections[columnIndex];

            // Remove sort classes from all headers
            for (let i = 0; i < headers.length; i++) {
                headers[i].classList.remove('sorted-asc', 'sorted-desc');
            }

            // Add sort class to current header
            headers[columnIndex].classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

            // Sort rows
            rows.sort((a, b) => {
                let aValue = a.getElementsByTagName('td')[columnIndex]?.textContent.trim() || '';
                let bValue = b.getElementsByTagName('td')[columnIndex]?.textContent.trim() || '';

                // Handle numeric values (Report ID)
                if (columnIndex === 0) {
                    aValue = parseInt(aValue) || 0;
                    bValue = parseInt(bValue) || 0;
                }
                // Handle dates (columns 7 and 8 - Date Reported and Updated At)
                else if (columnIndex === 7 || columnIndex === 8) {
                    aValue = new Date(aValue).getTime() || 0;
                    bValue = new Date(bValue).getTime() || 0;
                }

                if (aValue < bValue) return direction === 'asc' ? -1 : 1;
                if (aValue > bValue) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }

           window.updateStatus = function(reportId, status, selectElement) {
               // Store reference to the select element before the fetch call
               const target = selectElement || (typeof event !== 'undefined' ? event.target : null);
               if (!target) return;

             fetch(`/reports/${reportId}/status`, {
                 method: 'PUT',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                 },
                 body: JSON.stringify({
                     status: status
                 })
             })
                 .then(response => response.json())
                 .then(data => {
                     if (data.success) {
                         // Update successful - no need to update UI since we're using a select dropdown
                         // The select already shows the current status
                         // Update the original status attribute
                         target.setAttribute('data-original-status', status);

                         alert('Status updated successfully');
                     } else {
                         alert('Failed to update status: ' + (data.message || 'Unknown error'));
                         // Revert to original status
                         target.value = target.getAttribute('data-original-status');
                     }
                 })
                 .catch(error => {
                     console.error('Error:', error);
                     alert('An error occurred while updating status: ' + (error.message || 'Unknown error'));
                     // Revert to original status
                     target.value = target.getAttribute('data-original-status');
                 });
         }

         window.updateValidity = function(reportId, isValid, selectElement) {
             // Store reference to the select element before the fetch call
             const target = selectElement || (typeof event !== 'undefined' ? event.target : null);
             if (!target) return;

             const originalValidity = target.getAttribute('data-original-validity');

             // Validation confirmation prompt with 3-minute rule context
             const row = target.closest('tr');
             const slaTimer = row ? row.querySelector('.sla-timer') : null;
             const createdAtTs = slaTimer ? parseInt(slaTimer.getAttribute('data-created-at')) : 0;
             const nowTs = Math.floor(Date.now() / 1000);
             const elapsedSeconds = nowTs - createdAtTs;
             const threeMinRule = elapsedSeconds <= 180;
             const elapsedMin = Math.floor(elapsedSeconds / 60);
             const elapsedSec = elapsedSeconds % 60;
             
             let confirmMsg = '';
             if (isValid === 'valid') {
                 confirmMsg = `Are you sure you want to mark this report as VALID?\n\n`;
                 confirmMsg += `⏱️ 3-Minute Rule: ${threeMinRule ? '✅ WITHIN TIME (' + elapsedMin + 'm ' + elapsedSec + 's)' : '❌ EXCEEDED (' + elapsedMin + 'm ' + elapsedSec + 's)'}`;
                 confirmMsg += `\n\nThis will record the validation timestamp for the 3-minute rule compliance.`;
             } else if (isValid === 'invalid') {
                 confirmMsg = `Are you sure you want to mark this report as INVALID (Fake Report)?\n\n`;
                 confirmMsg += `⏱️ 3-Minute Rule: ${threeMinRule ? '✅ WITHIN TIME (' + elapsedMin + 'm ' + elapsedSec + 's)' : '❌ EXCEEDED (' + elapsedMin + 'm ' + elapsedSec + 's)'}`;
                 confirmMsg += `\n\nA rejection reason may be required.`;
             } else {
                 confirmMsg = 'Reset report validity to "Checking"?';
             }

             if (!confirm(confirmMsg)) {
                 target.value = originalValidity;
                 return;
             }

             // If marking as invalid, prompt for reason
             let rejectionReason = null;
             if (isValid === 'invalid') {
                 rejectionReason = prompt('Please provide a reason for marking this report as invalid:');
                 if (rejectionReason === null) {
                     target.value = originalValidity;
                     return;
                 }
             }

             const bodyData = { is_valid: isValid };
             if (rejectionReason) bodyData.rejection_reason = rejectionReason;

             fetch(`/reports/${reportId}/validity`, {
                 method: 'PUT',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                 },
                 body: JSON.stringify(bodyData)
             })
                 .then(response => response.json())
                 .then(data => {
                     if (data.success) {
                         target.setAttribute('data-original-validity', isValid);
                         const ruleMsg = threeMinRule ? '✅ 3-Minute Rule: ACHIEVED' : '⚠️ 3-Minute Rule: EXCEEDED';
                         alert(`Report validity updated successfully.\n${ruleMsg}`);
                         
                         // Update the Rule Status column in the same row
                         const ruleStatusEl = row ? row.querySelector('.rule-status') : null;
                         if (ruleStatusEl && (isValid === 'valid' || isValid === 'invalid')) {
                             if (threeMinRule) {
                                 ruleStatusEl.textContent = 'Within 3 Min';
                                 ruleStatusEl.className = 'rule-status within-sla';
                             } else {
                                 ruleStatusEl.textContent = 'Exceeded';
                                 ruleStatusEl.className = 'rule-status exceeded';
                             }
                             // Mark as validated so the auto-update stops
                             ruleStatusEl.setAttribute('data-rule-validated-at', nowTs.toString());
                         }
                     } else {
                         alert('Failed to update validity status: ' + (data.message || 'Unknown error'));
                         target.value = originalValidity;
                     }
                 })
                 .catch(error => {
                     console.error('Error:', error);
                     alert('An error occurred while updating validity status: ' + (error.message || 'Unknown error'));
                     target.value = originalValidity;
                 });
         }

        // Make function globally accessible
        window.showReportDetails = function(reportId) {
            console.log('🔍 View Details clicked for report ID:', reportId);
            
            // Get current logged-in user ID from Laravel session
            const userId = '{{ auth()->id() }}';
            
            fetch(`/reports/${reportId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const report = data.data;
                        const modalBody = document.getElementById('modalBody');

                        // Helper function to get media URLs with userId for decryption
                        const getMediaUrl = (media) => {
                            if (!media) return null;
                            if (media.display_url) {
                                // Add userId parameter for authentication
                                const url = new URL(media.display_url, window.location.origin);
                                url.searchParams.append('userId', userId);
                                return url.toString();
                            }
                            if (!media.media_url) return null;
                            
                            // For evidence files from Node backend, add userId for decryption
                            if (media.media_url.startsWith('/evidence/')) {
                                const nodeBackendUrl = '{{ config("app.node_backend_url", "http://localhost:3000") }}';
                                return `${nodeBackendUrl}${media.media_url}?userId=${userId}`;
                            }
                            
                            if (media.media_url.startsWith('http')) return media.media_url;
                            const url = media.media_url.startsWith('/storage/') 
                                ? media.media_url 
                                : `/storage/${media.media_url}`;
                            console.log('Media URL generated:', url, 'from:', media.media_url);
                            return url;
                        };

                        const reportInfo = `
                            <div class="report-info-section">
                                <div class="report-info-header">
                                    <h3 class="report-info-title">🚨 Crime Report Details</h3>
                                    <span class="report-id-badge">ID: ${report.report_id.toString().padStart(5, '0')}</span>
                                </div>
                                
                                <div class="info-row">
                                    <div class="detail-item">
                                        <div class="detail-label">📋 Title</div>
                                        <div class="detail-value">${report.title || 'No title provided'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">🏷️ Report Type</div>
                                        <div class="detail-value">${Array.isArray(report.report_type) ? report.report_type.join(', ') : (report.report_type || 'N/A')}</div>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <div class="detail-item">
                                        <div class="detail-label">📅 Date Reported</div>
                                        <div class="detail-value">${new Date(report.date_reported || report.created_at).toLocaleString('en-US', { 
                                            timeZone: 'Asia/Manila',
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">🔄 Last Updated</div>
                                        <div class="detail-value">${new Date(report.updated_at).toLocaleString('en-US', { 
                                            timeZone: 'Asia/Manila',
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}</div>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <div class="detail-item">
                                        <div class="detail-label">👤 Reported By</div>
                                        <div class="detail-value">
                                            ${report.is_anonymous ? 'Anonymous' : (report.user ? (report.user.firstname + ' ' + report.user.lastname) : 'Unknown User')}
                                            ${getVerificationBadge(report)}
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">🆔 User ID</div>
                                        <div class="detail-value">
                                            ${report.user ? report.user.id : 'N/A'}
                                        </div>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <div class="detail-item">
                                        <div class="detail-label">📊 Status</div>
                                        <div class="detail-value">
                                            <span class="status-badge ${report.status}">${report.status.charAt(0).toUpperCase() + report.status.slice(1)}</span>
                                            <span style="margin-left: 0.5rem; font-size: 12px; font-weight: 600; ${report.is_valid === 'valid' ? 'color: #065f46;' : report.is_valid === 'invalid' ? 'color: #991b1b;' : 'color: #6b7280;'}">${report.is_valid === 'valid' ? '✓ Valid' : report.is_valid === 'invalid' ? '✗ Invalid' : ''}</span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">📍 Location Address</div>
                                        <div class="detail-value">${getLocationDisplay(report)}</div>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <div class="detail-item">
                                        <div class="detail-label">🎯 Coordinates</div>
                                        <div class="detail-value">
                                            ${report.location ? `${parseFloat(report.location.latitude).toFixed(6)}, ${parseFloat(report.location.longitude).toFixed(6)}` : 'Not available'}
                                        </div>
                                    </div>
                                    ${report.assigned_station_id && report.police_station ? `
                                    <div class="detail-item">
                                        <div class="detail-label">🚓 Assigned Station</div>
                                        <div class="detail-value">
                                            <strong>${report.police_station.station_name || 'Station ' + report.assigned_station_id}</strong>
                                            ${report.police_station.address ? `<br><small style="color: #6b7280;">${report.police_station.address}</small>` : ''}
                                        </div>
                                    </div>
                                    ` : `
                                    <div class="detail-item">
                                        <div class="detail-label">🚓 Assigned Station</div>
                                        <div class="detail-value">
                                            <span style="color: #f59e0b; font-weight: 600;">⚠️ Unassigned</span>
                                        </div>
                                    </div>
                                    `}
                                </div>

                                <div class="info-row" style="grid-template-columns: 1fr;">
                                    <div class="detail-item">
                                        <div class="detail-label">📝 Description</div>
                                        <div class="detail-value" style="white-space: pre-wrap;">${report.description || 'No description provided'}</div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div style="margin-top: 4px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                    ${getActionButtons(report)}
                                </div>
                            </div>
                        `;

                        // Build map container
                        const mapContainer = `
                            <div class="map-container">
                                <div class="map-header">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="display: inline-block; vertical-align: middle;">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    Location Map - Crime Scene & Police Stations
                                </div>
                                <div id="reportDetailMap" style="height: 300px; width: 100%; border-radius: 0 0 8px 8px;"></div>
                            </div>
                        `;

                        // Build media/attachments section
                        let mediaContent = '';
                        if (report.media && report.media.length > 0) {
                            const imageUrls = report.media
                                .filter(media => {
                                    const ext = (media.media_type || '').toLowerCase();
                                    return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                                })
                                .map(media => getMediaUrl(media))
                                .filter(url => url !== null);

                            mediaContent = `
                                <div class="attachments-section">
                                    <h3 class="attachments-header">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                            <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                                        </svg>
                                        Evidence Attachments (${report.media.length})
                                    </h3>
                                    <div class="media-grid">
                            `;

                            report.media.forEach((media, index) => {
                                const mediaUrl = getMediaUrl(media);
                                const mediaType = (media.media_type || '').toLowerCase();
                                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(mediaType);
                                const isVideo = ['mp4', 'mov', 'avi', 'webm'].includes(mediaType);
                                const isSensitive = Boolean(media.is_sensitive);
                                const mediaId = media.media_id ?? index;

                                if (isImage) {
                                    const imageIndex = imageUrls.indexOf(mediaUrl);
                                    const imageUrlsJson = JSON.stringify(imageUrls).replace(/"/g, '&quot;');
                                    if (isSensitive) {
                                        mediaContent += `
                                            <div class="media-item sensitive" id="media-item-${mediaId}" data-image-index="${imageIndex}" data-image-urls='${imageUrlsJson}'>
                                                <img src="${mediaUrl}" alt="Evidence ${index + 1}" onerror="this.src='https://placehold.co/200x150?text=Image+Not+Found'" loading="lazy">
                                                <div class="sensitive-overlay">
                                                    <div style="font-weight: 800; font-size: 0.8rem;">Sensitive content</div>
                                                    <div style="font-size: 0.7rem; opacity: 0.9;">Click reveal to view</div>
                                                    <button class="reveal-btn" onclick="event.stopPropagation(); revealSensitiveMedia(${mediaId});">Reveal</button>
                                                </div>
                                                <span class="media-type-badge">📷 Photo</span>
                                            </div>
                                        `;
                                    } else {
                                        mediaContent += `
                                            <div class="media-item" onclick="openLightbox(${imageIndex}, '${imageUrlsJson}')">
                                                <img src="${mediaUrl}" alt="Evidence ${index + 1}" onerror="this.src='https://placehold.co/200x150?text=Image+Not+Found'" loading="lazy">
                                                <span class="media-type-badge">📷 Photo</span>
                                            </div>
                                        `;
                                    }
                                } else if (isVideo) {
                                    if (isSensitive) {
                                        mediaContent += `
                                            <div class="media-item sensitive" id="media-item-${mediaId}">
                                                <video src="${mediaUrl}" style="width: 100%; height: 220px; object-fit: contain; background: #000;" playsinline></video>
                                                <div class="sensitive-overlay">
                                                    <div style="font-weight: 800; font-size: 0.8rem;">Sensitive content</div>
                                                    <div style="font-size: 0.7rem; opacity: 0.9;">Click reveal to view</div>
                                                    <button class="reveal-btn" onclick="event.stopPropagation(); revealSensitiveMedia(${mediaId});">Reveal</button>
                                                </div>
                                                <span class="media-type-badge">🎥 Video</span>
                                            </div>
                                        `;
                                    } else {
                                        mediaContent += `
                                            <div class="media-item">
                                                <video src="${mediaUrl}" style="width: 100%; height: 220px; object-fit: contain; background: #000;" controls></video>
                                                <span class="media-type-badge">🎥 Video</span>
                                            </div>
                                        `;
                                    }
                                } else {
                                    mediaContent += `
                                        <div class="media-item">
                                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 150px; background: #f3f4f6;">
                                                <svg viewBox="0 0 24 24" width="40" height="40" fill="#9ca3af">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                                </svg>
                                                <span style="font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem;">${mediaType.toUpperCase()}</span>
                                            </div>
                                            <span class="media-type-badge">📄 File</span>
                                        </div>
                                    `;
                                }
                            });

                            mediaContent += `
                                    </div>
                                </div>
                            `;
                        } else {
                            mediaContent = `
                                <div class="attachments-section">
                                    <h3 class="attachments-header">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                            <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                                        </svg>
                                        Evidence Attachments
                                    </h3>
                                    <div class="no-media-message">
                                        <svg viewBox="0 0 24 24" width="48" height="48" fill="#d1d5db" style="margin-bottom: 0.5rem;">
                                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-5-7l-3 3.72L9 13l-3 4h12l-4-5z"/>
                                        </svg>
                                        <p>No evidence attachments for this report</p>
                                    </div>
                                </div>
                            `;
                        }

                        // Build report timeline/history
                        const timeline = Array.isArray(report.timelines) ? report.timelines : [];
                        const timelineRows = timeline.map((entry) => {
                            const when = entry.created_at
                                ? new Date(entry.created_at).toLocaleString('en-US', {
                                    timeZone: 'Asia/Manila',
                                    year: 'numeric',
                                    month: 'short',
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })
                                : 'Unknown time';

                            const actorName = entry.actor
                                ? `${entry.actor.firstname || ''} ${entry.actor.lastname || ''}`.trim() || `User #${entry.actor.id}`
                                : (entry.changed_by ? `User #${entry.changed_by}` : 'System');

                            const change = (entry.from_value || entry.to_value)
                                ? `${entry.from_value ?? ''}${entry.from_value && entry.to_value ? ' → ' : ''}${entry.to_value ?? ''}`
                                : '';

                            const eventLabel = entry.event_type === 'status_change'
                                ? 'Status'
                                : entry.event_type === 'validity_change'
                                    ? 'Validity'
                                    : (entry.event_type || 'Update');

                            return `
                                <div class="timeline-item">
                                    <div>
                                        <div class="timeline-event">${eventLabel}${change ? `: ${change}` : ''}</div>
                                        ${entry.notes ? `<div class="timeline-notes">${entry.notes}</div>` : ''}
                                    </div>
                                    <div class="timeline-meta">${when}<br>${actorName}</div>
                                </div>
                            `;
                        }).join('');

                        const timelineContent = `
                            <div class="timeline-section">
                                <h3 class="timeline-header">🕒 Report Timeline</h3>
                                ${timeline.length ? timelineRows : `<div style="color:#6b7280; font-size: 0.85rem;">No status/history recorded yet.</div>`}
                            </div>
                        `;

                        // Build Patrol Response section (if dispatch exists)
                        let patrolResponseContent = '';
                        const dispatch = report.dispatch;
                        if (dispatch) {
                            // Helper to parse timestamps: Laravel fields (created_at, dispatched_at) are stored in Manila local time.
                            // Node fields (accepted_at, en_route_at, arrived_at, completed_at) are stored as Postgres UTC time.
                            const parseServerTime = (ts, isNodeField = false) => {
                                if (!ts) return new Date();
                                const cleanTs = String(ts).replace('T', ' ').split('.')[0].replace('Z', '');
                                const date = new Date(cleanTs.replace(/-/g, '/'));
                                if (isNodeField) {
                                    date.setHours(date.getHours() + 8); // Convert Node UTC to Manila +8
                                }
                                return date;
                            };

                            const fmtDate = (d, isNodeField = false) => {
                                if (!d) return '—';
                                return parseServerTime(d, isNodeField).toLocaleString('en-US', {
                                    year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit'
                                });
                            };

                            const officerName = dispatch.officer_name || (dispatch.patrol_officer ? `${dispatch.patrol_officer.firstname || ''} ${dispatch.patrol_officer.lastname || ''}`.trim() : 'Unknown Officer');
                            
                            // Calculate response time from report creation to officer arrival
                            let responseTimeDisp = '—';
                            if (report.created_at) {
                                const createdAt = parseServerTime(report.created_at, false); // Laravel
                                const endTime = dispatch.arrived_at ? parseServerTime(dispatch.arrived_at, true) : new Date();
                                
                                const elapsedSec = Math.floor((endTime - createdAt) / 1000);
                                const isNegative = elapsedSec < 0;
                                const absSec = Math.abs(elapsedSec);
                                const h = Math.floor(absSec / 3600);
                                const m = Math.floor((absSec % 3600) / 60);
                                const s = absSec % 60;
                                
                                const sign = isNegative ? '-' : '';
                                responseTimeDisp = sign + (h > 0 ? `${h}h ${m}m ${s}s` : (m > 0 ? `${m}m ${s}s` : `${s}s`));
                            }
                            const validityLabel = dispatch.is_valid === true || dispatch.is_valid === 'true' ? '✅ Valid' : dispatch.is_valid === false || dispatch.is_valid === 'false' ? '❌ Invalid' : '⏳ Pending';
                            const validityColor = dispatch.is_valid === true || dispatch.is_valid === 'true' ? '#065f46' : dispatch.is_valid === false || dispatch.is_valid === 'false' ? '#991b1b' : '#6b7280';

                            // Verification evidence media
                            let evidenceMediaHtml = '';
                            if (dispatch.verification_media_url) {
                                const nodeBackendUrl = '{{ config("app.node_backend_url", "http://localhost:3000") }}';
                                const evidenceUrl = dispatch.verification_media_url.startsWith('http') 
                                    ? dispatch.verification_media_url 
                                    : `${nodeBackendUrl}${dispatch.verification_media_url}`;
                                const isVideo = /\.(mp4|mov|avi|webm)$/i.test(evidenceUrl);
                                if (isVideo) {
                                    evidenceMediaHtml = `
                                        <div style="margin-top: 12px;">
                                            <div class="detail-label">📹 Verification Evidence</div>
                                            <video src="${evidenceUrl}" controls style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-top: 6px;"></video>
                                        </div>`;
                                } else {
                                    evidenceMediaHtml = `
                                        <div style="margin-top: 12px;">
                                            <div class="detail-label">📸 Verification Evidence</div>
                                            <img src="${evidenceUrl}" alt="Verification evidence" onclick="openLightbox(0, '[&quot;${evidenceUrl}&quot;]')" style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-top: 6px; cursor: pointer; object-fit: contain;" onerror="this.src='https://placehold.co/400x300?text=Image+Not+Found'">
                                        </div>`;
                                }
                            }

                            patrolResponseContent = `
                                <div class="report-info-section" style="border-left: 4px solid #3b82f6;">
                                    <h3 class="report-info-title" style="color: #1e40af;">🚔 Patrol Response</h3>
                                    
                                    <div class="info-row">
                                        <div class="detail-item">
                                            <div class="detail-label">👮 Responding Officer</div>
                                            <div class="detail-value" style="font-weight: 600;">${officerName}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">⏱️ Response Time</div>
                                            <div class="detail-value">${responseTimeDisp}</div>
                                        </div>
                                    </div>

                                    <div class="info-row">
                                        <div class="detail-item">
                                            <div class="detail-label">📤 Dispatched</div>
                                            <div class="detail-value">${fmtDate(dispatch.dispatched_at, false)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">✅ Accepted</div>
                                            <div class="detail-value">${fmtDate(dispatch.accepted_at, true)}</div>
                                        </div>
                                    </div>

                                    <div class="info-row">
                                        <div class="detail-item">
                                            <div class="detail-label">🚗 En Route</div>
                                            <div class="detail-value">${fmtDate(dispatch.en_route_at, true)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">📍 Arrived</div>
                                            <div class="detail-value">${fmtDate(dispatch.arrived_at, true)}</div>
                                        </div>
                                    </div>

                                    <div class="info-row">
                                        <div class="detail-item">
                                            <div class="detail-label">🏁 Completed</div>
                                            <div class="detail-value">${fmtDate(dispatch.completed_at, true)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">🔍 Validation</div>
                                            <div class="detail-value" style="font-weight: 600; color: ${validityColor};">${validityLabel}</div>
                                        </div>
                                    </div>

                                    ${dispatch.validation_notes ? `
                                    <div class="info-row" style="grid-template-columns: 1fr;">
                                        <div class="detail-item">
                                            <div class="detail-label">📝 Officer Notes</div>
                                            <div class="detail-value" style="white-space: pre-wrap; font-style: italic;">${dispatch.validation_notes}</div>
                                        </div>
                                    </div>
                                    ` : ''}

                                    ${evidenceMediaHtml}
                                </div>
                            `;
                        }

                        // Combine all sections
                        modalBody.innerHTML = reportInfo + patrolResponseContent + timelineContent + mapContainer + mediaContent;

                        // Wire dynamic action buttons (dispatch) after render
                        modalBody.querySelectorAll('.dispatch-patrol-btn[data-dispatch-report]').forEach((btn) => {
                            if (btn.dataset.bound === '1') return;
                            btn.dataset.bound = '1';
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                const rid = btn.getAttribute('data-dispatch-report');
                                window.openDispatchModal(rid);
                            });
                        });
                        
                        // Store current report ID and data globally
                        window.currentReportId = reportId;
                        window.currentReportData = report;
                        
                        // Show the modal
                        document.getElementById('reportModal').classList.add('active');
                        
                        // Initialize map after modal is shown
                        setTimeout(() => {
                            initializeReportMap(report, data.policeStations);
                        }, 100);
                    } else {
                        alert('Failed to load report details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading report details: ' + error.message);
                });
        }

function getLocationDisplay(report) {
     // Check if location object exists
     if (report.location) {
         const barangay = report.location.barangay;
         const address = report.location.reporters_address;
         
         // Build display with address and barangay
         let display = '';
         
         if (address && address.trim()) {
             display = address.trim();
         }
         
         if (barangay && barangay !== 'Unknown' && !barangay.startsWith('Lat:') && !barangay.includes(',')) {
             display = display ? `${display}, ${barangay}` : barangay;
         }
         
         if (display) {
             return display;
         }
     }
     
     // No valid location name found
     return 'Location not specified';
 }

function getVerificationBadge(report) {
     // Return empty string if user is anonymous or doesn't exist
     if (report.is_anonymous || !report.user) {
         return '';
     }
     
     // Check if user is ID verified (using the verification relationship)
     // The PHP controller eager loads 'user.verification'
     const verification = report.user.verification;
     
     if (verification && (verification.is_verified === 1 || verification.is_verified === true || verification.is_verified === '1')) {
         return '<span style="margin-left: 6px; color: #065f46; font-size: 12px; font-weight: 600;">✓ Verified</span>';
     } else if (verification && (verification.is_verified === 0 || verification.is_verified === false || verification.is_verified === '0')) {
         return '<span style="margin-left: 6px; color: #3730a3; font-size: 12px; font-weight: 600;">⏳ Pending</span>';
     }
     
     return '';
 }

 function getActionButtons(report) {
     const userRole = '{{ auth()->user()->hasRole("super_admin") ? "super_admin" : (auth()->user()->hasRole("admin") ? "admin" : (auth()->user()->hasRole("police") ? "police" : "")) }}';
     const isUnassigned = !report.assigned_station_id;
     
     // Shared button styles
     const baseBtn = `
         display: inline-flex; align-items: center; justify-content: center;
         padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600;
         cursor: pointer; border: none; transition: all 0.2s ease;
         letter-spacing: 0.3px; white-space: nowrap;
     `;
     const assignStyle = isUnassigned
         ? `${baseBtn} background: #f0fdf4; color: #15803d; border: 1.5px solid #86efac;`
         : `${baseBtn} background: #fefce8; color: #a16207; border: 1.5px solid #fde047;`;
     const dispatchStyle = `${baseBtn} background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #fff; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);`;
     const reassignStyle = `${baseBtn} background: #fffbeb; color: #b45309; border: 1.5px solid #fcd34d;`;

     const assignIcon = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; flex-shrink: 0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`;
     const dispatchIcon = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>`;
     const reassignIcon = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; flex-shrink: 0;"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg>`;

     let buttons = '';
     
     // For admin and super_admin users
     if (userRole === 'admin' || userRole === 'super_admin') {
         const buttonText = isUnassigned ? 'Assign to Station' : 'Reassign Station';
         buttons = `
             <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                 <button onclick="openAssignStationModal(${report.report_id})" style="${assignStyle}"
                     onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                     onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                     ${assignIcon} ${buttonText}
                 </button>
                 <button class="dispatch-patrol-btn" data-dispatch-report="${report.report_id}" style="${dispatchStyle}"
                     onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 14px rgba(37, 99, 235, 0.4)'"
                     onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 6px rgba(37, 99, 235, 0.25)'">
                     ${dispatchIcon} Dispatch Patrol
                 </button>
             </div>
         `;
     }
     // For police users
     else if (userRole === 'police') {
         buttons = `
             <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                 <button onclick="openReassignmentModal(${report.report_id})" style="${reassignStyle}"
                     onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                     onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                     ${reassignIcon} Request Reassignment
                 </button>
                 <button class="dispatch-patrol-btn" data-dispatch-report="${report.report_id}" style="${dispatchStyle}"
                     onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 14px rgba(37, 99, 235, 0.4)'"
                     onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 6px rgba(37, 99, 235, 0.25)'">
                     ${dispatchIcon} Dispatch Patrol
                 </button>
             </div>
         `;
     }
     // Fallback
     else {
         buttons = `
             <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                 <button class="dispatch-patrol-btn" data-dispatch-report="${report.report_id}" style="${dispatchStyle}"
                     onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 14px rgba(37, 99, 235, 0.4)'"
                     onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 6px rgba(37, 99, 235, 0.25)'">
                     ${dispatchIcon} Dispatch Patrol
                 </button>
             </div>
         `;
     }
     
     return buttons;
 }

 // Station assignment/reassignment functions
 let policeStations = [];

 window.loadPoliceStations = function() {
     if (policeStations.length > 0) return Promise.resolve();
     
     return fetch('/api/police-stations')
         .then(response => response.json())
         .then(data => {
            if (data.success && Array.isArray(data.data)) {
                policeStations = data.data;
            } else if (Array.isArray(data)) {
                policeStations = data;
            } else {
                console.error('Unexpected police stations format:', data);
                policeStations = [];
            }
            
            // Sort stations naturally (PS1, PS2, ... PS10)
            policeStations.sort((a, b) => {
                return a.station_name.localeCompare(b.station_name, undefined, {numeric: true, sensitivity: 'base'});
            });

             console.log('Loaded police stations:', policeStations);
         })
         .catch(error => {
             console.error('Error loading police stations:', error);
             alert('Failed to load police stations');
         });
 }

 window.populateStationSelect = function(selectId) {
     const select = document.getElementById(selectId);
     select.innerHTML = '<option value="">-- Select a station --</option>';
     
     if (selectId === 'reassignStationSelect') {
         const unassignOption = document.createElement('option');
         unassignOption.value = 'unassign';
         unassignOption.textContent = '⚠️ Return to Admin (Unassign)';
         unassignOption.style.color = '#ef4444';
         unassignOption.style.fontWeight = 'bold';
         select.appendChild(unassignOption);
     }
     
     if (Array.isArray(policeStations)) {
         policeStations.forEach(station => {
             const option = document.createElement('option');
             option.value = station.station_id;
             option.textContent = station.station_name;
             select.appendChild(option);
         });
     } else {
         console.warn('policeStations is not an array:', policeStations);
     }
 }

 window.openAssignStationModal = function(reportId) {
     window.currentReportId = reportId;
     document.getElementById('selectedAssignStationId').value = ''; // Reset selection
     document.querySelectorAll('#assignStationModal .station-card').forEach(c => c.classList.remove('selected'));
     
     window.loadPoliceStations().then(() => {
         window.renderAssignStations();
         document.getElementById('assignStationModal').classList.add('active');
     });
 }

 window.renderAssignStations = function() {
     const listContainer = document.getElementById('assignStationList');
     listContainer.innerHTML = '';
     
     if (Array.isArray(policeStations)) {
         policeStations.forEach(station => {
             const card = document.createElement('div');
             card.className = 'station-card';
             card.id = `assign-card-${station.station_id}`;
             card.onclick = () => window.selectAssignStation(station.station_id);
             
             card.innerHTML = `
                <div class="station-card-content">
                    <span class="station-name">${station.station_name}</span>
                    <span class="station-address">${station.address || 'Address not available'}</span>
                </div>
                <svg class="check-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
             `;
             listContainer.appendChild(card);
         });
     }
 }

 window.selectAssignStation = function(stationId) {
     document.getElementById('selectedAssignStationId').value = stationId;
     document.querySelectorAll('#assignStationModal .station-card').forEach(c => c.classList.remove('selected'));
     const selectedCard = document.getElementById(stationId === 'unassign' ? 'assign-card-unassign' : `assign-card-${stationId}`);
     if (selectedCard) selectedCard.classList.add('selected');
 }

 window.filterAssignStations = function(query) {
     const filter = query.toLowerCase();
     const cards = document.querySelectorAll('#assignStationList .station-card');
     cards.forEach(card => {
         const name = card.querySelector('.station-name').textContent.toLowerCase();
         const address = card.querySelector('.station-address').textContent.toLowerCase();
         if (name.includes(filter) || address.includes(filter)) {
             card.style.display = 'flex';
         } else {
             card.style.display = 'none';
         }
     });
 }

 window.closeAssignStationModal = function() {
     document.getElementById('assignStationModal').classList.remove('active');
     document.getElementById('selectedAssignStationId').value = '';
 }

 window.openReassignmentModal = function(reportId) {
     window.currentReportId = reportId;
     document.getElementById('selectedReassignStationId').value = ''; // Reset selection
     document.querySelectorAll('.station-card').forEach(c => c.classList.remove('selected'));
     
     window.loadPoliceStations().then(() => {
         window.renderReassignmentStations();
         document.getElementById('requestReassignmentModal').classList.add('active');
     });
 }

 window.renderReassignmentStations = function() {
     const listContainer = document.getElementById('reassignStationList');
     listContainer.innerHTML = '';
     
     if (Array.isArray(policeStations)) {
         policeStations.forEach(station => {
             const card = document.createElement('div');
             card.className = 'station-card';
             card.id = `station-card-${station.station_id}`;
             card.onclick = () => window.selectStation(station.station_id);
             
             card.innerHTML = `
                <div class="station-card-content">
                    <span class="station-name">${station.station_name}</span>
                    <span class="station-address">${station.address || 'Address not available'}</span>
                </div>
                <svg class="check-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
             `;
             listContainer.appendChild(card);
         });
     }
 }
 
 window.selectStation = function(stationId) {
     // Update hidden input
     document.getElementById('selectedReassignStationId').value = stationId;
     
     // Update visual state
     document.querySelectorAll('.station-card').forEach(c => c.classList.remove('selected'));
     const selectedCard = document.getElementById(stationId === 'unassign' ? 'station-card-unassign' : `station-card-${stationId}`);
     if (selectedCard) selectedCard.classList.add('selected');
 }
 
 window.filterStations = function(query) {
     const filter = query.toLowerCase();
     const cards = document.querySelectorAll('#reassignStationList .station-card');
     
     cards.forEach(card => {
         const name = card.querySelector('.station-name').textContent.toLowerCase();
         const address = card.querySelector('.station-address').textContent.toLowerCase();
         
         if (name.includes(filter) || address.includes(filter)) {
             card.style.display = 'flex';
         } else {
             card.style.display = 'none';
         }
     });
 }

 window.closeReassignmentModal = function() {
     document.getElementById('requestReassignmentModal').classList.remove('active');
     document.getElementById('selectedReassignStationId').value = '';
     document.getElementById('reassignReason').value = '';
 }

 window.submitAssignStation = function() {
     const stationId = document.getElementById('selectedAssignStationId').value;
     
     if (!stationId) {
         alert('Please select a destination');
         return;
     }
     
     const payload = { station_id: stationId === 'unassign' ? null : stationId };
     
     fetch(`/reports/${window.currentReportId}/assign-station`, {
         method: 'POST',
         headers: {
             'Content-Type': 'application/json',
             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
         },
         body: JSON.stringify(payload)
     })
     .then(response => response.json())
     .then(data => {
         if (data.success) {
             alert('Report successfully assigned/unassigned');
             window.closeAssignStationModal();
             window.closeModal();
             location.reload(); 
         } else {
             alert('Failed to assign report: ' + (data.message || 'Unknown error'));
         }
     })
     .catch(error => {
         console.error('Error:', error);
         alert('An error occurred while assigning the report');
     });
 }

 window.submitReassignmentRequest = function() {
     const stationId = document.getElementById('selectedReassignStationId').value;
     const reason = document.getElementById('reassignReason').value;
     
     if (!stationId) {
         alert('Please select a destination (Station or Return to Admin)');
         return;
     }
     
     const payload = {
         reason: reason
     };
     
     // Handle unassign option
     if (stationId === 'unassign') {
         payload.station_id = null;
     } else {
         payload.station_id = stationId;
     }
     
     fetch(`/reports/${window.currentReportId}/request-reassignment`, {
         method: 'POST',
         headers: {
             'Content-Type': 'application/json',
             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
         },
         body: JSON.stringify(payload)
     })
     .then(response => response.json())
     .then(data => {
         if (data.success) {
             alert('Reassignment request submitted successfully');
             window.closeReassignmentModal();
             window.closeModal();
         } else {
             alert('Failed to submit request: ' + (data.message || 'Unknown error'));
         }
     })
     .catch(error => {
         console.error('Error:', error);
         alert('An error occurred while submitting the request');
     });
 }
 
 function initializeReportMap(report, policeStations) {
     // Remove existing map if it exists
     if (reportDetailMap) {
         reportDetailMap.remove();
         reportDetailMap = null;
     }
     
     // Get latitude and longitude from the report's location object
     const lat = report.location ? (report.location.latitude || report.location.lat) : (report.latitude || report.lat);
     const lng = report.location ? (report.location.longitude || report.location.lng || report.location.long) : (report.longitude || report.lng || report.long);
     
     // Default to Davao City center if no coordinates
     const latitude = lat ? parseFloat(lat) : 7.1907;
     const longitude = lng ? parseFloat(lng) : 125.4553;
     const hasValidCoordinates = lat && lng;
     
     console.log('Report coordinates:', { lat, lng, hasValidCoordinates });
     
     // Davao City bounds for geofencing
     const davaoCityBounds = [
         [6.9, 125.2],  // Southwest corner
         [7.5, 125.7]   // Northeast corner
     ];
     
     // Initialize the map with geofencing
     reportDetailMap = L.map('reportDetailMap', {
         maxBounds: davaoCityBounds,
         maxBoundsViscosity: 1.0,
         minZoom: 11,
         maxZoom: 18
     }).setView([latitude, longitude], hasValidCoordinates ? 15 : 13);
     
     // Add OpenStreetMap tile layer
     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
         attribution: '© OpenStreetMap contributors',
         maxZoom: 18,
     }).addTo(reportDetailMap);
     
     // Add a RED person marker for the crime location if coordinates are valid
     if (hasValidCoordinates) {
         // Create custom red person icon for crime location
         const redIcon = L.divIcon({
             className: 'custom-marker-icon',
             html: `<div style="position: relative; width: 40px; height: 40px;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                     <circle cx="12" cy="8" r="5" fill="#EF4444" stroke="white" stroke-width="1.5"/>
                     <path d="M12 14c-5 0-9 3-9 6v2h18v-2c0-3-4-6-9-6z" fill="#EF4444" stroke="white" stroke-width="1.5"/>
                 </svg>
             </div>`,
             iconSize: [40, 40],
             iconAnchor: [20, 40],
             popupAnchor: [0, -40]
         });
         
         const crimeMarker = L.marker([latitude, longitude], { icon: redIcon }).addTo(reportDetailMap);
         
         // Add popup with location info
         const locationName = getLocationDisplay(report);
         const streetAddress = report.location?.reporters_address || '';
         const popupContent = `
             <div style="text-align: center; min-width: 180px;">
                 <strong style="color: #EF4444; font-size: 14px;">📍 Crime Location</strong><br>
                 <strong style="font-size: 13px; margin-top: 8px; display: block;">${report.title || 'Incident Report'}</strong><br>
                 ${streetAddress ? `<span style="font-size: 12px; color: #444; margin-top: 4px; display: block;">${streetAddress}</span>` : ''}
                 <span style="font-size: 12px; color: #666; margin-top: 4px; display: block;">${locationName}</span><br>
                 <span style="font-size: 11px; color: #999; margin-top: 4px; display: block;">
                     ${latitude.toFixed(6)}, ${longitude.toFixed(6)}
                 </span>
             </div>
         `;
         crimeMarker.bindPopup(popupContent).openPopup();
     }
     
     // Add police station markers (BLUE shield icons) if provided
     if (policeStations && policeStations.length > 0) {
         console.log('Adding police station markers:', policeStations.length);
         
         // Create custom blue shield/badge icon for police stations
         const blueIcon = L.divIcon({
             className: 'custom-marker-icon',
             html: `<div style="position: relative; width: 40px; height: 40px;">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                     <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="#3B82F6" stroke="white" stroke-width="1"/>
                     <circle cx="12" cy="10" r="2.5" fill="white"/>
                     <path d="M12 13c-2 0-3.5 1-3.5 2v1.5h7V15c0-1-1.5-2-3.5-2z" fill="white"/>
                 </svg>
             </div>`,
             iconSize: [40, 40],
             iconAnchor: [20, 40],
             popupAnchor: [0, -40]
         });
         
         let stationsAdded = 0;
         policeStations.forEach(station => {
             // Only add stations with valid, non-zero coordinates
             if (station.latitude && station.longitude && 
                 parseFloat(station.latitude) !== 0 && 
                 parseFloat(station.longitude) !== 0) {
                 
                 const stationMarker = L.marker(
                     [parseFloat(station.latitude), parseFloat(station.longitude)], 
                     { icon: blueIcon }
                 ).addTo(reportDetailMap);
                 
                 const stationPopup = `
                     <div style="text-align: center; min-width: 150px;">
                         <strong style="color: #3B82F6; font-size: 14px;">🚔 Police Station</strong><br>
                         <strong style="font-size: 13px; margin-top: 8px; display: block;">${station.station_name}</strong><br>
                         <span style="font-size: 11px; color: #666; margin-top: 4px; display: block;">${station.address || 'N/A'}</span>
                     </div>
                 `;
                 stationMarker.bindPopup(stationPopup);
                 stationsAdded++;
             }
         });
         console.log('Police station markers added:', stationsAdded);
     }
     
     // Invalidate size to ensure proper rendering
     setTimeout(() => {
         if (reportDetailMap) {
             reportDetailMap.invalidateSize();
         }
     }, 200);
 }
 
 window.closeModal = function() {
    // Remove the map when closing modal
    if (reportDetailMap) {
        reportDetailMap.remove();
        reportDetailMap = null;
    }
    document.getElementById('reportModal').classList.remove('active');
}

window.downloadModalAsPDF = function() {
    console.log('📥 Starting PDF generation from modal screenshot...');
    
    // Initialize jsPDF
    const { jsPDF } = window.jspdf;
    
    // Get the modal content element
    const modalContent = document.querySelector('#reportModal .modal-content');
    if (!modalContent) {
        alert('Modal content not found. Please open a report first.');
        return;
    }
    
    // Get current report ID from the global variable
    const reportId = window.currentReportId;
    if (!reportId) {
        alert('No report loaded. Please open a report first.');
        return;
    }
    
    console.log('📸 Capturing modal content for report:', reportId);
    
    // Show loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px 40px; border-radius: 8px; z-index: 10001; font-size: 16px; font-weight: 600;';
    loadingDiv.innerHTML = '📄 Generating PDF...<br><small style="font-size: 14px; font-weight: 400; margin-top: 8px; display: block;">Please wait...</small>';
    document.body.appendChild(loadingDiv);
    
    // Wait a moment for any images/maps to finish rendering
    setTimeout(() => {
        html2canvas(modalContent, {
            useCORS: true,
            allowTaint: true,
            logging: false,
            backgroundColor: '#ffffff',
            scale: 2,
            imageTimeout: 0,
            removeContainer: false,
            scrollY: -window.scrollY,
            scrollX: -window.scrollX,
            windowWidth: modalContent.scrollWidth,
            windowHeight: modalContent.scrollHeight
        }).then(canvas => {
            console.log('✅ Modal captured, canvas size:', canvas.width, 'x', canvas.height);
            
            // Convert canvas to image
            const imgData = canvas.toDataURL('image/png');
            
            // Calculate PDF dimensions
            const imgWidth = 210; // A4 width in mm
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            // Create PDF
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });
            
            let position = 0;
            const pageHeight = 297; // A4 height in mm
            
            // Add image to PDF, split into pages if needed
            while (position < imgHeight) {
                if (position > 0) {
                    pdf.addPage();
                }
                
                pdf.addImage(
                    imgData,
                    'PNG',
                    0,
                    -position,
                    imgWidth,
                    imgHeight
                );
                
                position += pageHeight;
            }
            
            // Download the PDF
            const fileName = `crime_report_${reportId.toString().padStart(5, '0')}.pdf`;
            pdf.save(fileName);
            
            console.log('✅ PDF saved as:', fileName);
            
            // Remove loading indicator
            document.body.removeChild(loadingDiv);
            
            // Show success message
            const successDiv = document.createElement('div');
            successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 16px 24px; border-radius: 8px; z-index: 10001; font-size: 14px; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
            successDiv.innerHTML = '✅ PDF downloaded successfully!';
            document.body.appendChild(successDiv);
            setTimeout(() => {
                document.body.removeChild(successDiv);
            }, 3000);
        }).catch(error => {
            console.error('❌ Error capturing modal:', error);
            document.body.removeChild(loadingDiv);
            alert('Failed to generate PDF: ' + error.message);
        });
    }, 500);
}

// OLD IMPLEMENTATION - Kept for reference
// This function is no longer used. PDF is now generated from modal screenshot.
/*
function downloadReport(reportId) {
    console.log('📥 Download PDF requested for report:', reportId);
    console.log('📍 Map status:', {
        mapExists: !!reportDetailMap,
        mapElement: !!document.getElementById('reportDetailMap')
    });
    
    fetch(`/reports/${reportId}/details`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const report = data.data;
            console.log('📄 Starting PDF generation for report:', report.report_id);
            generatePDF(report);
        } else {
            alert('Failed to load report details: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while loading report details: ' + error.message);
    });
}

function generatePDF(report) {
    console.log('🎨 Creating PDF template...');
    // Create a temporary HTML element for rendering
    const tempContainer = document.createElement('div');
    tempContainer.style.position = 'absolute';
    tempContainer.style.left = '-9999px';
    tempContainer.style.width = '800px';
    tempContainer.style.padding = '20px';
    tempContainer.style.fontFamily = 'Arial, sans-serif';
    tempContainer.style.backgroundColor = 'white';
    
    // Get location display
    const locationDisplay = getLocationDisplay(report);
    
    // Get user display (anonymous or actual name)
    const userDisplay = report.is_anonymous ? 'Anonymous' : (report.user ? report.user.firstname + ' ' + report.user.lastname : 'Unknown User');
    
    // Determine verification status
    let verificationStatus = 'Unverified';
    if (!report.is_anonymous && report.user) {
        if (report.user.email_verified_at) {
            verificationStatus = 'Verified';
        } else if (report.user.verification_status === 'pending') {
            verificationStatus = 'Pending';
        }
    }
    
    // Create HTML content for the PDF
    tempContainer.innerHTML = `
        <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1D3557; padding-bottom: 20px;">
            <div class="alertWelcome">Alert</div>
            <div class="davao">Davao</div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Title</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${report.title || 'No title provided'}</div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Location</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${locationDisplay}</div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Description</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${report.description || 'No description provided'}</div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Report ID</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${report.report_id.toString().padStart(5, '0')}</div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Report Type</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${Array.isArray(report.report_type) ? report.report_type.join(', ') : (report.report_type || 'N/A')}</div>
            </div>
            <div>
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Date Reported</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${new Date(report.created_at).toLocaleString('en-US', { timeZone: 'Asia/Manila' })}</div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Last Updated</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">${new Date(report.updated_at).toLocaleString('en-US', { timeZone: 'Asia/Manila' })}</div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">User</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">
                    ${userDisplay} 
                    <span style="margin-left: 8px; font-size: 11px; font-weight: bold; 
                        ${verificationStatus === 'Verified' ? 'color: #065f46;' : verificationStatus === 'Pending' ? 'color: #3730a3;' : 'color: #991b1b;'}">
                        ${verificationStatus}
                    </span>
                </div>
                
                <div style="font-weight: bold; margin-bottom: 5px; color: #1D3557;">Status</div>
                <div style="margin-bottom: 15px; padding-left: 10px;">
                    <span style="font-size: 12px; font-weight: 600; text-transform: capitalize;
                        ${report.status === 'pending' ? 'color: #3730a3;' : 
                          report.status === 'investigating' ? 'color: #1e40af;' : 
                          report.status === 'resolved' ? 'color: #065f46;' : 
                          'color: #991b1b;'}">
                        ${report.status}
                    </span>
                </div>
            </div>
        </div>

        <div id="map-container" style="margin-top: 20px;"></div>
        <div id="images-container" style="margin-top: 20px;"></div>
    `;

            // Add map container
            const mapContainer = tempContainer.querySelector('#map-container');
            if (reportDetailMap) {
                const mapTitle = document.createElement('div');
                mapTitle.style.fontWeight = 'bold';
                mapTitle.style.marginBottom = '15px';
                mapTitle.style.color = '#1D3557';
                mapTitle.textContent = 'Report Location Map';
                mapContainer.appendChild(mapTitle);

                // Add placeholder for map image
                const mapPlaceholder = document.createElement('div');
                mapPlaceholder.id = 'pdf-map';
                mapPlaceholder.style.marginBottom = '20px';
                mapContainer.appendChild(mapPlaceholder);
            }

            // Add images container
            const imagesContainer = tempContainer.querySelector('#images-container');
            if (report.media && report.media.length > 0) {
                const imagesTitle = document.createElement('div');
                imagesTitle.style.fontWeight = 'bold';
                imagesTitle.style.marginBottom = '15px';
                imagesTitle.style.color = '#1D3557';
                imagesTitle.textContent = 'Attached Images';
                imagesContainer.appendChild(imagesTitle);

                // Add placeholder for images
                const imagesPlaceholder = document.createElement('div');
                imagesPlaceholder.id = 'pdf-images';
                imagesPlaceholder.style.display = 'flex';
                imagesPlaceholder.style.flexWrap = 'wrap';
                imagesPlaceholder.style.gap = '10px';
                imagesContainer.appendChild(imagesPlaceholder);
            }

            document.body.appendChild(tempContainer);

            // Capture map as image first
            const mapPromise = new Promise((resolve) => {
                if (reportDetailMap) {
                    console.log('📍 Starting map capture for PDF...');
                    // Wait for map tiles to load - increased timeout
                    setTimeout(() => {
                        // Get the map container from the modal
                        const mapElement = document.getElementById('reportDetailMap');
                        if (mapElement) {
                            console.log('📍 Map element found, capturing with html2canvas...');
                            html2canvas(mapElement, {
                                useCORS: true,
                                allowTaint: true,
                                logging: true,
                                backgroundColor: '#f3f4f6',
                                scale: 2, // Higher quality
                                imageTimeout: 0, // Don't timeout on images
                                removeContainer: false
                            }).then(canvas => {
                                console.log('📍 Map canvas created successfully');
                                const mapPlaceholder = tempContainer.querySelector('#pdf-map');
                                if (mapPlaceholder) {
                                    const mapImg = document.createElement('img');
                                    mapImg.src = canvas.toDataURL('image/png');
                                    mapImg.style.width = '100%';
                                    mapImg.style.maxWidth = '700px';
                                    mapImg.style.height = 'auto';
                                    mapImg.style.border = '1px solid #ccc';
                                    mapImg.style.borderRadius = '4px';
                                    mapPlaceholder.appendChild(mapImg);
                                    console.log('✅ Map image added to PDF template');
                                } else {
                                    console.warn('⚠️ Map placeholder not found in template');
                                }
                                resolve();
                            }).catch(error => {
                                console.error('❌ Error capturing map:', error);
                                // Add error message to PDF instead
                                const mapPlaceholder = tempContainer.querySelector('#pdf-map');
                                if (mapPlaceholder) {
                                    const errorDiv = document.createElement('div');
                                    errorDiv.style.padding = '20px';
                                    errorDiv.style.backgroundColor = '#fee';
                                    errorDiv.style.border = '1px solid #fcc';
                                    errorDiv.style.borderRadius = '4px';
                                    errorDiv.style.color = '#c00';
                                    errorDiv.textContent = 'Map could not be captured for PDF';
                                    mapPlaceholder.appendChild(errorDiv);
                                }
                                resolve(); // Continue even if map capture fails
                            });
                        } else {
                            console.warn('⚠️ Map element not found in DOM');
                            resolve();
                        }
                    }, 1000); // Increased wait time for map tiles to load
                } else {
                    console.log('ℹ️ No map initialized, skipping map capture');
                    resolve();
                }
            });

            // Load and insert images before capturing
            const imagePromises = [mapPromise];
            if (report.media && report.media.length > 0) {
                const imagesPlaceholder = tempContainer.querySelector('#pdf-images');

                report.media.forEach((mediaItem) => {
                    // Only process image files
                    const mediaType = (mediaItem.media_type || '').toLowerCase();
                    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(mediaType);

                    if (!isImage) {
                        return; // Skip non-image files
                    }

                    const promise = new Promise((resolve, reject) => {
                         const img = new Image();
                         img.crossOrigin = 'anonymous';
                         img.onload = () => {
                             const container = document.createElement('div');
                             container.style.marginBottom = '8px';
                             container.style.width = '100%';

                             const imgElement = document.createElement('img');
                             imgElement.src = img.src;
                             imgElement.style.maxWidth = '40%';
                             imgElement.style.height = 'auto';
                             imgElement.style.border = '1px solid #ccc';
                             imgElement.style.borderRadius = '4px';

                             container.appendChild(imgElement);
                             imagesPlaceholder.appendChild(container);
                             console.log('Image loaded successfully:', mediaItem.media_url);
                             resolve();
                         };
                         img.onerror = () => {
                             console.error('Failed to load image:', mediaItem.media_url);
                             // Still create a placeholder to show there was media
                             const container = document.createElement('div');
                             container.style.marginBottom = '8px';
                             container.style.width = '100%';
                             container.style.padding = '10px';
                             container.style.backgroundColor = '#f3f4f6';
                             container.style.borderRadius = '4px';
                             container.textContent = 'Image could not be loaded';
                             imagesPlaceholder.appendChild(container);
                             resolve(); // Resolve instead of reject to continue with other images
                         };

                         // Use display_url if available (generated by backend), otherwise construct manually
                         let imageUrl = mediaItem.display_url || mediaItem.media_url;
                         if (!imageUrl.startsWith('http')) {
                             imageUrl = `/storage/${mediaItem.media_url}`;
                         }

                         console.log('Loading image from:', imageUrl);
                         img.src = imageUrl;
                     });

                    imagePromises.push(promise);
                });
            }

            // Wait for all images to load before generating PDF
            console.log(`⏳ Waiting for ${imagePromises.length} promise(s) to complete (map + images)...`);
            Promise.allSettled(imagePromises).then((results) => {
                console.log('✅ All promises settled:', results);
                console.log('🎨 Rendering final PDF content with html2canvas...');
                
                // Use html2canvas to capture the content
                html2canvas(tempContainer, {
                    scale: 2, // Higher quality
                    useCORS: true,
                    logging: false,
                    allowTaint: true
                }).then(canvas => {
                    console.log('✅ Canvas created, size:', canvas.width, 'x', canvas.height);
                    
                    // Create PDF
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgWidth = 210; // A4 width in mm
                    const pageHeight = 297; // A4 height in mm
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;
                    let position = 0;

                    console.log('📄 Adding content to PDF, estimated pages:', Math.ceil(imgHeight / pageHeight));
                    
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;

                    // Add new pages if content is too long
                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }

                    // Save the PDF
                    const fileName = `report_${report.report_id.toString().padStart(5, '0')}.pdf`;
                    console.log('💾 Saving PDF as:', fileName);
                    pdf.save(fileName);
                    console.log('✅ PDF download complete!');

                    // Clean up
                    document.body.removeChild(tempContainer);
                    console.log('🧹 Cleaned up temporary elements');
                }).catch(error => {
                    console.error('❌ Error generating PDF:', error);
                    alert('Error generating PDF: ' + error.message);
                    document.body.removeChild(tempContainer);
                });
            });
        }
*/
// END OF OLD IMPLEMENTATION

        // Lightbox functions
        function openLightbox(index, imagesJson) {
            // Parse the JSON string back to an array
            currentImages = JSON.parse(imagesJson.replace(/&quot;/g, '"'));
            currentImageIndex = index;
            document.getElementById('lightboxImage').src = currentImages[currentImageIndex];
            document.getElementById('lightbox').classList.add('active');

            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const lightboxElement = document.getElementById('lightbox');
            lightboxElement.classList.remove('active');

            // Re-enable background scrolling
            document.body.style.overflow = '';

            // Clear the image
            document.getElementById('lightboxImage').src = '';
        }

        function changeImage(direction) {
            currentImageIndex += direction;

            // Handle boundaries
            if (currentImageIndex < 0) {
                currentImageIndex = currentImages.length - 1;
            } else if (currentImageIndex >= currentImages.length) {
                currentImageIndex = 0;
            }

            document.getElementById('lightboxImage').src = currentImages[currentImageIndex];
        }

        // Close modals when clicking outside
        document.getElementById('reportModal').addEventListener('click', function (event) {
            if (event.target === this) {
                closeModal();
            }
        });

        document.getElementById('lightbox').addEventListener('click', function (event) {
            if (event.target === this) {
                closeLightbox();
            }
        });

        // Keyboard navigation for lightbox
        document.addEventListener('keydown', function (event) {
            // Only handle keyboard events when lightbox is open
            if (document.getElementById('lightbox').classList.contains('active')) {
                switch (event.key) {
                    case 'Escape':
                        closeLightbox();
                        break;
                    case 'ArrowLeft':
                        changeImage(-1);
                        break;
                    case 'ArrowRight':
                        changeImage(1);
                        break;
                }
            }
        });

        // ========== VALIDITY UPDATE FUNCTION ==========
        function updateValidity(reportId, newValidity) {
            // If selecting "invalid", show rejection modal instead
            if (newValidity === 'invalid') {
                openRejectionModal(reportId);
                // Reset the select to its original value
                event.target.value = event.target.dataset.originalValidity;
                return;
            }

            // For other validity values, update directly
            fetch(`/reports/${reportId}/validity`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ validity: newValidity })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the data attribute
                    event.target.dataset.originalValidity = newValidity;
                    // Show success message (optional)
                    console.log('Validity updated successfully');
                } else {
                    alert('Failed to update validity');
                    // Reset to original value
                    event.target.value = event.target.dataset.originalValidity;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating validity');
                // Reset to original value
                event.target.value = event.target.dataset.originalValidity;
            });
        }
        // ========== END VALIDITY UPDATE FUNCTION ==========

        // ========== REJECTION REASON MODAL ==========
        function openRejectionModal(reportId) {
            const modal = document.getElementById('rejectionModal');
            const form = document.getElementById('rejectionForm');
            form.action = `/reports/${reportId}/validity`;
            modal.style.display = 'flex';
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').style.display = 'none';
            document.getElementById('rejectionReasonInput').value = '';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('rejectionModal');
            if (event.target === modal) {
                closeRejectionModal();
            }
        });
        // ========== END REJECTION REASON MODAL ==========
    </script>

    <!-- Rejection Reason Modal -->
    <div id="rejectionModal" class="modal-overlay" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Reject Report</h2>
                <button class="modal-close" onclick="closeRejectionModal()" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 16px; color: #666; font-size: 14px;">
                    Please provide a reason for rejecting this report. This will be sent to the user via email.
                </p>
                <form id="rejectionForm" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="validity" value="invalid">
                    <textarea 
                        id="rejectionReasonInput"
                        name="rejection_reason" 
                        required
                        rows="5"
                        placeholder="Enter rejection reason (e.g., 'Insufficient evidence', 'Duplicate report', 'Outside jurisdiction')..."
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical;"
                    ></textarea>
                    <div style="margin-top: 16px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="closeRejectionModal()" style="padding: 10px 20px; background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            Cancel
                        </button>
                        <button type="submit" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            Reject Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dispatch Modal - uses dispatch-modal class to avoid CSS conflicts -->
    <div id="dispatchModal" class="dispatch-modal" style="display: none;">
        <div class="dispatch-modal-content">
            <div class="modal-header">
                <h2>🚓 Dispatch Patrol Officer</h2>
                <button class="modal-close" onclick="window.closeDispatchModal()" type="button">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 24px;">
                <div id="dispatch-loading" style="display: none;">
                    <div style="width: 60px; height: 60px; border: 4px solid #e5e7eb; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                    <p style="color: #666; font-size: 14px;">Finding nearest patrol officer...</p>
                </div>
                <div id="dispatch-confirm" style="display: block;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <span style="font-size: 36px;">🚓</span>
                    </div>
                    <h3 style="margin: 0 0 8px; font-size: 18px; color: #1f2937;">Ready to Dispatch</h3>
                    <p style="margin: 0 0 16px; color: #666; font-size: 14px; line-height: 1.5;">
                        Select an available patrol officer to respond to this report.
                    </p>
                    <div style="margin-bottom: 24px; text-align: left;">
                        <label for="patrol_officer_select" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px;">Available Officers</label>
                        <select id="patrol_officer_select" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; background-color: #f9fafb;">
                            <option value="">Loading officers...</option>
                        </select>
                    </div>
                    <input type="hidden" id="dispatch_report_id">
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button type="button" onclick="window.closeDispatchModal()" style="padding: 12px 24px; background: #f3f4f6; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">
                            Cancel
                        </button>
                        <button type="button" onclick="window.dispatchToNearestPatrol()" style="padding: 12px 24px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                            🚓 Dispatch Now
                        </button>
                    </div>
                </div>
                <div id="dispatch-success" style="display: none;">
                    <div style="width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <span style="font-size: 36px;">✅</span>
                    </div>
                    <h3 style="margin: 0 0 8px; font-size: 18px; color: #16a34a;">Patrol Dispatched!</h3>
                    <p style="margin: 0 0 24px; color: #666; font-size: 14px; line-height: 1.5;">
                        Patrol sent to the nearest dispatch in the reported area. The officer has been notified with an urgent alert.
                    </p>
                    <button type="button" onclick="window.closeDispatchModal(); location.reload();" style="padding: 12px 24px; background: #16a34a; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        Done
                    </button>
                </div>
                <div id="dispatch-error" style="display: none;">
                    <div style="width: 80px; height: 80px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <span style="font-size: 36px;">❌</span>
                    </div>
                    <h3 style="margin: 0 0 8px; font-size: 18px; color: #dc2626;">Dispatch Failed</h3>
                    <p id="dispatch-error-message" style="margin: 0 0 24px; color: #666; font-size: 14px; line-height: 1.5;">
                        No patrol officers are currently on duty or available.
                    </p>
                    <button type="button" onclick="window.closeDispatchModal();" style="padding: 12px 24px; background: #f3f4f6; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Dispatch Modal - separate from main modal-overlay to avoid CSS conflicts */
        .dispatch-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999; /* Higher than report detail modal */
        }
        
        .dispatch-modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 450px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <script>
    // Dispatch Modal Functions - Made global for onclick handlers
    window.openDispatchModal = function(reportId) {
        console.log('🚓 Opening dispatch modal for report:', reportId);
        document.getElementById('dispatch_report_id').value = reportId;
        // Reset to confirm state
        document.getElementById('dispatch-loading').style.display = 'none';
        document.getElementById('dispatch-confirm').style.display = 'block';
        document.getElementById('dispatch-success').style.display = 'none';
        document.getElementById('dispatch-error').style.display = 'none';
        document.getElementById('dispatchModal').style.display = 'flex';
        
        // Fetch on-duty officers
        const selectElement = document.getElementById('patrol_officer_select');
        selectElement.innerHTML = '<option value="">Loading officers...</option>';
        selectElement.disabled = true;
        
        const apiUrl = "{{ url('/api/on-duty-officers') }}";
        fetch(apiUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            credentials: 'same-origin'
        })
            .then(res => {
                console.log('🚓 Officers API response status:', res.status, 'content-type:', res.headers.get('content-type'));
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response (possible auth redirect)');
                }
                return res.json();
            })
            .then(data => {
                console.log('🚓 Officers data:', data);
                const officers = data.officers || [];
                selectElement.innerHTML = '<option value="">-- Select an Officer --</option>';
                if (officers.length === 0) {
                    selectElement.innerHTML = '<option value="">No officers currently available</option>';
                } else {
                    officers.forEach(officer => {
                        const option = document.createElement('option');
                        option.value = officer.id;
                        const locationText = officer.station_name ? ` (${officer.station_name})` : '';
                        const dutyStatus = officer.is_on_duty ? ' (Online)' : ' (Offline)';
                        option.textContent = officer.name + dutyStatus + locationText;
                        selectElement.appendChild(option);
                    });
                    selectElement.disabled = false;
                }
            })
            .catch(err => {
                console.error('🚓 Error fetching officers:', err);
                selectElement.innerHTML = `<option value="">Error: ${err.message}</option>`;
            });
    }
    
    // Event delegation for dispatch buttons - handles dynamically created buttons
    document.addEventListener('click', function(e) {
        const dispatchBtn = e.target.closest('[data-dispatch-report]');
        if (dispatchBtn) {
            e.preventDefault();
            e.stopPropagation();
            const reportId = dispatchBtn.getAttribute('data-dispatch-report');
            console.log('🚓 Dispatch button clicked via delegation for report:', reportId);
            window.openDispatchModal(reportId);
        }
    });
    
    window.dispatchToNearestPatrol = function() {
        const reportId = document.getElementById('dispatch_report_id').value;
        const officerId = document.getElementById('patrol_officer_select').value;
        
        if (!officerId) {
            alert('Please select an available patrol officer.');
            return;
        }
        
        console.log('🚓 Dispatching patrol for report:', reportId, 'to officer:', officerId);
        
        // Show loading
        document.getElementById('dispatch-confirm').style.display = 'none';
        document.getElementById('dispatch-loading').style.display = 'block';
        
        // Manual dispatch to selected patrol
        fetch('/dispatches', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ report_id: reportId, patrol_officer_id: officerId, notes: 'Manually dispatched from Admin dashboard.' })
        })
        .then(response => {
            console.log('📡 Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📊 Response data:', data);
            document.getElementById('dispatch-loading').style.display = 'none';
            if (data.success) {
                document.getElementById('dispatch-success').style.display = 'block';
                console.log('✅ Dispatch successful:', data.message);
            } else {
                document.getElementById('dispatch-error-message').textContent = data.message || 'No patrol officers are currently on duty or available.';
                document.getElementById('dispatch-error').style.display = 'block';
                console.error('❌ Dispatch failed:', data.message);
            }
        })
        .catch(error => {
            console.error('🔥 Dispatch error:', error);
            document.getElementById('dispatch-loading').style.display = 'none';
            document.getElementById('dispatch-error-message').textContent = 'Failed to dispatch. Please try again. Error: ' + error.message;
            document.getElementById('dispatch-error').style.display = 'block';
        });
    }

    window.closeDispatchModal = function() {
        document.getElementById('dispatchModal').style.display = 'none';
    }

    // ========== AUTO-REFRESH FUNCTIONALITY ==========
    // Auto-refresh reports every 5 seconds to get real-time updates from patrol
    let autoRefreshInterval = null;
    let lastReportData = {};

    function initAutoRefresh() {
        // Store current report data for comparison
        document.querySelectorAll('tr[data-report-id]').forEach(row => {
            const reportId = row.dataset.reportId;
            const statusSelect = row.querySelector('.status-select');
            if (statusSelect) {
                lastReportData[reportId] = statusSelect.value;
            }
        });

        // Refresh every 20s + immediately on socket live updates
        autoRefreshInterval = setInterval(fetchReportUpdates, 20000);
        window.addEventListener('adminLiveUpdate', () => {
            if (document.visibilityState === 'visible') fetchReportUpdates();
        });
    }

    async function fetchReportUpdates() {
        try {
            const response = await fetch('/api/reports/updates', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            });

            if (!response.ok) throw new Error('Failed to fetch updates');

            const data = await response.json();

            if (data.success && data.reports) {
                updateReportRows(data.reports);
                updateTabCounts(data.counts);
            }
        } catch (error) {
            console.warn('Auto-refresh error:', error.message);
        }
    }

    function updateReportRows(reports) {
        const statusFilter = new URLSearchParams(window.location.search).get('status');
        reports.forEach(report => {
            const row = document.querySelector(`tr[data-report-id="${report.report_id}"]`);
            if (!row) return;

            if (statusFilter && report.status !== statusFilter) {
                row.remove();
                return;
            }

            // Check if status changed
            const statusSelect = row.querySelector('.status-select');
            if (statusSelect && statusSelect.value !== report.status) {
                // Status was updated by patrol - animate and update
                statusSelect.value = report.status;
                statusSelect.dataset.originalStatus = report.status;
                
                // Highlight the row briefly
                row.style.transition = 'background-color 0.5s ease';
                row.style.backgroundColor = '#fef3c7';
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 2000);

                // Show notification
                showUpdateNotification(report);
            }

            const validitySelect = row.querySelector('.validity-select');
            if (validitySelect && validitySelect.value !== report.is_valid) {
                validitySelect.value = report.is_valid;
                validitySelect.dataset.originalValidity = report.is_valid;
            }

            // Update SLA Timer data hooks dynamically if a patrol just arrived
            const slaTimer = row.querySelector('.sla-timer');
            if (slaTimer) {
                if (report.dispatch && report.dispatch.arrived_at) {
                    // Calculate Unix timestamp of arrival to freeze the timer dynamically
                    const arrivedUnixTimestamp = Math.floor(new Date(report.dispatch.arrived_at).getTime() / 1000);
                    slaTimer.dataset.arrivedAt = arrivedUnixTimestamp;
                }
            }
        });
    }

    function updateTabCounts(counts) {
        if (!counts) return;

        // Update all count
        const allCountEl = document.getElementById('allCount');
        if (allCountEl && counts.all !== undefined) {
            allCountEl.textContent = counts.all;
        }

        // Update pending count
        const pendingCountEl = document.getElementById('pendingCount');
        if (pendingCountEl && counts.pending !== undefined) {
            pendingCountEl.textContent = counts.pending;
            
            // Show/hide pulse indicator
            const pulseEl = document.querySelector('.tab-pulse');
            if (pulseEl) {
                pulseEl.style.display = counts.pending > 0 ? 'block' : 'none';
            }
        }

        // Update investigating count
        const investigatingCountEl = document.getElementById('investigatingCount');
        if (investigatingCountEl && counts.investigating !== undefined) {
            investigatingCountEl.textContent = counts.investigating;
        }

        // Update resolved count
        const resolvedCountEl = document.getElementById('resolvedCount');
        if (resolvedCountEl && counts.resolved !== undefined) {
            resolvedCountEl.textContent = counts.resolved;
        }
    }

    function showUpdateNotification(report) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'update-toast';
        toast.innerHTML = `
            <div class="toast-icon">🔔</div>
            <div class="toast-content">
                <div class="toast-title">Report #${String(report.report_id).padStart(5, '0')} Updated</div>
                <div class="toast-message">Status changed to: ${report.status}</div>
            </div>
        `;
        document.body.appendChild(toast);

        // Animate in
        setTimeout(() => toast.classList.add('show'), 10);

        // Remove after 4 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Initialize auto-refresh when page loads (socket events handled via layout's adminLiveUpdate)
    document.addEventListener('DOMContentLoaded', () => {
        initAutoRefresh();
    });

    // Pause auto-refresh when user is interacting with modals
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                // Modal closed, resume refresh
            }
        });
    });
    </script>

    <!-- Toast Notification Styles -->
    <style>
        .update-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border-left: 4px solid #3b82f6;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            z-index: 9999;
        }

        .update-toast.show {
            transform: translateX(0);
        }

        .toast-icon {
            font-size: 24px;
        }

        .toast-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }

        .toast-message {
            color: #6b7280;
            font-size: 13px;
            margin-top: 2px;
        }
    </style>
@endsection