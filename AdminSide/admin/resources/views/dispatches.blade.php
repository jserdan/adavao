@extends('layouts.app')

@section('title', 'Patrol Dispatches')

@section('styles')
<style>
    /* Clean, Professional Styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-section {
        background: white;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e7eb;
        margin-bottom: 1.5rem;
    }

    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }

    .form-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-control {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.875rem;
        color: #374151;
        background-color: #fff;
        transition: border-color 0.15s ease-in-out;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-select {
        padding-right: 2rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s;
        cursor: pointer;
        border: 1px solid transparent;
        gap: 0.5rem;
    }

    .btn-primary {
        background-color: #1f2937;
        color: white;
    }
    .btn-primary:hover { background-color: #111827; }

    .btn-secondary {
        background-color: white;
        border-color: #d1d5db;
        color: #374151;
    }
    .btn-secondary:hover { background-color: #f9fafb; border-color: #9ca3af; }

    .reports-table-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        overflow-x: auto;
    }

    .reports-table {
        width: 100%;
        min-width: 1200px;
        border-collapse: collapse;
    }

    .reports-table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background-color: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }

    .reports-table td {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        color: #1f2937;
        border-bottom: 1px solid #f3f4f6;
    }

    .reports-table tr:hover {
        background-color: #f9fafb;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 0;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.25;
    }
    .badge-success { color: #065f46; }
    .badge-danger { color: #991b1b; }
    .badge-warning { color: #92400e; }
    .badge-info { color: #1e40af; }
    .badge-gray { color: #374151; }

    /* Urgency Badges */
    .urgency-badge {
        padding: 2px 0;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .urgency-critical { color: #991b1b; }
    .urgency-high { color: #9a3412; }
    .urgency-medium { color: #92400e; }
    .urgency-low { color: #6b7280; }

    /* Validity Badges */
    .validity-badge {
        padding: 2px 0;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .validity-badge.valid { color: #065f46; }
    .validity-badge.invalid { color: #991b1b; }
    .validity-badge.checking_for_report_validity { color: #3730a3; }

    /* SLA Timer */
    .sla-timer {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 600;
    }
    .sla-timer.countdown { color: #2563eb; }
    .sla-timer.exceeded { color: #dc2626; }

    /* Action Buttons */
    .action-group {
        display: flex;
        gap: 0.5rem;
    }
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: white;
        color: #4b5563;
        transition: all 0.2s;
        cursor: pointer;
    }
    .action-btn:hover {
        background-color: #f3f4f6;
        color: #111827;
        border-color: #d1d5db;
    }
    .action-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Modal Styles */
    .modal {
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    .modal-content {
        background-color: #fff;
        margin: 4% auto; 
        padding: 0;
        border: 1px solid #e5e7eb;
        width: 90%; 
        max-width: 700px;
        border-radius: 12px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f9fafb;
    }
    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
    }
    .close {
        color: #9ca3af;
        font-size: 1.5rem;
        font-weight: 400;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }
    .close:hover { color: #111827; }
    .modal-body {
        padding: 1.5rem;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .detail-item label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    .detail-item div {
        font-size: 0.95rem;
        color: #111827;
        font-weight: 500;
    }
    .detail-full {
        grid-column: 1 / -1;
    }
</style>
@endsection

@section('content')
<div class="reports-container">
    <div class="page-header">
        <h1 class="page-title">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Patrol Dispatches
        </h1>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form action="{{ route('dispatches') }}" method="GET" class="filter-form">
            <div class="form-group" style="flex-grow: 1;">
                <input type="text" name="search" class="form-control" style="width: 100%;" 
                       placeholder="Search Report ID, User, or Officer..." 
                       value="{{ request('search') }}">
            </div>
            
            <div class="form-group">
                <select name="status" class="form-control form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="accepted" {{ request('status') == 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="declined" {{ request('status') == 'declined' ? 'selected' : '' }}>Declined</option>
                    <option value="en_route" {{ request('status') == 'en_route' ? 'selected' : '' }}>En Route</option>
                    <option value="arrived" {{ request('status') == 'arrived' ? 'selected' : '' }}>Arrived</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <input type="date" name="date_from" class="form-control" title="From Date" value="{{ request('date_from') }}">
                <span style="color: #6b7280; font-size: 0.875rem;">to</span>
                <input type="date" name="date_to" class="form-control" title="To Date" value="{{ request('date_to') }}">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                Filter
            </button>
            
            @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
            <a href="{{ route('dispatches') }}" class="btn btn-secondary">
                Reset
            </a>
            @endif
        </form>
    </div>

    <!-- Table Section -->
    <div class="reports-table-container">
        <table class="reports-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Report ID</th>
                    <th>User</th>
                    <th>Urgency</th>
                    <th>Response Time</th>
                    <th>3-Min Rule</th>
                    <th>Validated At</th>
                    <th>Date</th>
                    <th>Patrol Dispatched</th>
                    <th>Dispatch Status</th>
                    <th>Officer In-Charge</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dispatches as $dispatch)
                    <tr data-dispatch-id="{{ $dispatch->dispatch_id }}" data-report-id="{{ $dispatch->report_id }}">
                        <td style="font-family: monospace;">#{{ $dispatch->report_id }}</td>
                        <td>
                            <div style="font-weight: 500;">{{ $dispatch->report->user->firstname ?? 'Anonymous' }} {{ $dispatch->report->user->lastname ?? '' }}</div>
                        </td>
                        <td>
                            @php $urgency = $dispatch->report->urgency_score ?? 0; @endphp
                            <span class="urgency-badge urgency-{{ $urgency >= 80 ? 'critical' : ($urgency >= 50 ? 'high' : ($urgency >= 20 ? 'medium' : 'low')) }}">
                                {{ $urgency }}
                            </span>
                        </td>
                        <td class="response-time-cell">
                            @php
                                $acceptedAt = $dispatch->accepted_at;
                                $arrivedAt = $dispatch->arrived_at;
                                $dispatchedAt = $dispatch->dispatched_at ?? $dispatch->report->created_at;
                            @endphp
                            @if($arrivedAt && $acceptedAt)
                                @php
                                    $responseSeconds = Carbon\Carbon::parse($acceptedAt)->diffInSeconds($arrivedAt);
                                    $h = floor($responseSeconds / 3600);
                                    $m = floor(($responseSeconds % 3600) / 60);
                                    $s = $responseSeconds % 60;
                                    $timeStr = $h > 0 ? sprintf('%dh %dm %ds', $h, $m, $s) : ($m > 0 ? sprintf('%dm %ds', $m, $s) : sprintf('%ds', $s));
                                @endphp
                                <span class="badge badge-success" title="Officer arrived at scene">✅ {{ $timeStr }}</span>
                            @elseif($acceptedAt)
                                <div class="sla-timer countdown" 
                                     data-accepted-at="{{ Carbon\Carbon::parse($acceptedAt)->timestamp }}"
                                     title="Time since officer accepted">⏱ Counting...</div>
                            @else
                                <span class="badge badge-gray">Pending acceptance</span>
                            @endif
                        </td>
                        <td class="rule-status-cell">
                            @if($dispatch->three_minute_rule_met !== null)
                                @if($dispatch->three_minute_rule_met)
                                    <span class="badge badge-success">✅ Within 3 Min</span>
                                @else
                                    <span class="badge badge-danger">❌ Exceeded</span>
                                @endif
                            @elseif($acceptedAt)
                                @php
                                    $acceptSeconds = Carbon\Carbon::parse($dispatchedAt)->diffInSeconds($acceptedAt);
                                @endphp
                                @if($acceptSeconds <= 180)
                                    <span class="badge badge-success">✅ Within 3 Min</span>
                                @else
                                    <span class="badge badge-danger">❌ Exceeded ({{ floor($acceptSeconds/60) }}m {{ $acceptSeconds%60 }}s)</span>
                                @endif
                            @else
                                <div class="rule-timer" data-dispatched-at="{{ Carbon\Carbon::parse($dispatchedAt)->timestamp }}">⏳ Waiting...</div>
                            @endif
                        </td>
                        <td>{{ optional($dispatch->report->validated_at)->format('M d, H:i') ?? '-' }}</td>
                        <td>{{ $dispatch->report->created_at->format('M d, Y') }}</td>
                        <td>
                            <div style="font-weight: 500;">{{ optional($dispatch->dispatched_at)->format('H:i') ?? '-' }}</div>
                            <div style="font-size: 0.75rem; color: #6b7280;">{{ optional($dispatch->dispatched_at)->format('M d') ?? '' }}</div>
                        </td>
                        <td class="dispatch-status-cell">
                            @php
                                $statusColors = [
                                    'pending' => 'badge-warning',
                                    'assigned' => 'badge-info',
                                    'accepted' => 'badge-info',
                                    'en_route' => 'badge-info',
                                    'arrived' => 'badge-success',
                                    'completed' => 'badge-success',
                                    'declined' => 'badge-danger',
                                    'cancelled' => 'badge-gray',
                                ];
                                $statusLabels = [
                                    'pending' => '🟡 Pending',
                                    'assigned' => '📋 Assigned',
                                    'accepted' => '✅ Accepted',
                                    'en_route' => '🚗 En Route',
                                    'arrived' => '📍 Arrived',
                                    'completed' => '✔️ Completed',
                                    'declined' => '❌ Declined',
                                    'cancelled' => '🚫 Cancelled',
                                ];
                            @endphp
                            <span class="badge {{ $statusColors[$dispatch->status] ?? 'badge-gray' }}">
                                {{ $statusLabels[$dispatch->status] ?? ucfirst($dispatch->status) }}
                            </span>
                        </td>
                        <td class="officer-cell">{{ $dispatch->patrolOfficer->firstname ?? 'Unassigned' }} {{ $dispatch->patrolOfficer->lastname ?? '' }}</td>
                        <td>
                            <div class="action-group">
                                <button class="action-btn" onclick="showReportDetails({{ $dispatch->report_id }})" title="View Details">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                                <button class="action-btn" onclick="openTransferModal({{ $dispatch->report_id }})" title="Transfer Patrol">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 3rem;">
                            <div style="color: #6b7280; font-weight: 500;">No dispatches found</div>
                            <div style="color: #9ca3af; font-size: 0.875rem;">Try adjusting your filters</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 1.5rem;">
        {{ $dispatches->links() }}
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Report Details</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalBody" class="modal-body">
            <!-- Content loaded via JS -->
            <div style="text-align: center; color: #6b7280;">Loading details...</div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // ═══════════════════════════════════════════════
    // LIVE TIMERS (Response Time & 3-Min Rule)
    // ═══════════════════════════════════════════════

    function formatTime(totalSec) {
        const h = Math.floor(totalSec / 3600);
        const m = Math.floor((totalSec % 3600) / 60);
        const s = totalSec % 60;
        if (h > 0) return `${h}h ${m}m ${s}s`;
        if (m > 0) return `${m}m ${s}s`;
        return `${s}s`;
    }

    function updateTimers() {
        const now = Math.floor(Date.now() / 1000);

        // Response time timers (counting from accepted_at)
        document.querySelectorAll('.sla-timer[data-accepted-at]').forEach(timer => {
            const acceptedAt = parseInt(timer.getAttribute('data-accepted-at'));
            if (!acceptedAt) return;
            const elapsed = now - acceptedAt;
            timer.textContent = '⏱ ' + formatTime(elapsed);
            timer.className = elapsed <= 180 ? 'sla-timer countdown' : 'sla-timer exceeded';
        });

        // Rule timers (counting from dispatched_at — waiting for acceptance)
        document.querySelectorAll('.rule-timer[data-dispatched-at]').forEach(timer => {
            const dispatchedAt = parseInt(timer.getAttribute('data-dispatched-at'));
            if (!dispatchedAt) return;
            const elapsed = now - dispatchedAt;
            if (elapsed <= 180) {
                timer.textContent = '⏳ ' + formatTime(180 - elapsed) + ' left';
                timer.style.color = '#2563eb';
            } else {
                timer.textContent = '❌ Exceeded (' + formatTime(elapsed) + ')';
                timer.style.color = '#dc2626';
            }
        });
    }

    updateTimers();
    setInterval(updateTimers, 1000);

    // ═══════════════════════════════════════════════
    // SMART POLLING (auto-update without disruption)
    // ═══════════════════════════════════════════════

    const STATUS_LABELS = {
        pending: '🟡 Pending', assigned: '📋 Assigned', accepted: '✅ Accepted',
        en_route: '🚗 En Route', arrived: '📍 Arrived', completed: '✔️ Completed',
        declined: '❌ Declined', cancelled: '🚫 Cancelled'
    };
    const STATUS_COLORS = {
        pending: 'badge-warning', assigned: 'badge-info', accepted: 'badge-info',
        en_route: 'badge-info', arrived: 'badge-success', completed: 'badge-success',
        declined: 'badge-danger', cancelled: 'badge-gray'
    };

    let isModalOpen = false;

    async function pollDispatches() {
        // Skip polling if modal is open (user is interacting)
        if (isModalOpen) return;

        try {
            const resp = await fetch('/api/dispatches/live-status');
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.success || !json.data) return;

            json.data.forEach(d => {
                const row = document.querySelector(`tr[data-dispatch-id="${d.dispatch_id}"]`);
                if (!row) return;

                // Update Dispatch Status column
                const statusCell = row.querySelector('.dispatch-status-cell');
                if (statusCell) {
                    const label = STATUS_LABELS[d.status] || d.status;
                    const color = STATUS_COLORS[d.status] || 'badge-gray';
                    const current = statusCell.textContent.trim();
                    if (current !== label) {
                        statusCell.innerHTML = `<span class="badge ${color}">${label}</span>`;
                        flashCell(statusCell);
                    }
                }

                // Update Officer column
                const officerCell = row.querySelector('.officer-cell');
                if (officerCell && d.officer_name) {
                    const currentOfficer = officerCell.textContent.trim();
                    if (currentOfficer !== d.officer_name && d.officer_name !== '') {
                        officerCell.textContent = d.officer_name;
                        flashCell(officerCell);
                    }
                }

                // Update Response Time column
                const rtCell = row.querySelector('.response-time-cell');
                if (rtCell) {
                    if (d.arrived_at && d.accepted_at) {
                        // Arrived — freeze the timer
                        const acceptedTs = Math.floor(new Date(d.accepted_at).getTime() / 1000);
                        const arrivedTs = Math.floor(new Date(d.arrived_at).getTime() / 1000);
                        const elapsed = arrivedTs - acceptedTs;
                        const newHtml = `<span class="badge badge-success" title="Officer arrived at scene">✅ ${formatTime(elapsed)}</span>`;
                        if (!rtCell.innerHTML.includes('✅')) {
                            rtCell.innerHTML = newHtml;
                            flashCell(rtCell);
                        }
                    } else if (d.accepted_at && !d.arrived_at) {
                        // Accepted but not arrived — show live timer
                        const acceptedTs = Math.floor(new Date(d.accepted_at).getTime() / 1000);
                        if (!rtCell.querySelector('.sla-timer[data-accepted-at]')) {
                            rtCell.innerHTML = `<div class="sla-timer countdown" data-accepted-at="${acceptedTs}" title="Time since officer accepted">⏱ Counting...</div>`;
                            flashCell(rtCell);
                        }
                    }
                }

                // Update 3-Min Rule column
                const ruleCell = row.querySelector('.rule-status-cell');
                if (ruleCell) {
                    if (d.three_minute_rule_met === true) {
                        if (!ruleCell.innerHTML.includes('Within 3 Min')) {
                            ruleCell.innerHTML = '<span class="badge badge-success">✅ Within 3 Min</span>';
                            flashCell(ruleCell);
                        }
                    } else if (d.three_minute_rule_met === false) {
                        if (!ruleCell.innerHTML.includes('Exceeded')) {
                            const acceptTime = d.acceptance_time ? ` (${formatTime(d.acceptance_time)})` : '';
                            ruleCell.innerHTML = `<span class="badge badge-danger">❌ Exceeded${acceptTime}</span>`;
                            flashCell(ruleCell);
                        }
                    } else if (d.accepted_at && d.dispatched_at) {
                        // Calculate from timestamps
                        const dispTs = Math.floor(new Date(d.dispatched_at).getTime() / 1000);
                        const accTs = Math.floor(new Date(d.accepted_at).getTime() / 1000);
                        const secs = accTs - dispTs;
                        if (secs <= 180) {
                            ruleCell.innerHTML = '<span class="badge badge-success">✅ Within 3 Min</span>';
                        } else {
                            ruleCell.innerHTML = `<span class="badge badge-danger">❌ Exceeded (${formatTime(secs)})</span>`;
                        }
                        // Remove the live rule-timer if it exists
                        const liveTimer = ruleCell.querySelector('.rule-timer');
                        if (liveTimer) liveTimer.remove();
                    }
                }
            });
        } catch (err) {
            console.warn('Dispatch poll error:', err);
        }
    }

    // Flash animation when a cell updates
    function flashCell(cell) {
        cell.style.transition = 'background-color 0.3s';
        cell.style.backgroundColor = '#fef9c3';
        setTimeout(() => {
            cell.style.backgroundColor = '';
            setTimeout(() => { cell.style.transition = ''; }, 300);
        }, 1500);
    }

    // Poll every 8 seconds
    setInterval(pollDispatches, 8000);

    // Instant update on Socket.io push from backend
    window.addEventListener('adminLiveUpdate', (e) => {
        e.preventDefault(); // prevent generic full-page refresh
        pollDispatches();
    });

    // ═══════════════════════════════════════════════
    // MODAL FUNCTIONS
    // ═══════════════════════════════════════════════

    function closeModal() {
        document.getElementById('detailsModal').style.display = 'none';
        isModalOpen = false;
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('detailsModal')) {
            closeModal();
        }
    }

    function showReportDetails(reportId) {
        const modal = document.getElementById('detailsModal');
        const modalBody = document.getElementById('modalBody');
        modal.style.display = 'block';
        isModalOpen = true;
        modalBody.innerHTML = '<div style="text-align: center; padding: 2rem; color: #6b7280;">Loading details...</div>';

        fetch(`/reports/${reportId}/details`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const report = data.data;
                    const getType = (t) => Array.isArray(t) ? t.join(', ') : (t || 'N/A');
                    const formatDate = (d) => d ? new Date(d).toLocaleString() : 'N/A';
                    const userName = report.user ? (report.user.firstname + ' ' + report.user.lastname) : 'Anonymous';
                    
                    const html = `
                        <div class="detail-grid">
                            <div class="detail-full">
                                <div class="detail-item">
                                    <label>Report ID</label>
                                    <div style="font-size: 1.25rem; font-family: monospace;">#${report.report_id}</div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Title</label>
                                <div>${report.title || 'No Title'}</div>
                            </div>
                            <div class="detail-item">
                                <label>Type</label>
                                <div>${getType(report.report_type)}</div>
                            </div>
                            <div class="detail-full">
                                <div class="detail-item">
                                    <label>Description</label>
                                    <div>${report.description || 'No description'}</div>
                                </div>
                            </div>
                            <div class="detail-full">
                                <div class="detail-item">
                                    <label>Location</label>
                                    <div>${report.location ? (report.location.address || report.location.barangay || 'Unknown') : 'Unknown'}</div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Reported By</label>
                                <div>${userName}</div>
                            </div>
                            <div class="detail-item">
                                <label>Date Reported</label>
                                <div>${formatDate(report.date_reported || report.created_at)}</div>
                            </div>
                        </div>
                    `;
                    modalBody.innerHTML = html;
                } else {
                    modalBody.innerHTML = '<p style="color: #dc2626; text-align: center;">Failed to load details.</p>';
                }
            })
            .catch(err => {
                console.error(err);
                modalBody.innerHTML = '<p style="color: #dc2626; text-align: center;">Error loading details.</p>';
            });
    }

    function openTransferModal(reportId) {
        alert('Transfer/Reassign functionality will be available via the main Reports page or can be implemented here.');
    }
</script>
@endsection
