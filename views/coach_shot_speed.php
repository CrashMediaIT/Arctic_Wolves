<?php
/**
 * Coach Shot Speed Tracker
 * Record and track shot speed/velocity for athletes
 */

$csrf_token = $_SESSION['csrf_token'] ?? '';
if (empty($csrf_token)) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

// Fetch all active users for the dropdown (all users are athletes)
$athletes = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        WHERE u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ");
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (Exception $e) {
    $athletes = [];
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-hockey-puck"></i> Shot Speed Tracker</h1>
        <p class="page-description">Measure and track shot velocity for athletes</p>
    </div>
    <div class="page-header-actions">
        <!-- Optional: Add action buttons here if needed -->
    </div>
</div>

<style>
    .shot-speed-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-6);
        margin-top: var(--space-5);
    }
    @media (max-width: 992px) {
        .shot-speed-container {
            grid-template-columns: 1fr;
        }
    }
    .speed-input-wrapper {
        text-align: center;
        padding: var(--space-8) var(--space-6);
    }
    .speed-display {
        font-family: 'Courier New', 'Consolas', monospace;
        font-size: 64px;
        font-weight: 900;
        color: var(--text-white);
        letter-spacing: 2px;
        margin-bottom: var(--space-4);
        text-shadow: 0 0 20px rgba(107, 70, 193, 0.4);
    }
    .speed-input {
        width: 100%;
        max-width: 300px;
        font-size: 48px;
        text-align: center;
        padding: 16px;
        background: var(--bg-main);
        border: 2px solid var(--border);
        border-radius: var(--radius-lg);
        color: var(--text-white);
        font-family: 'Courier New', monospace;
        font-weight: 700;
    }
    .speed-input:focus {
        border-color: var(--primary);
        outline: none;
    }
    .unit-selector {
        display: flex;
        gap: var(--space-3);
        justify-content: center;
        margin: var(--space-4) 0;
    }
    .unit-btn {
        padding: 12px 32px;
        border: 2px solid var(--border);
        background: var(--bg-secondary);
        color: var(--text-primary);
        border-radius: var(--radius-lg);
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-bold);
        cursor: pointer;
        transition: all 0.2s;
    }
    .unit-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    .unit-btn:hover:not(.active) {
        border-color: var(--primary);
    }
    .speed-controls {
        display: flex;
        gap: var(--space-3);
        justify-content: center;
        flex-wrap: wrap;
        margin-top: var(--space-4);
    }
    .speed-table {
        width: 100%;
        border-collapse: collapse;
    }
    .speed-table th {
        text-align: left;
        padding: var(--space-3) var(--space-4);
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--border);
        font-weight: var(--font-weight-semibold);
    }
    .speed-table td {
        padding: var(--space-3) var(--space-4);
        font-size: var(--font-size-base);
        color: var(--text-primary);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .speed-table tr:hover td {
        background: rgba(107, 70, 193, 0.05);
    }
    .speed-value {
        font-family: 'Courier New', 'Consolas', monospace;
        font-weight: var(--font-weight-bold);
        font-size: var(--font-size-lg);
        color: var(--primary-light);
    }
    .speed-max {
        color: var(--success);
    }
    .speed-empty {
        text-align: center;
        padding: var(--space-8);
        color: var(--text-muted);
    }
    .stat-card {
        background: var(--bg-secondary);
        padding: var(--space-4);
        border-radius: var(--radius-md);
        text-align: center;
        border: 1px solid var(--border);
    }
    .stat-label {
        font-size: var(--font-size-sm);
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: var(--space-2);
    }
    .stat-value {
        font-size: 32px;
        font-weight: var(--font-weight-bold);
        color: var(--primary-light);
        font-family: 'Courier New', monospace;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: var(--space-4);
        margin-bottom: var(--space-5);
    }
    .delete-btn {
        background: var(--error);
        color: #fff;
        border: none;
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: var(--font-size-sm);
        transition: all var(--transition-fast);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .delete-btn:hover {
        background: var(--error-hover);
        transform: translateY(-1px);
    }
</style>

<div class="shot-speed-container">
    <!-- Recording Panel -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hockey-puck"></i> Record Shot Speed</h3>
            </div>
            <div class="card-body">
                <form id="speed-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-group" style="margin-bottom: var(--space-5);">
                        <label class="form-label">Select Athlete *</label>
                        <div id="shot-speed-athlete-typeahead"></div>
                    </div>
                    
                    <div class="speed-input-wrapper">
                        <div class="speed-display">
                            <input type="number" 
                                   id="speed-input" 
                                   name="speed" 
                                   class="speed-input" 
                                   placeholder="0" 
                                   min="1" 
                                   max="150" 
                                   step="0.1"
                                   required>
                        </div>
                        
                        <div class="unit-selector">
                            <button type="button" class="unit-btn active" data-unit="mph" onclick="selectUnit('mph')">
                                MPH
                            </button>
                            <button type="button" class="unit-btn" data-unit="km/h" onclick="selectUnit('km/h')">
                                KM/H
                            </button>
                        </div>
                        <input type="hidden" id="unit-input" name="unit" value="mph">
                        
                        <div class="form-group">
                            <label class="form-label">Notes (Optional)</label>
                            <input type="text" 
                                   name="notes" 
                                   class="form-input" 
                                   placeholder="e.g., wrist shot, slap shot">
                        </div>
                        
                        <div class="speed-controls">
                            <button type="submit" id="record-btn" class="btn btn-primary" disabled style="min-width: 180px; height: 50px;">
                                <i class="fas fa-save"></i> Record Speed
                            </button>
                        </div>
                    </div>
                </form>
                
                <div id="speed-alert" class="alert" style="display:none; margin-top: var(--space-4);"></div>
            </div>
        </div>
    </div>
    
    <!-- History & Stats Panel -->
    <div>
        <!-- Stats Summary -->
        <div class="card" id="stats-card" style="display:none;">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Statistics</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid" id="stats-grid">
                    <!-- Stats will be populated here -->
                </div>
            </div>
        </div>
        
        <!-- Recent Measurements -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Measurements</h3>
                <span id="measurement-count" style="color: var(--text-muted); font-size: var(--font-size-sm);"></span>
            </div>
            <div class="card-body" style="padding: 0; max-height: 600px; overflow-y: auto;">
                <table class="speed-table" id="speed-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Speed</th>
                            <th>Notes</th>
                            <th>Recorded By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="speed-tbody">
                    </tbody>
                </table>
                <div id="no-speeds" class="speed-empty">
                    <i class="fas fa-hockey-puck" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                    Select an athlete to view their shot speed history
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';
let currentUnit = 'mph';
let currentAthleteId = null;

// Initialize athlete typeahead
new ArcticTypeahead({
    container: '#shot-speed-athlete-typeahead',
    name: 'athlete_id',
    placeholder: 'Search for a user…',
    roles: '',
    multiple: false,
    required: true,
    onSelect: function(item) {
        currentAthleteId = item.id;
        const recordBtn = document.getElementById('record-btn');
        const speedInput = document.getElementById('speed-input');
        recordBtn.disabled = !(currentAthleteId && speedInput.value);
        loadRecentSpeeds();
        loadStats();
    },
    onChange: function(ids) {
        if (!ids || ids.length === 0) {
            currentAthleteId = null;
            document.getElementById('record-btn').disabled = true;
            document.getElementById('no-speeds').style.display = 'block';
            document.getElementById('speed-tbody').innerHTML = '';
            document.getElementById('stats-card').style.display = 'none';
        }
    }
});

// Unit selection
function selectUnit(unit) {
    currentUnit = unit;
    document.querySelectorAll('.unit-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-unit="${unit}"]`).classList.add('active');
    document.getElementById('unit-input').value = unit;
    
    // Update max value based on unit
    const speedInput = document.getElementById('speed-input');
    speedInput.max = unit === 'mph' ? 150 : 240;
}

// Enable record button when speed value changes
document.getElementById('speed-input').addEventListener('input', function() {
    const recordBtn = document.getElementById('record-btn');
    recordBtn.disabled = !(currentAthleteId && this.value);
});

// Submit form
document.getElementById('speed-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!currentAthleteId) {
        showAlert('error', 'Please select an athlete');
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'record_speed');
    
    const recordBtn = document.getElementById('record-btn');
    recordBtn.disabled = true;
    recordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording...';
    
    fetch('process_shot_speed.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            document.getElementById('speed-input').value = '';
            document.querySelector('[name="notes"]').value = '';
            loadRecentSpeeds();
            loadStats();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Error recording speed');
        console.error('Error:', error);
    })
    .finally(() => {
        const speedInput = document.getElementById('speed-input');
        const btn = document.getElementById('record-btn');
        btn.disabled = !(currentAthleteId && speedInput.value);
        btn.innerHTML = '<i class="fas fa-save"></i> Record Speed';
    });
});

// Load recent speeds for selected athlete
function loadRecentSpeeds() {
    if (!currentAthleteId) return;
    
    const formData = new FormData();
    formData.append('action', 'get_recent_speeds');
    formData.append('athlete_id', currentAthleteId);
    formData.append('csrf_token', csrfToken);
    
    fetch('process_shot_speed.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderSpeeds(data.speeds);
        }
    })
    .catch(error => console.error('Error loading speeds:', error));
}

// Load statistics
function loadStats() {
    if (!currentAthleteId) return;
    
    const formData = new FormData();
    formData.append('action', 'get_stats');
    formData.append('athlete_id', currentAthleteId);
    formData.append('csrf_token', csrfToken);
    
    fetch('process_shot_speed.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.stats.length > 0) {
            renderStats(data.stats);
        }
    })
    .catch(error => console.error('Error loading stats:', error));
}

// Render speeds table
function renderSpeeds(speeds) {
    const tbody = document.getElementById('speed-tbody');
    const noSpeeds = document.getElementById('no-speeds');
    const measurementCount = document.getElementById('measurement-count');
    
    if (!speeds || speeds.length === 0) {
        tbody.innerHTML = '';
        noSpeeds.style.display = 'block';
        measurementCount.textContent = '';
        return;
    }
    
    noSpeeds.style.display = 'none';
    measurementCount.textContent = `${speeds.length} measurement${speeds.length !== 1 ? 's' : ''}`;
    
    // Find max speed
    let maxSpeed = 0;
    speeds.forEach(s => {
        const speedValue = parseFloat(s.speed);
        if (speedValue > maxSpeed) maxSpeed = speedValue;
    });
    
    let html = '';
    speeds.forEach(speed => {
        const speedValue = parseFloat(speed.speed);
        const isMax = speedValue === maxSpeed && speeds.length > 1;
        const speedClass = isMax ? 'speed-value speed-max' : 'speed-value';
        const date = new Date(speed.created_at);
        const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        // Escape user-provided data
        const escapedNotes = speed.notes ? escapeHtml(speed.notes) : '-';
        const escapedName = speed.first_name ? escapeHtml(speed.first_name + ' ' + speed.last_name) : '-';
        
        html += '<tr>';
        html += '<td>' + dateStr + '</td>';
        html += '<td class="' + speedClass + '">' + speedValue.toFixed(1) + ' ' + escapeHtml(speed.unit) + '</td>';
        html += '<td>' + escapedNotes + '</td>';
        html += '<td>' + escapedName + '</td>';
        html += '<td><button class="delete-btn" onclick="deleteSpeed(' + speed.id + ')"><i class="fas fa-trash"></i></button></td>';
        html += '</tr>';
    });
    
    tbody.innerHTML = html;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Render statistics
function renderStats(stats) {
    const statsCard = document.getElementById('stats-card');
    const statsGrid = document.getElementById('stats-grid');
    
    if (!stats || stats.length === 0) {
        statsCard.style.display = 'none';
        return;
    }
    
    statsCard.style.display = 'block';
    
    let html = '';
    stats.forEach(stat => {
        html += '<div class="stat-card">';
        html += '<div class="stat-label">Max Speed (' + stat.unit + ')</div>';
        html += '<div class="stat-value">' + parseFloat(stat.max_speed).toFixed(1) + '</div>';
        html += '</div>';
        
        html += '<div class="stat-card">';
        html += '<div class="stat-label">Average (' + stat.unit + ')</div>';
        html += '<div class="stat-value">' + parseFloat(stat.avg_speed).toFixed(1) + '</div>';
        html += '</div>';
        
        html += '<div class="stat-card">';
        html += '<div class="stat-label">Total Tests</div>';
        html += '<div class="stat-value">' + stat.total_measurements + '</div>';
        html += '</div>';
    });
    
    statsGrid.innerHTML = html;
}

// Delete speed measurement
async function deleteSpeed(speedId) {
    if (!await showConfirmModal('Delete this measurement?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_speed');
    formData.append('speed_id', speedId);
    formData.append('csrf_token', csrfToken);
    
    fetch('process_shot_speed.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadRecentSpeeds();
            loadStats();
            showAlert('success', 'Measurement deleted');
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Error deleting measurement');
        console.error('Error:', error);
    });
}

// Show alert message
function showAlert(type, message) {
    const alert = document.getElementById('speed-alert');
    alert.className = 'alert alert-' + type;
    alert.textContent = message;
    alert.style.display = 'block';
    
    setTimeout(() => {
        alert.style.display = 'none';
    }, 4000);
}
</script>
