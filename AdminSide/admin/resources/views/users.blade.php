@extends('layouts.app')

@section('title', 'Users')

@section('styles')
<style>
    .users-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .users-title-section h1 {
        font-size: 1.875rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }
    
    .users-title-section p {
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
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        fill: #9ca3af;
    }
    
    .users-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .users-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .users-table th {
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
    
    .users-table th:hover {
        background: #f3f4f6;
    }
    
    .users-table th.sortable::after {
        content: '\2195';
        margin-left: 0.5rem;
        opacity: 0.3;
    }
    
    .users-table th.sorted-asc::after {
        content: '\2191';
        margin-left: 0.5rem;
        opacity: 1;
    }
    
    .users-table th.sorted-desc::after {
        content: '\2193';
        margin-left: 0.5rem;
        opacity: 1;
    }
    
    .users-table td {
        padding: 1rem 1.5rem;
        font-size: 0.875rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .users-table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .users-table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .users-table tbody tr:last-child td {
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
    
    .status-badge.flagged {
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
        text-decoration: none;
        color: #6b7280;
        margin-right: 0.25rem;
    }

    .action-btn.disabled {
        opacity: 0.55;
        cursor: not-allowed;
        border-color: #e5e7eb;
        color: #9ca3af;
        background: #ffffff;
    }

    /* Flag badge (small red circle with count) */
    .flag-user-btn {
        position: relative;
    }

    .flag-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 9999px;
        background: #ef4444;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        box-shadow: 0 1px 2px rgba(0,0,0,0.12);
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
    
    .action-btn.success:hover {
        border-color: #10b981;
        color: #10b981;
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
    
    .action-buttons-group {
        display: flex;
        gap: 0.25rem;
        align-items: center;
        position: relative;
        z-index: 10;
    }
    
    .role-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .role-dropdown-content {
        display: none;
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        background-color: #ffffff;
        min-width: 140px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1000;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }
    
    .role-dropdown-content.active {
        display: block;
    }
    
    .role-option {
        color: #374151;
        padding: 0.75rem 1rem;
        text-decoration: none;
        display: block;
        font-size: 0.875rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .role-option:last-child {
        border-bottom: none;
    }
    
    .role-option:hover {
        background-color: #f9fafb;
        color: #3b82f6;
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
    
    .change-role-btn {
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
    
    .change-role-btn:hover {
        background: #f9fafb;
        border-color: #8b5cf6;
        color: #8b5cf6;
    }
    
    .role-option-card {
        padding: 1rem 1.25rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .role-option-card:hover {
        border-color: #3b82f6;
        background-color: #f0f9ff;
    }
    
    .role-option-card.selected {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }
    
    .role-option-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .role-option-name {
        font-weight: 600;
        color: #1f2937;
        font-size: 1rem;
    }
    
    .role-option-desc {
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .role-option-card.selected .role-option-name {
        color: #1e40af;
    }
    
    .role-check-circle {
        width: 24px;
        height: 24px;
        border: 2px solid #d1d5db;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .role-option-card.selected .role-check-circle {
        background-color: #3b82f6;
        border-color: #3b82f6;
    }
    
    .role-check-icon {
        width: 14px;
        height: 14px;
        stroke: white;
        fill: none;
        stroke-width: 3;
        opacity: 0;
    }
    
    .role-option-card.selected .role-check-icon {
        opacity: 1;
    }
    
    .address-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-overlay.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background-color: #fefefe;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow: visible;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
    }
    
    .modal-close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6b7280;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-close-btn:hover {
        color: #1f2937;
    }
    
    .station-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1.5px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 10;
    }
    
    .station-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .modal-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }
    
    .btn-cancel {
        padding: 0.75rem 1.5rem;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        transition: all 0.2s ease;
    }
    
    .btn-cancel:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }
    
    .form-group label .required {
        color: #ef4444;
        margin-left: 2px;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1.5px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.875rem;
        color: #1f2937;
        background-color: #fff;
        transition: all 0.2s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .form-control::placeholder {
        color: #9ca3af;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 2rem;
        line-height: 1;
        cursor: pointer;
        color: #6b7280;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    
    .modal-close:hover {
        background-color: #f3f4f6;
        color: #1f2937;
    }
    
    .btn-assign {
        padding: 0.75rem 1.5rem;
        border: none;
        background: #3b82f6;
        color: white;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-assign:hover {
        background: #2563eb;
    }
    
    .btn-assign:disabled {
        background: #d1d5db;
        cursor: not-allowed;
    }
    
    @media (max-width: 768px) {
        .users-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .search-box {
            width: 100%;
        }
        
        .users-table-container {
            overflow-x: auto;
        }
        
        .users-table {
            min-width: 800px;
        }
    }
</style>
@endsection

@section('content')
<div class="users-header">
    <div class="users-title-section">
        <h1>Users</h1>
        <p>Manage user accounts and permissions</p>
    </div>
    
    <div class="search-box">
        <svg class="search-icon" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
        </svg>
        <input 
            type="text" 
            class="search-input" 
            placeholder="Search users..." 
            id="searchInput"
            onkeyup="searchUsers()"
        >
    </div>
</div>

<div class="users-table-container">
    <table class="users-table" id="usersTable">
        <thead>
            <tr>
                <th class="sortable" onclick="sortUsersTable(0)">User ID</th>
                <th class="sortable" onclick="sortUsersTable(1)">Type</th>
                <th class="sortable" onclick="sortUsersTable(2)">Name</th>
                <th class="sortable" onclick="sortUsersTable(3)">Email</th>
                <th class="sortable" onclick="sortUsersTable(4)">Address</th>
                <th class="sortable" onclick="sortUsersTable(5)">Date of Registration</th>
                <th class="sortable" onclick="sortUsersTable(6)">Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td class="user-id">{{ str_pad($user->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td>{{ ucfirst($user->role) }}</td>
                <td>{{ $user->firstname }} {{ $user->lastname }}</td>
                <td>{{ $user->email }}</td>
                <td class="address-cell">{{ $user->address ?? 'N/A' }}</td>
                <td>{{ $user->created_at->timezone('Asia/Manila')->format('m/d/Y') }}</td>
                <td>
                    @if(($user->total_flags ?? 0) > 0 || ($user->restriction_level ?? 'none') !== 'none')
                        <span class="status-badge flagged">Flagged</span>
                    @else
                        <span class="status-badge {{ $user->is_verified ? 'verified' : 'pending' }}">
                            {{ $user->is_verified ? 'Verified' : 'Pending' }}
                        </span>
                    @endif
                </td>
                <td>
                    <div class="action-buttons-group">
                        <?php $hasFlags = ($user->total_flags ?? 0) > 0 || ($user->restriction_level ?? 'none') !== 'none'; ?>
                        <button class="action-btn danger flag-user-btn" data-user-id="{{ $user->id }}" data-total-flags="{{ $user->total_flags ?? 0 }}" data-restriction-level="{{ $user->restriction_level ?? 'none' }}" title="Flag User">
                            <svg class="action-icon" viewBox="0 0 24 24">
                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                                <line x1="4" y1="22" x2="4" y2="15"/>
                            </svg>
                            @if(($user->total_flags ?? 0) > 0)
                                <span class="flag-badge">{{ $user->total_flags }}</span>
                            @endif
                        </button>
                        <!-- Change Role button removed as per separation of user_public/admin -->
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="no-results">
                    <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.3;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <p>No users found</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Change Role Modal -->
<div id="changeRoleModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Change User Role</h2>
            <button class="modal-close-btn" id="roleModalCloseBtn">&times;</button>
        </div>
        
        <p style="margin-bottom: 1.5rem; color: #6b7280; font-size: 0.875rem;">Select the new role for this user:</p>
        
        <div id="roleOptions">
            <div class="role-option-card" data-role="user">
                <div class="role-option-info">
                    <span class="role-option-name">User</span>
                    <span class="role-option-desc">Can submit and view own reports</span>
                </div>
                <div class="role-check-circle">
                    <svg class="role-check-icon" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
            </div>
            
            <div class="role-option-card" data-role="police">
                <div class="role-option-info">
                    <span class="role-option-name">Police</span>
                    <span class="role-option-desc">Can manage reports in assigned station</span>
                </div>
                <div class="role-check-circle">
                    <svg class="role-check-icon" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
            </div>
            
            <div class="role-option-card" data-role="admin">
                <div class="role-option-info">
                    <span class="role-option-name">Admin</span>
                    <span class="role-option-desc">Full system access and management</span>
                </div>
                <div class="role-check-circle">
                    <svg class="role-check-icon" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="modal-buttons">
            <button class="btn-cancel" id="roleCancelBtn">Cancel</button>
            <button class="btn-assign" id="roleChangeBtn">Change Role</button>
        </div>
    </div>
</div>

<!-- Flag User Modal -->
<div class="modal-overlay" id="flagUserModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title">🚩 Flag User for Violation</h2>
            <button class="modal-close" onclick="closeFlagModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="violationType">
                    Violation Type <span class="required">*</span>
                </label>
                <select id="violationType" class="form-control" required>
                    <option value="">Select violation type...</option>
                    <option value="false_report">📝 False Report</option>
                    <option value="prank_spam">🗑️ Prank/Spam Report</option>
                    <option value="harassment">⚠️ Harassment</option>
                    <option value="offensive_content">🚫 Offensive Content</option>
                    <option value="impersonation">👤 Impersonation</option>
                    <option value="multiple_accounts">👥 Multiple Accounts</option>
                    <option value="system_abuse">⚙️ System Abuse</option>
                    <option value="inappropriate_media">📷 Inappropriate Media</option>
                    <option value="misleading_info">❌ Misleading Information</option>
                    <option value="other">🔹 Other</option>
                </select>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label for="flagDuration">Restriction Duration <span class="required">*</span></label>
                <select id="flagDuration" class="form-control" required>
                    <option value="1">1 Day</option>
                    <option value="3">3 Days</option>
                    <option value="7">7 Days</option>
                    <option value="30">30 Days</option>
                </select>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label for="flagReason">Reason (Optional)</label>
                <textarea id="flagReason" class="form-control" rows="3" placeholder="Provide additional details about the violation..."></textarea>
            </div>
                <div style="padding: 12px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px; margin-top: 15px;">
                <p style="margin: 0; font-size: 0.875rem; color: #92400e;">
                    <strong>⚠️ Auto-Restrictions:</strong><br>
                    • 1 flag = Warning (automatic restriction applied)<br>
                    • Repeated flags may escalate the restriction level
                </p>
            </div>
        </div>
        <div class="modal-buttons">
            <button class="btn-cancel" onclick="closeFlagModal()">Cancel</button>
            <button class="btn-assign" onclick="submitFlag()">Flag User</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let selectedRole = null;
let currentUserId = null;
let userToFlag = null;

function openFlagModal(userId) {
    userToFlag = userId;
    document.getElementById('violationType').value = '';
    document.getElementById('flagReason').value = '';
    if(document.getElementById('flagDuration')) document.getElementById('flagDuration').value = '1';
    document.getElementById('flagUserModal').classList.add('active');
}

function closeFlagModal() {
    document.getElementById('flagUserModal').classList.remove('active');
    userToFlag = null;
}

function submitFlag() {
    const violationType = document.getElementById('violationType').value;
    const duration = document.getElementById('flagDuration').value;
    const reason = document.getElementById('flagReason').value;
    
    if (!violationType) {
        alert('Please select a violation type');
        return;
    }
    
    console.log('Flagging user ' + userToFlag + ' with type: ' + violationType);
    
    fetch('/users/' + userToFlag + '/flag', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            violation_type: violationType,
            duration_days: duration,
            reason: reason
        })
    })
    .then(response => {
        console.log('Flag response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Flag response data:', data);
        if (data.success) {
            alert(data.message + (data.data && data.data.restriction_applied ? '\\nRestriction: ' + data.data.restriction_applied : ''));
            closeFlagModal();
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error flagging user:', error);
        alert('An error occurred while flagging the user: ' + error.message);
    });
}

function unflagUser(userId) {
    
    console.log('Unflagging user ' + userId);
    
    fetch('/users/' + userId + '/unflag', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        console.log('Unflag response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Unflag response data:', data);
        if (data.success) {
            alert(data.message);
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error unflagging user:', error);
        alert('An error occurred while unflagging the user: ' + error.message);
    });
}

function openRoleModal(userId, currentRole) {
    currentUserId = userId;
    selectedRole = currentRole;
    
    const modal = document.getElementById('changeRoleModal');
    modal.classList.add('active');
    
    // Highlight current role
    document.querySelectorAll('.role-option-card').forEach(card => {
        const role = card.getAttribute('data-role');
        if (role === currentRole) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });
}

function closeRoleModal() {
    document.getElementById('changeRoleModal').classList.remove('active');
    selectedRole = null;
    currentUserId = null;
}

function changeUserRole() {
    if (!selectedRole || !currentUserId) {
        customAlert('Please select a role', 'error');
        return;
    }
    
    customConfirm('Are you sure you want to change this user\'s role to "' + selectedRole + '"?', 'Confirm Role Change')
        .then(confirmed => {
            if (!confirmed) return;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) {
                customAlert('Security token not found. Please refresh the page and try again.', 'error');
                return;
            }
            
            const btn = document.getElementById('roleChangeBtn');
            btn.disabled = true;
            btn.textContent = 'Changing...';
            
            showLoading('Updating user role...');
            
            fetch('/users/' + currentUserId + '/change-role', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ role: selectedRole })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customAlert('User role has been changed to "' + selectedRole + '" successfully', 'success')
                        .then(() => {
                            closeRoleModal();
                            location.reload();
                        });
                } else {
                    const errorMsg = data.message || 'Failed to change role';
                    customAlert('Error: ' + errorMsg, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                customAlert('An error occurred while changing the user role: ' + error.message, 'error');
            })
            .finally(() => {
                hideLoading();
                btn.disabled = false;
                btn.textContent = 'Change Role';
            });
        });
}

function searchUsers() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('usersTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let txtValue = tr[i].textContent || tr[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}

let usersSortDirections = {};

function sortUsersTable(columnIndex) {
    const table = document.getElementById('usersTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    const headers = table.getElementsByTagName('th');
    
    // Toggle sort direction
    if (!usersSortDirections[columnIndex]) {
        usersSortDirections[columnIndex] = 'asc';
    } else if (usersSortDirections[columnIndex] === 'asc') {
        usersSortDirections[columnIndex] = 'desc';
    } else {
        usersSortDirections[columnIndex] = 'asc';
    }
    
    const direction = usersSortDirections[columnIndex];
    
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
        
        // Handle numeric values (User ID)
        if (columnIndex === 0) {
            aValue = parseInt(aValue) || 0;
            bValue = parseInt(bValue) || 0;
        }
        // Handle dates (column 5)
        else if (columnIndex === 5) {
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

function flagUser(userId) {
    openFlagModal(userId);
}

// Add event listeners after the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, customAlert exists?', typeof customAlert);
    console.log('DOM loaded, customConfirm exists?', typeof customConfirm);
    
    // Flag user button click handler (toggle: flag -> open modal, flagged -> unflag)
    // Use event delegation on document to handle dynamically generated buttons
    document.addEventListener('click', function(e) {
        const flagBtn = e.target.closest('.flag-user-btn');
        if (!flagBtn) return;
        
        e.preventDefault();
        const userId = flagBtn.getAttribute('data-user-id');
        const totalFlags = parseInt(flagBtn.getAttribute('data-total-flags') || '0', 10);
        const restrictionLevel = flagBtn.getAttribute('data-restriction-level') || 'none';

        // If user already has flags or active restriction, treat the action as "unflag"
        if (totalFlags > 0 || restrictionLevel !== 'none') {
            // Use customConfirm for consistent UI (returns a Promise)
            if (typeof customConfirm === 'function') {
                customConfirm('This user currently has ' + totalFlags + ' flag(s) and/or an active restriction ("' + restrictionLevel + '").\n\nRemove all flags and lift restrictions?', 'Confirm Unflag')
                    .then(confirmed => {
                        if (confirmed) unflagUser(userId);
                    });
            } else {
                // Fallback to native confirm if customConfirm is not available
                if (confirm('This user currently has ' + totalFlags + ' flag(s) and/or an active restriction ("' + restrictionLevel + '").\n\nPress OK to remove all flags and lift restrictions, or Cancel to keep them.')) {
                    unflagUser(userId);
                }
            }
        } else {
            // Open flag modal to add a new flag
            openFlagModal(userId);
        }
    });
    
    // Change role button click handler
    document.querySelectorAll('.change-role-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-user-id');
            const currentRole = this.getAttribute('data-current-role');
            openRoleModal(userId, currentRole);
        });
    });
    
    // Role option card click handlers
    document.querySelectorAll('.role-option-card').forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            document.querySelectorAll('.role-option-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            this.classList.add('selected');
            selectedRole = this.getAttribute('data-role');
        });
    });
    
    // Role modal button handlers
    document.getElementById('roleModalCloseBtn').addEventListener('click', closeRoleModal);
    document.getElementById('roleCancelBtn').addEventListener('click', closeRoleModal);
    document.getElementById('roleChangeBtn').addEventListener('click', changeUserRole);
    
    // Close role modal when clicking outside
    document.getElementById('changeRoleModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRoleModal();
        }
    });
});
</script>
@endsection