@extends('layouts.app')

@section('title', 'Personnel')

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
    
    .status-badge.active {
        color: #065f46;
    }
    
    .status-badge.inactive {
        color: #991b1b;
    }
    
    .status-badge.on-leave {
        color: #92400e;
    }
    
    .officer-id {
        font-family: 'Courier New', monospace;
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }
    
    .rank-badge {
        color: #3730a3;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
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
        margin-right: 0.5rem;
    }
    
    .action-btn:hover {
        background: #f9fafb;
        border-color: #3b82f6;
    }
    
    .action-icon {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    
    .assign-station-btn {
        color: #6b7280;
    }
    
    .assign-station-btn:hover {
        color: #3b82f6;
    }
    
    .unassign-station-btn {
        color: #6b7280;
        margin-left: 4px;
    }
    
    .unassign-station-btn:hover {
        color: #ef4444;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
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
        color: #9ca3af;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-close-btn:hover {
        color: #4b5563;
    }
    
    .station-select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
    }
    
    .station-list {
        max-height: 400px;
        overflow-y: auto;
        margin-bottom: 1.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }
    
    .station-item {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .station-item:last-child {
        border-bottom: none;
    }
    
    .station-item:hover {
        background-color: #f9fafb;
    }
    
    .station-item.selected {
        background-color: #eff6ff;
        border-left: 3px solid #3b82f6;
    }
    
    .station-name {
        font-weight: 500;
        color: #1f2937;
    }
    
    .station-item.selected .station-name {
        color: #1e40af;
        font-weight: 600;
    }
    
    .station-check {
        width: 20px;
        height: 20px;
        border: 2px solid #d1d5db;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .station-item.selected .station-check {
        background-color: #3b82f6;
        border-color: #3b82f6;
    }
    
    .station-check-icon {
        width: 12px;
        height: 12px;
        stroke: white;
        fill: none;
        stroke-width: 3;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: 0;
    }
    
    .station-item.selected .station-check-icon {
        opacity: 1;
    }
    
    .loading-stations {
        text-align: center;
        padding: 2rem;
        color: #6b7280;
    }
    
    .no-stations {
        text-align: center;
        padding: 2rem;
        color: #9ca3af;
    }
    
    .modal-buttons {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }
    
    .btn-cancel, .btn-assign {
        padding: 0.625rem 1.25rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }
    
    .btn-cancel {
        background: white;
        border: 1px solid #d1d5db;
        color: #374151;
    }
    
    .btn-cancel:hover {
        background: #f9fafb;
    }
    
    .btn-assign {
        background: #3b82f6;
        color: white;
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
            min-width: 900px;
        }
    }
</style>
@endsection

@section('content')
<div class="users-header">
    <div class="users-title-section">
        <h1>Personnel</h1>
        <p>Manage police officers and personnel records</p>
    </div>
    
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <select id="stationFilter" onchange="filterPersonnel()" style="padding: 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #374151; min-width: 150px;">
            <option value="">All Stations</option>
            @php
                $stations = \App\Models\PoliceStation::orderBy('station_name')->get();
            @endphp
            @foreach($stations as $station)
                <option value="{{ $station->station_name }}">{{ $station->station_name }}</option>
            @endforeach
            <option value="Unassigned">Unassigned</option>
        </select>
        
        <select id="statusFilter" onchange="filterPersonnel()" style="padding: 0.75rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; color: #374151; min-width: 120px;">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="On-Leave">On-Leave</option>
        </select>
    </div>

    <div class="search-box">
        <svg class="search-icon" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
        </svg>
        <input 
            type="text" 
            class="search-input" 
            placeholder="Search personnel..." 
            id="searchInput"
            onkeyup="filterPersonnel()"
        >
    </div>
</div>

<div class="users-table-container">
    <table class="users-table" id="personnelTable">
        <thead>
            <tr>
                <th class="sortable" onclick="sortPersonnelTable(0)">Officer ID</th>
                <th class="sortable" onclick="sortPersonnelTable(1)">Name</th>
                <th class="sortable" onclick="sortPersonnelTable(2)">Rank</th>
                <th class="sortable" onclick="sortPersonnelTable(3)">Assigned Station</th>
                <th class="sortable" onclick="sortPersonnelTable(4)">Role</th>
                <th class="sortable" onclick="sortPersonnelTable(5)">Email</th>
                <th class="sortable" onclick="sortPersonnelTable(6)">Contact</th>
                <th class="sortable" onclick="sortPersonnelTable(7)">Assigned Since</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($officers as $officer)
            <tr>
                <td class="officer-id">{{ str_pad($officer->officer_id, 5, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $officer->user->firstname ?? 'N/A' }} {{ $officer->user->lastname ?? '' }}</td>
                <td>
                    @if($officer->rank)
                        <span class="rank-badge">{{ $officer->rank }}</span>
                    @else
                        <span style="color: #9ca3af;">Not Set</span>
                    @endif
                </td>
                <td>
                    @if($officer->policeStation)
                        {{ $officer->policeStation->station_name }}
                    @else
                        <span style="color: #9ca3af;">Unassigned</span>
                    @endif
                </td>
                <td>
                    @if($officer->user && $officer->user->role)
                        <span class="status-badge" style="color: #1e40af; text-transform: capitalize;">{{ $officer->user->role }}</span>
                    @else
                        <span style="color: #9ca3af;">Not Set</span>
                    @endif
                </td>
                <td>{{ $officer->user->email ?? 'N/A' }}</td>
                <td>{{ $officer->user->contact ?? 'N/A' }}</td>
                <td>
                    @if($officer->assigned_since)
                        {{ $officer->assigned_since->timezone('Asia/Manila')->format('m/d/Y') }}
                    @else
                        <span style="color: #9ca3af;">N/A</span>
                    @endif
                </td>
                <td>
                    <button type="button" class="action-btn assign-station-btn" data-user-id="{{ $officer->user_id }}" title="Assign to Police Station">
                        <svg class="action-icon" viewBox="0 0 24 24">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </button>
                    @if($officer->user && $officer->user->station_id)
                    <button type="button" class="action-btn unassign-station-btn" data-user-id="{{ $officer->user_id }}" title="Unassign from Station">
                        <svg class="action-icon" viewBox="0 0 24 24">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="no-results">
                    <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.3;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                    <p>No personnel found</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Assign Station Modal -->
<div id="assignStationModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Assign Officer to Police Station</h2>
            <button type="button" class="modal-close-btn" id="modalCloseBtn">&times;</button>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #374151; font-size: 0.875rem;">Select Police Station</label>
            <div id="stationList" class="station-list">
                <div class="loading-stations">Click to load police stations...</div>
            </div>
        </div>
        
        <div class="modal-buttons">
            <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
            <button type="button" class="btn-assign" id="assignBtn">Assign Station</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let stationsLoaded = false;
let selectedStationId = null;

// Load police stations for modal
async function loadPoliceStations() {
    if (stationsLoaded) return;
    
    showLoading('Loading police stations...');
    const stationList = document.getElementById('stationList');
    stationList.innerHTML = '<div class="loading-stations">Loading police stations...</div>';
    
    try {
        console.log('Fetching police stations from /api/police-stations');
        
        const response = await fetch('/api/police-stations', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        });
        
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        
        const data = await response.json();
        console.log('Response data:', data);
        
        if (response.ok && data.success) {
            if (data.data && data.data.length > 0) {
                console.log('Found', data.data.length, 'stations');
                
                const sortedStations = data.data.sort((a, b) => {
                    const aNum = parseInt(a.station_name?.match(/\d+/)?.[0] || 0);
                    const bNum = parseInt(b.station_name?.match(/\d+/)?.[0] || 0);
                    return aNum - bNum;
                });
                
                stationList.innerHTML = '';
                
                sortedStations.forEach(station => {
                    const stationId = station.station_id;
                    const stationName = station.station_name;
                    
                    console.log('Adding station:', stationId, stationName);
                    
                    const stationItem = document.createElement('div');
                    stationItem.className = 'station-item';
                    stationItem.setAttribute('data-station-id', stationId);
                    stationItem.innerHTML = `
                        <span class="station-name">${stationName}</span>
                        <div class="station-check">
                            <svg class="station-check-icon" viewBox="0 0 24 24">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                    `;
                    
                    stationItem.addEventListener('click', function() {
                        // Remove selected class from all items
                        document.querySelectorAll('.station-item').forEach(item => {
                            item.classList.remove('selected');
                        });
                        
                        // Add selected class to clicked item
                        this.classList.add('selected');
                        selectedStationId = stationId;
                        console.log('Selected station:', stationId, stationName);
                    });
                    
                    stationList.appendChild(stationItem);
                });
                
                stationsLoaded = true;
            } else {
                console.warn('No stations in data');
                stationList.innerHTML = '<div class="no-stations">No police stations found</div>';
            }
        } else {
            console.error('API returned error:', data);
            stationList.innerHTML = '<div class="no-stations">Failed to load police stations: ' + (data.message || 'Unknown error') + '</div>';
        }
    } catch (error) {
        console.error('Error loading police stations:', error);
        stationList.innerHTML = '<div class="no-stations">Error: ' + error.message + '</div>';
    } finally {
        hideLoading();
    }
}

function openAssignStationModal(userId) {
    document.getElementById('assignStationModal').classList.add('active');
    document.getElementById('assignBtn').setAttribute('data-user-id', userId);
    selectedStationId = null;
    
    // Remove selected class from all items
    document.querySelectorAll('.station-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Load stations immediately when modal opens
    loadPoliceStations();
}

function closeAssignStationModal() {
    console.log('Closing modal triggered'); // Debug logic
    document.getElementById('assignStationModal').classList.remove('active');
    selectedStationId = null;
}

async function assignStationToOfficer() {
    const userId = document.getElementById('assignBtn').getAttribute('data-user-id');
    
    if (!selectedStationId) {
        alert('Please select a police station');
        return;
    }
    
    const confirmed = await confirm('Are you sure you want to assign this officer to the selected police station?');
    if (!confirmed) {
        return;
    }
    
    const assignBtn = document.getElementById('assignBtn');
    assignBtn.disabled = true;
    assignBtn.textContent = 'Assigning...';
    
    showLoading('Assigning officer to station...');
    
    fetch('/users/' + userId + '/assign-station', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ station_id: selectedStationId })
    })
    .then(async response => {
        const data = await response.json();
        
        // Hide loading BEFORE showing the alert, otherwise z-index 10000 loading blocks z-index 9999 alert
        hideLoading();
        
        if (data.success) {
            await alert('Officer has been assigned to the police station successfully');
            closeAssignStationModal();
            location.reload();
        } else {
            const errorMsg = data.message || 'Unknown error occurred';
            await alert('Error: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred while assigning the officer: ' + error.message);
    })
    .finally(() => {
        hideLoading();
        assignBtn.disabled = false;
        assignBtn.textContent = 'Assign Station';
    });
}

async function unassignStationFromOfficer(userId) {
    showLoading('Removing station assignment...');
    
    fetch('/users/' + userId + '/unassign-station', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            await alert('Officer has been unassigned from the police station successfully');
            location.reload();
        } else {
            const errorMsg = data.message || 'Unknown error occurred';
            await alert('Error: ' + errorMsg);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred while unassigning the officer: ' + error.message);
    })
    .finally(() => {
        hideLoading();
    });
}

function filterPersonnel() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const stationFilter = document.getElementById('stationFilter').value.toUpperCase();
    const statusFilter = document.getElementById('statusFilter').value.toUpperCase();
    
    const table = document.getElementById('personnelTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const tds = tr[i].getElementsByTagName('td');
        if (tds.length < 3) continue; // Skip header or malformed rows

        let textMatch = false;
        let stationMatch = false;
        let statusMatch = false;
        
        // 1. Check Text Search
        const rowText = tr[i].textContent || tr[i].innerText;
        if (rowText.toUpperCase().indexOf(filter) > -1) {
            textMatch = true;
        }
        
        // 2. Check Station (Column 3)
        const stationText = tds[3].textContent || tds[3].innerText;
        if (stationFilter === "" || stationText.toUpperCase().includes(stationFilter)) {
            stationMatch = true;
        }
        
        // 3. Check Status (Column 8)
        const statusText = tds[8].textContent || tds[8].innerText;
        // Strict match for status to avoid partial (e.g. "Active" in "Inactive")
        // But the filter values are "Active", "Inactive", "On-Leave"
        // And the cell contains text with whitespace.
        // Simple includes is risky for "Active" vs "Inactive".
        // Better to check if stationFilter is "INACTIVE" vs "ACTIVE".
        // Actually, "Active" is inside "Inactive".
        // Let's use exact trim match if possible, or refined includes.
        
        if (statusFilter === "") {
            statusMatch = true;
        } else {
             const cellStatus = statusText.trim().toUpperCase();
             // Check if the cell status *contains* the filter status, but safer.
             // OR just use includes. "Active" will match "Inactive".
             // We can check if statusFilter is "ACTIVE" and cellStatus is "INACTIVE", exclude.
             
             if (statusFilter === 'ACTIVE' && cellStatus.includes('INACTIVE')) {
                 statusMatch = false;
             } else if (cellStatus.includes(statusFilter)) {
                 statusMatch = true;
             }
        }

        if (textMatch && stationMatch && statusMatch) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

let personnelSortDirections = {};

function sortPersonnelTable(columnIndex) {
    const table = document.getElementById('personnelTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    const headers = table.getElementsByTagName('th');
    
    // Toggle sort direction
    if (!personnelSortDirections[columnIndex]) {
        personnelSortDirections[columnIndex] = 'asc';
    } else if (personnelSortDirections[columnIndex] === 'asc') {
        personnelSortDirections[columnIndex] = 'desc';
    } else {
        personnelSortDirections[columnIndex] = 'asc';
    }
    
    const direction = personnelSortDirections[columnIndex];
    
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
        
        // Handle numeric values (Officer ID)
        if (columnIndex === 0) {
            aValue = parseInt(aValue) || 0;
            bValue = parseInt(bValue) || 0;
        }
        // Handle dates (column 6)
        else if (columnIndex === 6) {
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

// Add event listeners after DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Assign station button click handler
    document.querySelectorAll('.assign-station-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-user-id');
            openAssignStationModal(userId);
        });
    });
    
    // Unassign station button click handler
    document.querySelectorAll('.unassign-station-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-user-id');
            if (await confirm('Are you sure you want to unassign this officer from their current station?')) {
                unassignStationFromOfficer(userId);
            }
        });
    });
    
    // Modal button handlers
    document.getElementById('modalCloseBtn').addEventListener('click', closeAssignStationModal);
    document.getElementById('cancelBtn').addEventListener('click', closeAssignStationModal);
    document.getElementById('assignBtn').addEventListener('click', assignStationToOfficer);
    
    // Close modal when clicking outside
    // Close modal when clicking outside - DISABLED to prevent auto-close issues
    /* 
    document.getElementById('assignStationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAssignStationModal();
        }
    });
    */
    
    // Prevent clicks inside modal from closing it
    document.querySelector('.modal-content').addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>
@endsection
