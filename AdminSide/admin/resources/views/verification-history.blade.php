@extends('layouts.app')

@section('title', 'Verification History')

@section('styles')
<style>
    .verification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .verification-title-section h1 {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .verification-title-section p {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .verification-table-container {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        padding: 0;
    }
    
    .verification-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .verification-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .verification-table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-badge.verified {
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
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #6b7280;
    }
    
    .action-btn:hover {
        background: #f9fafb;
        border-color: #3b82f6;
        color: #3b82f6;
    }
    
    .action-icon {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        stroke-width: 2;
        fill: none;
    }
    
    .loading {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }
    
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #3b82f6;
        text-decoration: none;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    
    .back-link:hover {
        text-decoration: underline;
    }
</style>
@endsection

@section('content')
<a href="{{ route('verification') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
    </svg>
    Back to Pending Requests
</a>

<div class="verification-header">
    <div class="verification-title-section">
        <h1>Verification History</h1>
        <p>View verified and rejected verification requests</p>
    </div>
</div>

<div class="verification-table-container">
    <table class="verification-table" id="historyTable">
        <thead>
            <tr>
                <th>Verification ID</th>
                <th>User Name</th>
                <th>User ID</th>
                <th>Date of Submission</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="historyRequests">
            <!-- History requests will be loaded here -->
        </tbody>
    </table>
    <div id="loading" class="loading">Loading verification history...</div>
    <div id="noResults" class="no-results" style="display: none;">
        <p>No verification history found</p>
    </div>
</div>
@endsection

@section('scripts')
<script>
let historyData = [];

async function loadHistory() {
    try {
        const response = await fetch('/api/verifications/all', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to load verification history');
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Unknown error');
        }
        
        // Filter only verified and rejected
        historyData = (data.data || []).filter(v => 
            v.status.toLowerCase() === 'verified' || v.status.toLowerCase() === 'rejected'
        );
        
        displayHistory(historyData);
    } catch (error) {
        console.error('Error loading history:', error);
        document.getElementById('loading').textContent = 'Error loading history: ' + error.message;
    }
}

function displayHistory(requests) {
    const tbody = document.getElementById('historyRequests');
    const loading = document.getElementById('loading');
    const noResults = document.getElementById('noResults');
    
    loading.style.display = 'none';
    
    if (!requests || requests.length === 0) {
        noResults.style.display = 'block';
        tbody.innerHTML = '';
        return;
    }
    
    noResults.style.display = 'none';
    
    tbody.innerHTML = requests.map(request => {
        if (!request.user) return '';
        
        return `
        <tr>
            <td>${escapeHtml(request.verification_id)}</td>
            <td>${escapeHtml(request.user.firstname)} ${escapeHtml(request.user.lastname)}</td>
            <td>${request.user_id}</td>
            <td>${request.created_at ? new Date(request.created_at).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                timeZone: 'Asia/Manila'
            }) : 'N/A'}</td>
            <td>
                <span class="status-badge ${request.status.toLowerCase()}">
                    ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                </span>
            </td>
            <td>
                <button class="action-btn" onclick="viewDetails(${request.verification_id})" title="View Details">
                    <svg class="action-icon" viewBox="0 0 24 24" width="18" height="18">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                </button>
            </td>
        </tr>
        `;
    }).join('');
}

function viewDetails(verificationId) {
    // Redirect to main verification page with a focus on this ID
    window.location.href = '/verification?highlight=' + verificationId;
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    text = String(text);
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Load history when page loads
document.addEventListener('DOMContentLoaded', loadHistory);

// Auto-refresh every 10 seconds
setInterval(() => {
    console.log('🔄 Auto-refreshing verification history...');
    loadHistory();
}, 10000);

// Instant update on Socket.io push
window.addEventListener('adminLiveUpdate', (e) => {
    e.preventDefault();
    loadHistory();
});
</script>
@endsection
