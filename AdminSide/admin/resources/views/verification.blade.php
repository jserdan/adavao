@extends('layouts.app')

@section('title', 'Verification Requests')

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
        font-size: 1.875rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }
    
    .verification-title-section p {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .verification-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .verification-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .verification-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .verification-table th {
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
    
    .verification-table th:hover {
        background: #f3f4f6;
    }
    
    .verification-table th.sortable::after {
        content: '\2195';
        margin-left: 0.5rem;
        opacity: 0.3;
    }
    
    .verification-table th.sorted-asc::after {
        content: '\2191';
        margin-left: 0.5rem;
        opacity: 1;
    }
    
    .verification-table th.sorted-desc::after {
        content: '\2193';
        margin-left: 0.5rem;
        opacity: 1;
    }
    
    .verification-table td {
        padding: 1rem 1.5rem;
        font-size: 0.875rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .verification-table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .verification-table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .verification-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .status-badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    
    .status-badge.pending {
        color: #92400e;
    }
    
    .status-badge.verified {
        color: #065f46;
    }
    
    .status-badge.rejected {
        color: #991b1b;
    }
    
    .status-badge.not_verified {
        color: #374151;
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
    }
    
    .action-btn:hover {
        background: #f9fafb;
        border-color: #3b82f6;
        color: #3b82f6;
    }
    
    .action-btn.danger:hover {
        border-color: #ef4444;
        color: #ef4444;
    }
    
    .action-icon {
        width: 18px;
        height: 18px;
        fill: currentColor;
    }
    
    .user-id {
        font-family: 'Courier New', monospace;
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }
    
    .dropdown {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: #ffffff;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        margin-top: 0.25rem;
    }
    
    .dropdown-content a {
        color: #374151;
        padding: 0.75rem 1rem;
        text-decoration: none;
        display: block;
        font-size: 0.875rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .dropdown-content a:last-child {
        border-bottom: none;
    }
    
    .dropdown-content a:hover {
        background-color: #f9fafb;
    }
    
    .dropdown:hover .dropdown-content {
        display: block;
    }
    
    .dropdown-btn {
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
    }
    
    .dropdown-btn:hover {
        background: #f9fafb;
        border-color: #3b82f6;
        color: #3b82f6;
    }
    
    /* Modal Styles - Centered */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    
    .modal-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        max-width: 90%;
        max-height: 90%;
        overflow-y: auto;
        width: 90%;
        max-width: 800px;
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
        margin: 0;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #9ca3af;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }
    
    .modal-close:hover {
        background-color: #f3f4f6;
        color: #374151;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .verification-details {
        display: grid;
        gap: 1.5rem;
    }
    
    .user-section, .status-section, .documents-section {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.5rem;
    }
    
    .user-section h3, .status-section h3, .documents-section h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        font-size: 1.125rem;
        color: #1f2937;
    }
    
    .user-detail, .status-detail {
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
    }
    
    .user-detail strong, .status-detail strong {
        width: 120px;
        color: #6b7280;
    }
    
    .document-item {
        margin-bottom: 1.5rem;
    }
    
    .document-item:last-child {
        margin-bottom: 0;
    }
    
    .document-preview {
        margin-top: 0.5rem;
        position: relative;
        display: inline-block;
    }
    
    .document-preview img {
        max-width: 100%;
        max-height: 300px;
        border-radius: 4px;
        border: 1px solid #e5e7eb;
        object-fit: contain;
        display: block;
    }
    
    .view-image-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 10px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .view-image-btn:hover {
        background: rgba(0, 0, 0, 0.9);
    }
    
    .image-placeholder {
        width: 100%;
        max-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f3f4f6;
        border: 1px dashed #d1d5db;
        border-radius: 4px;
        padding: 2rem;
        text-align: center;
        color: #6b7280;
    }
    
    .actions-section {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
        margin-top: 1rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }
    
    .btn-success {
        background-color: #10b981;
        color: white;
    }
    
    .btn-success:hover {
        background-color: #059669;
    }
    
    .btn-danger {
        background-color: #ef4444;
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #dc2626;
    }
    
    .btn-secondary {
        background-color: #6b7280;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #4b5563;
    }
    
    /* Lightbox Styles */
    .lightbox-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 2000;
        display: none;
    }
    
    .lightbox-content {
        position: relative;
        max-width: 90%;
        max-height: 90%;
    }
    
    .lightbox-image {
        max-width: 90vw;
        max-height: 90vh;
        object-fit: contain;
        border: 2px solid white;
        border-radius: 4px;
    }
    
    .lightbox-close {
        position: absolute;
        top: -40px;
        right: 0;
        background: none;
        border: none;
        color: white;
        font-size: 30px;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .lightbox-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        font-size: 24px;
        cursor: pointer;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .lightbox-prev {
        left: 10px;
    }
    
    .lightbox-next {
        right: 10px;
    }
    
    .lightbox-counter {
        position: absolute;
        bottom: -40px;
        left: 0;
        color: white;
        font-size: 16px;
    }
    
    @media (max-width: 768px) {
        .verification-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .verification-table-container {
            overflow-x: auto;
        }
        
        .verification-table {
            min-width: 800px;
        }
        
        .modal-content {
            width: 95%;
            margin: 1rem;
        }
        
        .lightbox-image {
            max-width: 95vw;
            max-height: 80vh;
        }
    }
</style>
@endsection

@section('content')
<div class="verification-header">
    <div class="verification-title-section">
        <h1>Pending Verification Requests</h1>
        <p>Review and manage pending user verification requests</p>
    </div>
</div>

<div class="verification-table-container">
    <table class="verification-table" id="verificationTable">
        <thead>
            <tr>
                <th class="sortable" onclick="sortVerificationTable(0)">Verification ID</th>
                <th class="sortable" onclick="sortVerificationTable(1)">User Name</th>
                <th class="sortable" onclick="sortVerificationTable(2)">User ID</th>
                <th class="sortable" onclick="sortVerificationTable(3)">Date of Submission</th>
                <th class="sortable" onclick="sortVerificationTable(4)">Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="verificationRequests">
            <!-- Verification requests will be loaded here -->
        </tbody>
    </table>
    <div id="loading" class="loading">Loading verification requests...</div>
    <div id="noRequests" class="no-results" style="display: none;">
        <p>No verification requests found</p>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal-overlay" id="verificationModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Verification Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Verification details will be loaded here -->
        </div>
    </div>
</div>

</div>

<!-- Rejection Reason Modal -->
<div class="modal-overlay" id="rejectionModal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title" style="color: #ef4444;">Reject Verification</h2>
            <button class="modal-close" onclick="closeRejectionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 1rem; color: #4b5563;">Please provide a reason for rejecting this verification request. This will be sent to the user.</p>
            
            <div style="margin-bottom: 1.5rem;">
                <label for="rejectionReason" style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Reason <span style="color: #ef4444;">*</span></label>
                <textarea id="rejectionReason" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; resize: vertical;" placeholder="e.g., ID is blurry, Document expired, Name does not match..."></textarea>
                <p id="rejectionError" style="color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem; display: none;">Rejection reason is required.</p>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button class="btn btn-secondary" onclick="closeRejectionModal()">Cancel</button>
                <button class="btn btn-danger" onclick="submitRejection()">Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>
<div class="lightbox-overlay" id="lightboxModal">
    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
    <div class="lightbox-content">
        <img class="lightbox-image" id="lightboxImage" src="" alt="Enlarged document">
        <button class="lightbox-nav lightbox-prev" onclick="changeImage(-1)">&#10094;</button>
        <button class="lightbox-nav lightbox-next" onclick="changeImage(1)">&#10095;</button>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let verifications = [];
let currentImages = [];
let currentImageIndex = 0;
let verificationSortDirections = {};
let activeTab = 'pending'; // 'pending' or 'history'
// Variables for Rejection Modal
let pendingRejectionVerificationId = null;
let pendingRejectionUserId = null;

// Tab switching function
function switchVerificationTab(tabName) {
    activeTab = tabName;
    
    // Update tab button styles
    document.getElementById('tabPending').classList.toggle('active', tabName === 'pending');
    document.getElementById('tabHistory').classList.toggle('active', tabName === 'history');
    
    // Filter and display based on tab
    if (tabName === 'pending') {
        const pending = verifications.filter(v => v.status.toLowerCase() === 'pending');
        displayVerificationRequests(pending);
    } else {
        const history = verifications.filter(v => v.status.toLowerCase() === 'verified' || v.status.toLowerCase() === 'rejected');
        displayVerificationRequests(history);
    }
}

// Update tab counts
function updateTabCounts() {
    const pending = verifications.filter(v => v.status.toLowerCase() === 'pending');
    const history = verifications.filter(v => v.status.toLowerCase() === 'verified' || v.status.toLowerCase() === 'rejected');
    
    document.getElementById('pendingCount').textContent = pending.length > 0 ? pending.length : '';
    document.getElementById('historyCount').textContent = history.length > 0 ? history.length : '';
}

function sortVerificationTable(columnIndex) {
    const table = document.getElementById('verificationTable');
    const tbody = document.getElementById('verificationRequests');
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    const headers = table.getElementsByTagName('th');
    
    // Toggle sort direction
    if (!verificationSortDirections[columnIndex]) {
        verificationSortDirections[columnIndex] = 'asc';
    } else if (verificationSortDirections[columnIndex] === 'asc') {
        verificationSortDirections[columnIndex] = 'desc';
    } else {
        verificationSortDirections[columnIndex] = 'asc';
    }
    
    const direction = verificationSortDirections[columnIndex];
    
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
        
        // Handle numeric values (Verification ID, User ID)
        if (columnIndex === 0 || columnIndex === 2) {
            aValue = parseInt(aValue) || 0;
            bValue = parseInt(bValue) || 0;
        }
        // Handle dates (column 3)
        else if (columnIndex === 3) {
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

// Function to convert blob URLs or relative paths to proper URLs
// Includes userId for authorization with Node backend
function getImageUrl(path) {
    // If it's already a full URL, return as is
    if (path.startsWith('http://') || path.startsWith('https://')) {
        // If it's a blob URL, it's not accessible from the server, so we can't display it
        if (path.startsWith('blob:')) {
            return null;
        }
        // Add userId for authorization if it's a Node backend URL
        const nodeBackendUrl = '{{ config("app.node_backend_url", "http://localhost:3000") }}';
        if (path.startsWith(nodeBackendUrl)) {
            const separator = path.includes('?') ? '&' : '?';
            return path + separator + 'userId={{ auth()->id() }}';
        }
        return path;
    }
    
    // Use configured Node backend URL from Laravel config
    const nodeBackendUrl = '{{ config("app.node_backend_url", "http://localhost:3000") }}';
    
    // Add userId for authorization (required by Node server)
    const authParam = '?userId={{ auth()->id() }}';
    
    // If it's a relative path, prepend the backend server URL
    if (path.startsWith('/')) {
        return nodeBackendUrl + path + authParam;
    }
    
    // If it's just a filename, assume it's in the verifications directory
    return nodeBackendUrl + '/verifications/' + path + authParam;
}

// Load all verification requests
async function loadVerificationRequests() {
    try {
        console.log('🔍 Loading verification requests...');
        const response = await fetch('/api/verifications/all', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        });
        
        console.log('📊 Response received:', response);
        console.log('📊 Response status:', response.status);
        console.log('📊 Response headers:', [...response.headers.entries()]);
        
        if (!response.ok) {
            throw new Error('Failed to load verification requests: ' + response.status + ' ' + response.statusText);
        }
        
        const data = await response.json();
        console.log('✅ Verification data received:', data);
        
        if (!data.success) {
            throw new Error('API returned failure: ' + (data.message || 'Unknown error'));
        }
        
        verifications = data.data || [];
        console.log('📋 Number of verifications:', verifications.length);
        
        if (verifications.length > 0) {
            console.log('📋 First verification:', verifications[0]);
        }
        
        // Filter to only show pending requests
        const pendingRequests = verifications.filter(v => v.status.toLowerCase() === 'pending');
        displayVerificationRequests(pendingRequests);
    } catch (error) {
        console.error('💥 Error loading verification requests:', error);
        document.getElementById('loading').textContent = 'Error loading verification requests: ' + error.message;
    }
}

// Load pending verification requests
async function loadPendingVerifications() {
    try {
        console.log('Loading pending verification requests...'); // Debug log
        const response = await fetch('/api/verifications/pending', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin' // Add this to send cookies with the request
        });
        
        console.log('Pending response received:', response); // Debug log
        
        if (!response.ok) {
            throw new Error('Failed to load pending verification requests: ' + response.status + ' ' + response.statusText);
        }
        
        const data = await response.json();
        console.log('Pending verification data received:', data); // Debug log
        pendingVerifications = data.data || [];
        
        console.log('Number of pending verifications:', pendingVerifications.length); // Debug log
        displayPendingVerifications(pendingVerifications);
    } catch (error) {
        console.error('Error loading pending verification requests:', error);
        document.getElementById('pendingLoading').textContent = 'Error loading pending verification requests: ' + error.message;
    }
}

// Display verification requests in table
function displayVerificationRequests(requests) {
    console.log('🎨 Displaying verification requests:', requests);
    
    const tbody = document.getElementById('verificationRequests');
    const loading = document.getElementById('loading');
    const noRequests = document.getElementById('noRequests');
    
    if (!tbody) {
        console.error('❌ Could not find verificationRequests tbody element');
        return;
    }
    
    if (!loading) {
        console.error('❌ Could not find loading element');
        return;
    }
    
    if (!noRequests) {
        console.error('❌ Could not find noRequests element');
        return;
    }
    
    loading.style.display = 'none';
    
    if (!requests || requests.length === 0) {
        console.log('⚠️ No requests to display');
        noRequests.style.display = 'block';
        tbody.innerHTML = '';
        return;
    }
    
    console.log('✅ Displaying', requests.length, 'requests');
    noRequests.style.display = 'none';
    
    // Check if requests have the required data
    requests.forEach((request, index) => {
        console.log(`📋 Request ${index}:`, request);
        if (!request.user) {
            console.error(`❌ Request ${index} is missing user data:`, request);
        }
        if (!request.verification_id) {
            console.error(`❌ Request ${index} is missing verification_id:`, request);
        }
    });
    
    try {
        tbody.innerHTML = requests.map(request => {
            // Check if request has required data
            if (!request.user) {
                console.error('❌ Request missing user data:', request);
                return ''; // Skip this request
            }
            
            return `
            <tr>
                <td>${escapeHtml(request.verification_id)}</td>
                <td>${escapeHtml(request.user.firstname)} ${escapeHtml(request.user.lastname)}</td>
                <td class="user-id">${request.user_id}</td>
                <td>${request.created_at ? new Date(request.created_at).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    timeZone: 'Asia/Manila'
                }) : 'N/A'}</td>
                <td>
                    <span class="status-badge ${request.status.toLowerCase().replace(' ', '_')}">
                        ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                    </span>
                </td>
                <td>
                    <button class="action-btn" onclick="viewVerification(${request.verification_id})" title="View Details">
                        <svg class="action-icon" viewBox="0 0 24 24" width="18" height="18">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </button>
                </td>
            </tr>
            `;
        }).join('');
        
        console.log('✅ Finished displaying verification requests');
    } catch (error) {
        console.error('💥 Error rendering verification requests:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red; padding: 2rem;">Error displaying verification requests. Check console for details.</td></tr>';
    }
}

// View verification details
async function viewVerification(verificationId) {
    const verification = verifications.find(v => v.verification_id === verificationId);
    if (!verification) return;
    
    const modalBody = document.getElementById('modalBody');
    
    // Format documents section
    let documentsHtml = '<div class="documents-section">';
    
    // Debug: Log all image paths
    console.log('🖼️ Verification document paths:', {
        id_picture: verification.id_picture,
        id_selfie: verification.id_selfie,
        billing_document: verification.billing_document
    });
    
    // ID Picture
    if (verification.id_picture) {
        const imageUrl = getImageUrl(verification.id_picture);
        console.log('📸 ID Picture URL:', imageUrl);
        if (imageUrl) {
            documentsHtml += `
                <div class="document-item">
                    <h4>ID Picture</h4>
                    <div class="document-preview">
                        <img src="${imageUrl}" alt="ID Picture" onerror="this.style.display='none'; this.parentElement.innerHTML = '<div class=\\'image-placeholder\\'>Image not found or unavailable</div>'">
                        <button class="view-image-btn" onclick="openLightbox(['${imageUrl}'], 0)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            View
                        </button>
                    </div>
                </div>
            `;
        } else {
            documentsHtml += `
                <div class="document-item">
                    <h4>ID Picture</h4>
                    <div class="image-placeholder">
                        Image not available (blob URL)
                    </div>
                </div>
            `;
        }
    }
    
    // Selfie with ID
    if (verification.id_selfie) {
        const imageUrl = getImageUrl(verification.id_selfie);
        if (imageUrl) {
            documentsHtml += `
                <div class="document-item">
                    <h4>Selfie with ID</h4>
                    <div class="document-preview">
                        <img src="${imageUrl}" alt="Selfie with ID" onerror="this.style.display='none'; this.parentElement.innerHTML = '<div class=\\'image-placeholder\\'>Image not found or unavailable</div>'">
                        <button class="view-image-btn" onclick="openLightbox(['${imageUrl}'], 0)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            View
                        </button>
                    </div>
                </div>
            `;
        } else {
            documentsHtml += `
                <div class="document-item">
                    <h4>Selfie with ID</h4>
                    <div class="image-placeholder">
                        Image not available (blob URL)
                    </div>
                </div>
            `;
        }
    }
    
    // Billing Document
    if (verification.billing_document) {
        const imageUrl = getImageUrl(verification.billing_document);
        if (imageUrl) {
            documentsHtml += `
                <div class="document-item">
                    <h4>Billing Document</h4>
                    <div class="document-preview">
                        <img src="${imageUrl}" alt="Billing Document" onerror="this.style.display='none'; this.parentElement.innerHTML = '<div class=\\'image-placeholder\\'>Image not found or unavailable</div>'">
                        <button class="view-image-btn" onclick="openLightbox(['${imageUrl}'], 0)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            View
                        </button>
                    </div>
                </div>
            `;
        } else {
            documentsHtml += `
                <div class="document-item">
                    <h4>Billing Document</h4>
                    <div class="image-placeholder">
                        Image not available (blob URL)
                    </div>
                </div>
            `;
        }
    }
    
    if (!verification.id_picture && !verification.id_selfie && !verification.billing_document) {
        documentsHtml += '<p>No documents uploaded</p>';
    }
    
    documentsHtml += '</div>';
    
    modalBody.innerHTML = `
        <div class="verification-details">
            <div class="user-section">
                <h3>User Information</h3>
                <div class="user-detail">
                    <strong>Name:</strong> ${escapeHtml(verification.user.firstname)} ${escapeHtml(verification.user.lastname)}
                </div>
                <div class="user-detail">
                    <strong>Email:</strong> ${escapeHtml(verification.user.email)}
                </div>
                <div class="user-detail">
                    <strong>User ID:</strong> ${verification.user_id}
                </div>
            </div>
            
            <div class="status-section">
                <h3>Verification Status</h3>
                <div class="status-detail">
                    <strong>Verification ID:</strong> ${verification.verification_id}
                </div>
                <div class="status-detail">
                    <strong>Status:</strong> 
                    <span class="status-badge ${verification.status.toLowerCase().replace(' ', '_')}">
                        ${verification.status.charAt(0).toUpperCase() + verification.status.slice(1)}
                    </span>
                </div>
                ${(verification.status || '').toLowerCase() === 'rejected' && verification.rejection_reason ? `
                <div class="status-detail">
                    <strong>Rejection Reason:</strong> ${escapeHtml(verification.rejection_reason)}
                </div>
                ` : ''}
                <div class="status-detail">
                    <strong>Submitted:</strong> ${verification.created_at ? new Date(verification.created_at).toLocaleString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        timeZone: 'Asia/Manila'
                    }) : 'N/A'}
                </div>
                ${verification.expiration ? `
                <div class="status-detail">
                    <strong>Expiration:</strong> ${new Date(verification.expiration).toLocaleString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        timeZone: 'Asia/Manila'
                    })}
                </div>
                ` : ''}
            </div>
            
            ${documentsHtml}
            
            <div class="actions-section">
                ${verification.status !== 'verified' ? `
                    <button class="btn btn-success" onclick="approveVerification(${verificationId}, ${verification.user_id})">
                        Approve Verification
                    </button>
                ` : ''}
                ${verification.status === 'pending' ? `
                    <button class="btn btn-danger" onclick="rejectVerification(${verificationId}, ${verification.user_id})">
                        Reject Verification
                    </button>
                ` : ''}
                <button class="btn btn-secondary" onclick="closeModal()">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('verificationModal').style.display = 'flex';
}

// Open lightbox with image
function openLightbox(images, index) {
    currentImages = images;
    currentImageIndex = index;
    
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = images[index];
    
    updateLightboxCounter();
    document.getElementById('lightboxModal').style.display = 'flex';
    
    // Prevent body scrolling when lightbox is open
    document.body.style.overflow = 'hidden';
}

// Close lightbox
function closeLightbox() {
    document.getElementById('lightboxModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Change image in lightbox
function changeImage(direction) {
    currentImageIndex += direction;
    
    if (currentImageIndex < 0) {
        currentImageIndex = currentImages.length - 1;
    } else if (currentImageIndex >= currentImages.length) {
        currentImageIndex = 0;
    }
    
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = currentImages[currentImageIndex];
    
    updateLightboxCounter();
}

// Update lightbox counter
function updateLightboxCounter() {
    const counter = document.getElementById('lightboxCounter');
    counter.textContent = `${currentImageIndex + 1} of ${currentImages.length}`;
}

async function approveVerification(verificationId, userId) {
    if (!(await confirm('Are you sure you want to approve this verification request?'))) {
        return;
    }
    
    try {
        const response = await fetch('/api/verification/approve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                verificationId: verificationId,
                userId: userId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Verification approved successfully');
            closeModal();
            loadVerificationRequests(); // Refresh the list
        } else {
            alert('Error: ' + (data.message || 'Failed to approve verification'));
        }
    } catch (error) {
        console.error('Error approving verification:', error);
        alert('Error approving verification');
    }
}

async function rejectVerification(verificationId, userId) {
    if (!(await confirm('Are you sure you want to reject this verification request?'))) {
        return;
    }
    
    // Store variables
    pendingRejectionVerificationId = verificationId;
    pendingRejectionUserId = userId;
    
    // Reset modal
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectionError').style.display = 'none';
    
    // Show Rejection Modal and Hide Details Modal
    document.getElementById('verificationModal').style.display = 'none';
    document.getElementById('rejectionModal').style.display = 'flex';
    document.getElementById('rejectionReason').focus();
}

// Close Rejection Modal
function closeRejectionModal() {
    document.getElementById('rejectionModal').style.display = 'none';
    // Re-open verification modal so admin isn't lost
    document.getElementById('verificationModal').style.display = 'flex';
    
    // Clear variables
    pendingRejectionVerificationId = null;
    pendingRejectionUserId = null;
}

// Submit Rejection
async function submitRejection() {
    const reasonInput = document.getElementById('rejectionReason');
    const reason = reasonInput.value.trim();
    const errorMsg = document.getElementById('rejectionError');
    
    if (!reason) {
        errorMsg.style.display = 'block';
        reasonInput.focus();
        return;
    }
    
    errorMsg.style.display = 'none';
    
    // Disable button to prevent double submit
    const confirmBtn = document.querySelector('#rejectionModal .btn-danger');
    const originalText = confirmBtn.innerText;
    confirmBtn.disabled = true;
    confirmBtn.innerText = 'Rejecting...';
    
    try {
        const response = await fetch('/api/verification/reject', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                verificationId: pendingRejectionVerificationId,
                userId: pendingRejectionUserId,
                rejection_reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Verification rejected successfully');
            document.getElementById('rejectionModal').style.display = 'none';
            // Also ensure verification modal is closed if it wasn't already
            document.getElementById('verificationModal').style.display = 'none';
            
            // Clear variables
            pendingRejectionVerificationId = null;
            pendingRejectionUserId = null;
            
            loadVerificationRequests(); // Refresh the list
        } else {
            alert('Error: ' + (data.message || 'Failed to reject verification'));
        }
    } catch (error) {
        console.error('Error rejecting verification:', error);
        alert('Error rejecting verification');
    } finally {
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerText = originalText;
        }
    }
}

// Close modal
function closeModal() {
    document.getElementById('verificationModal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('verificationModal');
    const lightbox = document.getElementById('lightboxModal');
    
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    lightbox.addEventListener('click', function(event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });
    
    // Keyboard navigation for lightbox
    document.addEventListener('keydown', function(event) {
        if (document.getElementById('lightboxModal').style.display === 'flex') {
            if (event.key === 'Escape') {
                closeLightbox();
            } else if (event.key === 'ArrowLeft') {
                changeImage(-1);
            } else if (event.key === 'ArrowRight') {
                changeImage(1);
            }
        }
    });
});

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    text = String(text); // Convert to string to handle numbers
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Load verification requests when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing verification page...');
    
    // Load verification requests
    loadVerificationRequests();
    
    // Load pending verifications
    loadPendingVerifications();
    
    // Set up auto-refresh every 10 seconds
    setInterval(() => {
        console.log('Auto-refreshing verification data...');
        loadVerificationRequests();
        loadPendingVerifications();
    }, 10000);
});
</script>
@endsection