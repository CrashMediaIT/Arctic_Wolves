<?php
// views/mileage_tracker.php - Mileage logging interface
require_once __DIR__ . '/../security.php';

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['admin', 'coach', 'coach_plus'])) {
    die('Access denied');
}

// Get athletes for dropdown
$athletes_stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'athlete' ORDER BY first_name, last_name");
$athletes = $athletes_stmt->fetchAll();

// Get recent sessions for dropdown
$sessions_stmt = $pdo->query("
    SELECT s.id, s.title as session_name, s.session_date, s.session_time 
    FROM sessions s 
    WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY s.session_date DESC, s.session_time DESC
    LIMIT 50
");
$sessions = $sessions_stmt->fetchAll();

// Get Google Maps API key
$api_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
$google_maps_api_key = $api_key_stmt->fetchColumn();

// Get mileage rates
$rates_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mileage_rate_per_km', 'mileage_rate_per_mile')");
$rates = $rates_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$rate_per_km = floatval($rates['mileage_rate_per_km'] ?? 0.68);
$rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? 1.10);
?>

<style>
    .mileage-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .mileage-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 30px;
    }
    
    .mileage-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 25px;
    }
    
    .mileage-card h3 {
        color: white;
        font-size: 1.2rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .mileage-card h3 i {
        color: var(--primary);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        color: white;
        font-size: 1rem;
    }
    
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .waypoint-container {
        background: rgba(0, 0, 0, 0.2);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        position: relative;
    }
    
    .waypoint-label {
        color: var(--primary);
        font-weight: 600;
        margin-bottom: 10px;
        display: block;
    }
    
    .remove-waypoint {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.95rem;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: #e64500;
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .distance-display {
        background: rgba(255, 77, 0, 0.1);
        border: 2px solid var(--primary);
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        margin: 20px 0;
    }
    
    .distance-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--primary);
    }
    
    .distance-label {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        margin-top: 5px;
    }
    
    .logs-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .logs-table th {
        background: rgba(255, 77, 0, 0.1);
        color: var(--primary);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid var(--primary);
    }
    
    .logs-table td {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
    }
    
    .logs-table tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    
    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .badge-success {
        background: #10b981;
        color: white;
    }
    
    .badge-warning {
        background: #f59e0b;
        color: white;
    }
    
    .action-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
        margin-right: 5px;
    }
    
    .action-btn-edit {
        background: #3b82f6;
        color: white;
    }
    
    .action-btn-delete {
        background: #ef4444;
        color: white;
    }
    
    @media (max-width: 1024px) {
        .mileage-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($google_maps_api_key) ?>&libraries=places"></script>

<div class="dash-content mileage-container">
    <div class="dash-header">
        <h2><i class="fas fa-route"></i> Mileage Tracker</h2>
        <p style="color: rgba(255, 255, 255, 0.6);">Log and track travel expenses</p>
    </div>
    
    <div class="mileage-grid">
        <!-- Log New Trip -->
        <div class="mileage-card">
            <h3><i class="fas fa-plus-circle"></i> Log New Trip</h3>
            
            <form id="mileageForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create" id="formAction">
                <input type="hidden" name="log_id" id="logId">
                <input type="hidden" name="distance_km" id="distanceKm">
                <input type="hidden" name="distance_miles" id="distanceMiles">
                <input type="hidden" name="waypoints" id="waypointsData">
                
                <div class="form-group">
                    <label>Trip Date</label>
                    <input type="date" name="trip_date" id="tripDate" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Athlete (Optional)</label>
                    <select name="athlete_id" id="athleteId">
                        <option value="">-- Select Athlete --</option>
                        <?php foreach ($athletes as $athlete): ?>
                            <option value="<?= $athlete['id'] ?>">
                                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Session (Optional)</label>
                    <select name="session_id" id="sessionId">
                        <option value="">-- Select Session --</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= $session['id'] ?>">
                                <?= htmlspecialchars($session['session_name']) ?> - 
                                <?= date('M d, Y', strtotime($session['session_date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Purpose</label>
                    <textarea name="purpose" id="purpose" rows="2" required placeholder="e.g., Travel to training session"></textarea>
                </div>
                
                <hr style="border-color: rgba(255, 255, 255, 0.1); margin: 25px 0;">
                
                <h4 style="color: white; margin-bottom: 15px;">Trip Route</h4>
                
                <div id="waypointsContainer">
                    <div class="waypoint-container" data-index="0">
                        <span class="waypoint-label">Start Location</span>
                        <input type="text" class="waypoint-input" data-index="0" placeholder="Enter starting address" required>
                    </div>
                    <div class="waypoint-container" data-index="1">
                        <span class="waypoint-label">End Location</span>
                        <input type="text" class="waypoint-input" data-index="1" placeholder="Enter destination address" required>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="addWaypoint()" style="margin-bottom: 20px;">
                    <i class="fas fa-plus"></i> Add Stop
                </button>
                
                <button type="button" class="btn btn-primary" onclick="calculateDistance()" style="width: 100%; margin-bottom: 15px;">
                    <i class="fas fa-calculator"></i> Calculate Distance
                </button>
                
                <div id="distanceDisplay" style="display: none;">
                    <div class="distance-display">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <div class="distance-value" id="distanceKmDisplay">0</div>
                                <div class="distance-label">kilometers</div>
                            </div>
                            <div>
                                <div class="distance-value" id="distanceMilesDisplay">0</div>
                                <div class="distance-label">miles</div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; font-size: 1.2rem; color: white;">
                            Reimbursement: <strong style="color: var(--primary);">$<span id="reimbursementDisplay">0.00</span></strong>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin-top: 5px;">
                                Rate: $<?= number_format($rate_per_km, 2) ?>/km
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;" id="submitBtn" disabled>
                    <i class="fas fa-save"></i> Save Trip Log
                </button>
            </form>
        </div>
        
        <!-- Recent Logs -->
        <div class="mileage-card">
            <h3>
                <i class="fas fa-list"></i> Recent Logs
                <button class="btn btn-secondary" onclick="exportLogs()" style="margin-left: auto; padding: 8px 15px; font-size: 0.85rem;">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </h3>
            
            <div class="form-group">
                <label>Date Range</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <input type="date" id="filterStartDate" value="<?= date('Y-m-01') ?>">
                    <input type="date" id="filterEndDate" value="<?= date('Y-m-t') ?>">
                </div>
                <button class="btn btn-secondary" onclick="loadLogs()" style="margin-top: 10px; width: 100%;">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
            
            <div id="logsContainer" style="overflow-x: auto; margin-top: 20px;">
                <p style="color: rgba(255, 255, 255, 0.6); text-align: center; padding: 20px;">
                    Loading...
                </p>
            </div>
        </div>
    </div>
</div>

<script>
let waypointCount = 2;
let autocompleteFields = [];
const ratePerKm = <?= $rate_per_km ?>;

// Initialize autocomplete on existing waypoint inputs
document.addEventListener('DOMContentLoaded', function() {
    initializeAutocomplete();
    loadLogs();
});

function initializeAutocomplete() {
    document.querySelectorAll('.waypoint-input').forEach(input => {
        if (!input.dataset.autocompleteInit) {
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.setFields(['formatted_address', 'name']);
            input.dataset.autocompleteInit = 'true';
            autocompleteFields.push(autocomplete);
        }
    });
}

function addWaypoint() {
    const container = document.getElementById('waypointsContainer');
    const lastWaypoint = container.lastElementChild;
    
    const newWaypoint = document.createElement('div');
    newWaypoint.className = 'waypoint-container';
    newWaypoint.dataset.index = waypointCount;
    newWaypoint.innerHTML = `
        <span class="waypoint-label">Stop ${waypointCount - 1}</span>
        <input type="text" class="waypoint-input" data-index="${waypointCount}" placeholder="Enter stop address" required>
        <button type="button" class="remove-waypoint" onclick="removeWaypoint(this)">×</button>
    `;
    
    container.insertBefore(newWaypoint, lastWaypoint);
    waypointCount++;
    
    initializeAutocomplete();
}

function removeWaypoint(btn) {
    btn.parentElement.remove();
    renumberWaypoints();
}

function renumberWaypoints() {
    const waypoints = document.querySelectorAll('.waypoint-container');
    waypoints.forEach((wp, index) => {
        const label = wp.querySelector('.waypoint-label');
        if (index === 0) {
            label.textContent = 'Start Location';
        } else if (index === waypoints.length - 1) {
            label.textContent = 'End Location';
        } else {
            label.textContent = `Stop ${index}`;
        }
    });
}

function getWaypoints() {
    const inputs = document.querySelectorAll('.waypoint-input');
    const waypoints = [];
    
    inputs.forEach(input => {
        if (input.value.trim()) {
            waypoints.push({
                address: input.value.trim(),
                name: input.value.trim().split(',')[0]
            });
        }
    });
    
    return waypoints;
}

async function calculateDistance() {
    const waypoints = getWaypoints();
    
    if (waypoints.length < 2) {
        alert('Please enter at least start and end locations');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'get_distance');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    formData.append('waypoints', JSON.stringify(waypoints));
    
    try {
        const response = await fetch('process_mileage.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('distanceKm').value = result.data.distance_km;
            document.getElementById('distanceMiles').value = result.data.distance_miles;
            document.getElementById('waypointsData').value = JSON.stringify(waypoints);
            
            document.getElementById('distanceKmDisplay').textContent = result.data.distance_km.toFixed(2);
            document.getElementById('distanceMilesDisplay').textContent = result.data.distance_miles.toFixed(2);
            
            const reimbursement = result.data.distance_km * ratePerKm;
            document.getElementById('reimbursementDisplay').textContent = reimbursement.toFixed(2);
            
            document.getElementById('distanceDisplay').style.display = 'block';
            document.getElementById('submitBtn').disabled = false;
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error calculating distance: ' + error.message);
    }
}

document.getElementById('mileageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('process_mileage.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Trip logged successfully!');
            this.reset();
            document.getElementById('distanceDisplay').style.display = 'none';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('formAction').value = 'create';
            loadLogs();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving trip: ' + error.message);
    }
});

async function loadLogs() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    
    try {
        const response = await fetch(`process_mileage.php?action=get_logs&start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        if (result.success) {
            displayLogs(result.logs);
        }
    } catch (error) {
        console.error('Error loading logs:', error);
    }
}

function displayLogs(logs) {
    const container = document.getElementById('logsContainer');
    
    if (logs.length === 0) {
        container.innerHTML = '<p style="color: rgba(255, 255, 255, 0.6); text-align: center; padding: 20px;">No logs found</p>';
        return;
    }
    
    let html = '<table class="logs-table"><thead><tr>';
    html += '<th>Date</th><th>Purpose</th><th>Distance</th><th>Reimbursement</th><th>Status</th><th>Actions</th>';
    html += '</tr></thead><tbody>';
    
    logs.forEach(log => {
        html += '<tr>';
        html += `<td>${new Date(log.trip_date).toLocaleDateString()}</td>`;
        html += `<td>${log.purpose}<br><small style="color: rgba(255,255,255,0.5)">${log.athlete_name || 'No athlete'}</small></td>`;
        html += `<td>${parseFloat(log.total_distance_km).toFixed(2)} km<br><small>${parseFloat(log.total_distance_miles).toFixed(2)} mi</small></td>`;
        html += `<td>$${parseFloat(log.reimbursement_amount).toFixed(2)}</td>`;
        html += `<td>${log.is_reimbursed == 1 ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-warning">Pending</span>'}</td>`;
        html += `<td>
            <button class="action-btn action-btn-delete" onclick="deleteLog(${log.id})">
                <i class="fas fa-trash"></i>
            </button>
        </td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

async function deleteLog(logId) {
    if (!confirm('Are you sure you want to delete this log?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('log_id', logId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    try {
        const response = await fetch('process_mileage.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            loadLogs();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting log: ' + error.message);
    }
}

function exportLogs() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    window.location.href = `process_mileage.php?action=export_csv&start_date=${startDate}&end_date=${endDate}`;
}
</script>
