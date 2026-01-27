<?php
/**
 * Admin Staff Scheduling View
 * Manage front desk staff schedules and PINs
 */

// Check access - admins only
if (!isset($_SESSION['user_id']) || !$isAdmin) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Fetch all front desk staff
$staffMembers = [];
$allSchedules = [];
try {
    $staffStmt = $pdo->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM staff_pins sp WHERE sp.user_id = u.id AND sp.is_active = 1) as has_pin,
               (SELECT COUNT(*) FROM staff_schedules ss WHERE ss.staff_id = u.id AND ss.schedule_date >= CURDATE()) as upcoming_shifts
        FROM users u
        WHERE u.role = 'front_desk_staff' AND u.is_active = 1
        ORDER BY u.first_name, u.last_name
    ");
    $staffMembers = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all upcoming schedules
    $scheduleStmt = $pdo->query("
        SELECT ss.*, u.first_name, u.last_name
        FROM staff_schedules ss
        JOIN users u ON ss.staff_id = u.id
        WHERE ss.schedule_date >= CURDATE()
        ORDER BY ss.schedule_date ASC, ss.start_time ASC
        LIMIT 50
    ");
    $allSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Admin staff scheduling error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-users-cog"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Staff Scheduling</h1>
            <p class="page-description">Manage front desk staff schedules and time tracking</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= count($staffMembers) ?></span>
            <span class="stat-label">Staff Members</span>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <a href="#" class="tab-link active" onclick="switchTab('staff'); return false;">
        <i class="fas fa-users"></i> Staff & PINs
    </a>
    <a href="#" class="tab-link" onclick="switchTab('schedules'); return false;">
        <i class="fas fa-calendar-alt"></i> Schedules
    </a>
    <a href="#" class="tab-link" onclick="switchTab('create'); return false;">
        <i class="fas fa-plus"></i> Create Schedule
    </a>
</div>

<style>
    .staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .staff-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
    }
    
    .staff-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .staff-avatar {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 18px;
    }
    
    .staff-info h4 {
        font-size: 16px;
        margin-bottom: 4px;
    }
    
    .staff-info .email {
        font-size: 12px;
        color: var(--text-dim);
    }
    
    .staff-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
        padding: 12px 0;
        border-top: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
    }
    
    .staff-stat .value {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
    }
    
    .staff-stat .label {
        font-size: 11px;
        color: var(--text-dim);
    }
    
    .pin-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .pin-status.has-pin {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .pin-status.no-pin {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    .staff-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .staff-actions button {
        flex: 1;
        padding: 10px;
        font-size: 13px;
    }
    
    /* Schedules Tab */
    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .schedule-table th,
    .schedule-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }
    
    .schedule-table th {
        background: var(--bg);
        font-size: 12px;
        text-transform: uppercase;
        color: var(--text-dim);
        font-weight: 700;
    }
    
    .schedule-table tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }
    
    .schedule-table .actions {
        display: flex;
        gap: 8px;
    }
    
    .schedule-table .actions button {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    /* Create Schedule Form */
    .create-form {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 30px;
        max-width: 600px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* PIN Modal */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.7);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: var(--bg-secondary);
        border-radius: 16px;
        padding: 30px;
        width: 90%;
        max-width: 400px;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-header h3 {
        font-size: 18px;
    }
    
    .modal-close {
        background: none;
        border: none;
        color: var(--text-dim);
        font-size: 20px;
        cursor: pointer;
    }
</style>

<!-- Staff & PINs Tab -->
<div class="tab-content active" id="tab-staff">
    <div class="staff-grid">
        <?php foreach ($staffMembers as $staff): ?>
            <div class="staff-card">
                <div class="staff-header">
                    <div class="staff-avatar">
                        <?= strtoupper(substr($staff['first_name'], 0, 1)) ?>
                    </div>
                    <div class="staff-info">
                        <h4><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></h4>
                        <div class="email"><?= htmlspecialchars($staff['email']) ?></div>
                    </div>
                </div>
                
                <div class="staff-stats">
                    <div class="staff-stat">
                        <div class="value"><?= $staff['upcoming_shifts'] ?></div>
                        <div class="label">Upcoming Shifts</div>
                    </div>
                </div>
                
                <span class="pin-status <?= $staff['has_pin'] ? 'has-pin' : 'no-pin' ?>">
                    <i class="fas fa-<?= $staff['has_pin'] ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= $staff['has_pin'] ? 'PIN Set' : 'No PIN' ?>
                </span>
                
                <div class="staff-actions">
                    <button class="btn-secondary" onclick="openPinModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['first_name']) ?>')">
                        <i class="fas fa-key"></i> Set PIN
                    </button>
                    <button class="btn-primary" onclick="openScheduleForm(<?= $staff['id'] ?>)">
                        <i class="fas fa-calendar-plus"></i> Add Shift
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Schedules Tab -->
<div class="tab-content" id="tab-schedules">
    <?php if (empty($allSchedules)): ?>
        <div style="text-align: center; padding: 60px; background: var(--bg-secondary); border-radius: 12px;">
            <i class="fas fa-calendar-times" style="font-size: 48px; color: var(--text-dim); margin-bottom: 15px;"></i>
            <h3>No Upcoming Schedules</h3>
            <p style="color: var(--text-dim);">Create schedules for your staff members.</p>
        </div>
    <?php else: ?>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allSchedules as $schedule): ?>
                    <tr>
                        <td><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></td>
                        <td><?= date('M j, Y', strtotime($schedule['schedule_date'])) ?></td>
                        <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                        <td><?= htmlspecialchars($schedule['location'] ?? '-') ?></td>
                        <td class="actions">
                            <button class="btn-secondary btn-sm" onclick="editSchedule(<?= $schedule['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-danger btn-sm" onclick="deleteSchedule(<?= $schedule['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Create Schedule Tab -->
<div class="tab-content" id="tab-create">
    <div class="create-form">
        <h3 style="margin-bottom: 25px;"><i class="fas fa-calendar-plus" style="color: var(--primary); margin-right: 10px;"></i> Create New Schedule</h3>
        
        <form id="schedule-form" onsubmit="submitSchedule(event)">
            <div class="form-group">
                <label>Staff Member</label>
                <select name="staff_id" id="schedule-staff" required>
                    <option value="">Select staff member</option>
                    <?php foreach ($staffMembers as $staff): ?>
                        <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="schedule_date" id="schedule-date" required min="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" id="schedule-start" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" id="schedule-end" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Location (optional)</label>
                <input type="text" name="location" id="schedule-location" placeholder="e.g., Front Desk">
            </div>
            
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" id="schedule-notes" rows="3" placeholder="Any special notes..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Create Schedule
            </button>
        </form>
    </div>
</div>

<!-- PIN Modal -->
<div class="modal" id="pin-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Set Staff PIN</h3>
            <button class="modal-close" onclick="closePinModal()">&times;</button>
        </div>
        <p style="color: var(--text-dim); margin-bottom: 20px;">
            Set a 4-digit PIN for <strong id="pin-staff-name">Staff</strong> to use for kiosk login.
        </p>
        <form onsubmit="submitPin(event)">
            <input type="hidden" id="pin-staff-id">
            <div class="form-group">
                <label>4-Digit PIN</label>
                <input type="text" id="pin-input" pattern="[0-9]{4}" maxlength="4" 
                       placeholder="Enter 4 digits" required 
                       style="font-size: 24px; text-align: center; letter-spacing: 10px;">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%;">
                <i class="fas fa-key"></i> Save PIN
            </button>
        </form>
    </div>
</div>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

function switchTab(tab) {
    document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.closest('.tab-link').classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function openPinModal(staffId, staffName) {
    document.getElementById('pin-staff-id').value = staffId;
    document.getElementById('pin-staff-name').textContent = staffName;
    document.getElementById('pin-input').value = '';
    document.getElementById('pin-modal').classList.add('active');
}

function closePinModal() {
    document.getElementById('pin-modal').classList.remove('active');
}

function submitPin(e) {
    e.preventDefault();
    
    const staffId = document.getElementById('pin-staff-id').value;
    const pin = document.getElementById('pin-input').value;
    
    if (!/^\d{4}$/.test(pin)) {
        alert('Please enter exactly 4 digits');
        return;
    }
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'set_staff_pin',
            staff_id: staffId,
            pin: pin,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('PIN set successfully');
            closePinModal();
            location.reload();
        } else {
            alert(data.message || 'Failed to set PIN');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function openScheduleForm(staffId) {
    switchTab('create');
    document.getElementById('schedule-staff').value = staffId;
}

function submitSchedule(e) {
    e.preventDefault();
    
    const formData = {
        action: 'create_schedule',
        staff_id: document.getElementById('schedule-staff').value,
        schedule_date: document.getElementById('schedule-date').value,
        start_time: document.getElementById('schedule-start').value,
        end_time: document.getElementById('schedule-end').value,
        location: document.getElementById('schedule-location').value,
        notes: document.getElementById('schedule-notes').value,
        csrf_token: csrfToken
    };
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Schedule created successfully');
            location.reload();
        } else {
            alert(data.message || 'Failed to create schedule');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function deleteSchedule(scheduleId) {
    if (!confirm('Delete this schedule?')) return;
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_schedule',
            schedule_id: scheduleId,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to delete schedule');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function editSchedule(scheduleId) {
    // For now, just alert - could implement edit modal
    alert('Edit functionality coming soon. Delete and recreate the schedule for now.');
}
</script>
