@extends('layouts.app')

@section('title', 'Reassignment Requests')

@section('styles')
<style>
    .requests-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .requests-title-section h1 {
        font-size: 1.875rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }
    
    .requests-title-section p {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .requests-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .requests-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .requests-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .requests-table th {
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        user-select: none;
        position: relative;
    }
    
    .requests-table th:hover {
        background: #f3f4f6;
    }
    
    .requests-table td {
        padding: 1rem 1.5rem;
        font-size: 0.875rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .requests-table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .requests-table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .requests-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    
    .status-badge.pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .status-badge.approved {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.rejected {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        border: 1.5px solid #e5e7eb;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-right: 0.25rem;
        width: 32px;
        height: 32px;
    }
    
    .action-btn:hover {
        background: #f9fafb;
        border-color: #3b82f6;
    }
    
    .action-btn svg {
        width: 16px;
        height: 16px;
        fill: #6b7280;
    }
    
    .action-btn:hover svg {
        fill: #3b82f6;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }
    
    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
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
        max-width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        width: 600px;
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
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .modal-title {
        font-size: 1.25rem;
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
        padding: 1.5rem;
    }
    
    .detail-item {
        margin-bottom: 1.25rem;
    }
    
    .detail-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    
    .detail-value {
        color: #1f2937;
        font-size: 0.875rem;
    }
    
    .actions-section {
        display: flex;
        gap: 0.75rem;
        padding: 1.5rem;
        border-top: 1px solid #e5e7eb;
        background: #f9fafb;
    }
    
    .btn {
        padding: 0.625rem 1.25rem;
        border-radius: 6px;
        font-size: 0.875rem;
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
    
    @media (max-width: 768px) {
        .requests-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .requests-table-container {
            overflow-x: auto;
        }
        
        .requests-table {
            min-width: 900px;
        }
        
        .modal-content {
            width: 95%;
            margin: 1rem;
        }
    }
</style>
@endsection

@section('content')
<div class="requests-header">
    <div class="requests-title-section">
        <h1>Reassignment Requests</h1>
        <p>Manage report reassignment requests from police officers</p>
    </div>
</div>

<div class="requests-table-container">
    <table class="requests-table" id="requestsTable">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Report ID</th>
                <th>Requested By</th>
                <th>Current Station</th>
                <th>Requested Station</th>
                <th>Date Submitted</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="requestsTableBody">
            <tr>
                <td colspan="8" class="no-results">
                    Loading requests...
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Request Details Modal -->
<div class="modal-overlay" id="requestModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Request Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let requests = [];
let currentRequest = null;
const userRole = '{{ auth()->user()->hasRole("admin") ? "admin" : (auth()->user()->hasRole("police") ? "police" : "") }}';

// Load reassignment requests
async function loadRequests() {
    try {
        const response = await fetch('/reassignment-requests', {
            headers: {
                'Accept': 'application/json'
            }
        });
        
        // If response is HTML, we're viewing the page, fetch the API
        if (response.headers.get('content-type')?.includes('text/html')) {
            const apiResponse = await fetch('/api/reassignment-requests');
            const data = await apiResponse.json();
            
            if (data.success) {
                requests = data.data;
                displayRequests();
            } else {
                showError('Failed to load requests');
            }
        }
    } catch (error) {
        console.error('Error loading requests:', error);
        showError('An error occurred while loading requests');
    }
}

// Display requests in table
function displayRequests() {
    const tbody = document.getElementById('requestsTableBody');
    
    if (!requests || requests.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="no-results">
                    <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.3;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                    </svg>
                    <p>No reassignment requests found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = requests.map(request => {
        const createdAt = new Date(request.created_at);
        return `
            <tr>
                <td>#${String(request.request_id).padStart(5, '0')}</td>
                <td>#${String(request.report_id).padStart(5, '0')}</td>
                <td>${request.requested_by ? request.requested_by.firstname + ' ' + request.requested_by.lastname : 'Unknown'}</td>
                <td>${request.current_station ? request.current_station.station_name : 'Unassigned'}</td>
                <td>${request.requested_station ? request.requested_station.station_name : '<span style="color: #ef4444; font-weight: 600;">⚠️ Return to Admin</span>'}</td>
                <td>${createdAt.toLocaleString('en-US', { timeZone: 'Asia/Manila' })}</td>
                <td>
                    <span class="status-badge ${request.status}">
                        ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                    </span>
                </td>
                <td>
                    <button class="action-btn" onclick="viewRequest(${request.request_id})" title="View Details">
                        <svg viewBox="0 0 24 24">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// View request details
function viewRequest(requestId) {
    const request = requests.find(r => r.request_id === requestId);
    if (!request) return;
    
    currentRequest = request;
    const modalBody = document.getElementById('modalBody');
    
    const reviewedAt = request.reviewed_at ? new Date(request.reviewed_at).toLocaleString('en-US', { timeZone: 'Asia/Manila' }) : 'N/A';
    
    modalBody.innerHTML = `
        <div class="detail-item">
            <div class="detail-label">Request ID</div>
            <div class="detail-value">#${String(request.request_id).padStart(5, '0')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Report ID</div>
            <div class="detail-value">#${String(request.report_id).padStart(5, '0')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Requested By</div>
            <div class="detail-value">${request.requested_by ? request.requested_by.firstname + ' ' + request.requested_by.lastname : 'Unknown'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Current Station</div>
            <div class="detail-value">${request.current_station ? request.current_station.station_name : 'Unassigned'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Requested Station</div>
            <div class="detail-value">${request.requested_station ? request.requested_station.station_name : '<span style="color: #ef4444; font-weight: 600;">⚠️ Return to Admin (Unassign)</span>'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Reason</div>
            <div class="detail-value">${request.reason || 'No reason provided'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Status</div>
            <div class="detail-value">
                <span class="status-badge ${request.status}">
                    ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                </span>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Date Submitted</div>
            <div class="detail-value">${new Date(request.created_at).toLocaleString('en-US', { timeZone: 'Asia/Manila' })}</div>
        </div>
        ${request.status !== 'pending' ? `
            <div class="detail-item">
                <div class="detail-label">Reviewed By</div>
                <div class="detail-value">${request.reviewed_by ? request.reviewed_by.firstname + ' ' + request.reviewed_by.lastname : 'N/A'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Reviewed At</div>
                <div class="detail-value">${reviewedAt}</div>
            </div>
        ` : ''}
        
        <div class="actions-section">
            ${request.status === 'pending' && userRole === 'admin' ? `
                <button class="btn btn-success" onclick="reviewRequest('approve')">
                    <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    Approve Request
                </button>
                <button class="btn btn-danger" onclick="reviewRequest('reject')">
                    <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                    Reject Request
                </button>
            ` : ''}
            <button class="btn btn-secondary" onclick="closeModal()">Close</button>
        </div>
    `;
    
    document.getElementById('requestModal').classList.add('active');
}

// Review request (approve/reject)
async function reviewRequest(action) {
    if (!currentRequest) return;
    
    if (!(await confirm(`Are you sure you want to ${action} this reassignment request?`))) {
        return;
    }
    
    try {
        const response = await fetch(`/reassignment-requests/${currentRequest.request_id}/review`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ action })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeModal();
            loadRequests(); // Reload the requests
        } else {
            alert('Failed to review request: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while reviewing the request');
    }
}

// Close modal
function closeModal() {
    document.getElementById('requestModal').classList.remove('active');
    currentRequest = null;
}

// Show error message
function showError(message) {
    const tbody = document.getElementById('requestsTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="no-results">
                <p>${message}</p>
            </td>
        </tr>
    `;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadRequests();
});
</script>
@endsection
