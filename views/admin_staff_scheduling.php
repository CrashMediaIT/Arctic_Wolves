<?php
/**
 * Admin Staff Scheduling View
 * Manage front desk staff schedules, PINs, calendar view, and history
 */

// Check access - admins only
if (!isset($_SESSION['user_id']) || !$isAdmin) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

$activeTab = $_GET['tab'] ?? 'staff';

// Fetch all front desk staff
$staffMembers = [];
$allSchedules = [];
$historySchedules = [];
try {
    $staffStmt = $pdo->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM staff_pins sp WHERE sp.user_id = u.id AND sp.is_active = 1) as has_pin,
               (SELECT COUNT(*) FROM staff_schedules ss WHERE ss.staff_id = u.id AND ss.schedule_date >= CURDATE()) as upcoming_shifts,
               (SELECT COALESCE(SUM(total_hours), 0) FROM staff_shifts sf WHERE sf.staff_id = u.id AND sf.status = 'completed' AND sf.shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_hours
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
        LIMIT 100
    ");
    $allSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get schedule history (past 90 days)
    $historyStmt = $pdo->query("
        SELECT ss.*, u.first_name, u.last_name, c.first_name as created_by_first, c.last_name as created_by_last
        FROM staff_schedules ss
        JOIN users u ON ss.staff_id = u.id
        LEFT JOIN users c ON ss.created_by = c.id
        WHERE ss.schedule_date < CURDATE() AND ss.schedule_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ORDER BY ss.schedule_date DESC, ss.start_time DESC
        LIMIT 200
    ");
    $historySchedules = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Admin staff scheduling error: " . $e->getMessage());
}

// Group schedules by date for calendar
$schedulesByDate = [];
foreach ($allSchedules as $schedule) {
    $date = $schedule['schedule_date'];
    if (!isset($schedulesByDate[$date])) {
        $schedulesByDate[$date] = [];
    }
    $schedulesByDate[$date][] = $schedule;
}
?>

<div class="page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-users-cog"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Staff Scheduling</h1>
            <p class="page-description">Manage front desk staff schedules, PINs, and time tracking</p>
        </div>
    </div>
</div>

<style>
/* Staff Scheduling Tabs - Financial Reports Hub Style */
.staff-sched-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.staff-sched-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.staff-sched-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.staff-sched-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.staff-sched-tab i { font-size: 16px; }

/* Tab Content Container */
.staff-sched-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<!-- Tabs Navigation -->
<div class="staff-sched-tabs">
    <a href="?page=admin_staff_scheduling&tab=staff" class="staff-sched-tab <?= $activeTab === 'staff' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Staff & PINs
    </a>
    <a href="?page=admin_staff_scheduling&tab=schedules" class="staff-sched-tab <?= $activeTab === 'schedules' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> Schedules List
    </a>
    <a href="?page=admin_staff_scheduling&tab=calendar" class="staff-sched-tab <?= $activeTab === 'calendar' ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> Calendar View
    </a>
    <a href="?page=admin_staff_scheduling&tab=history" class="staff-sched-tab <?= $activeTab === 'history' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> History
    </a>
</div>

<style>
    .staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
    
    /* Schedule Table */
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
    
    .schedule-table tr:last-child td {
        border-bottom: none;
    }
    
    .schedule-table .actions {
        display: flex;
        gap: 8px;
    }
    
    .schedule-table .actions button {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    /* Calendar Styles */
    .calendar-container {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px;
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .calendar-title {
        font-size: 20px;
        font-weight: 700;
    }
    
    .calendar-nav {
        display: flex;
        gap: 8px;
    }
    
    .calendar-nav button {
        padding: 10px 15px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .calendar-nav button:hover {
        border-color: var(--primary);
        background: rgba(107, 70, 193, 0.1);
    }
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        background: var(--border);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .calendar-day-header {
        background: var(--bg);
        padding: 12px;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-dim);
        text-transform: uppercase;
    }
    
    .calendar-day {
        background: var(--bg-secondary);
        min-height: 120px;
        padding: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .calendar-day:hover {
        background: rgba(107, 70, 193, 0.1);
    }
    
    .calendar-day.other-month {
        opacity: 0.4;
    }
    
    .calendar-day.today {
        background: rgba(107, 70, 193, 0.15);
        border: 2px solid var(--primary);
    }
    
    .calendar-day.past {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .calendar-day-number {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .calendar-day.today .calendar-day-number {
        color: var(--primary);
    }
    
    .add-schedule-btn {
        width: 22px;
        height: 22px;
        background: var(--primary);
        border: none;
        border-radius: 50%;
        color: #fff;
        font-size: 12px;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.2s;
    }
    
    .calendar-day:hover .add-schedule-btn {
        opacity: 1;
    }
    
    .calendar-event {
        background: var(--primary);
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 4px;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .calendar-event:hover {
        background: var(--primary-hover);
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
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
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
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
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }
    
    .modal-close:hover {
        color: #fff;
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
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 14px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--primary);
        outline: none;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .form-hint {
        font-size: 11px;
        color: var(--text-dim);
        margin-top: 4px;
    }
    
    /* Add Button */
    .add-schedule-floating {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: var(--primary);
        border: none;
        border-radius: 50%;
        color: #fff;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(107, 70, 193, 0.4);
        transition: all 0.2s;
        z-index: 100;
    }
    
    .add-schedule-floating:hover {
        background: var(--primary-hover);
        transform: scale(1.1);
    }
    
    /* History styles */
    .history-filters {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .history-filters select {
        padding: 10px 15px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-secondary);
        border-radius: 16px;
        border: 1px solid var(--border);
    }
    
    .empty-state i {
        font-size: 48px;
        color: var(--text-dim);
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 18px;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: var(--text-dim);
        margin-bottom: 20px;
    }
</style>

<?php if ($activeTab === 'staff'): ?>
<!-- Staff & PINs Tab -->
<div class="staff-grid">
    <?php if (empty($staffMembers)): ?>
        <div class="empty-state" style="grid-column: 1 / -1;">
            <i class="fas fa-user-slash"></i>
            <h3>No Front Desk Staff</h3>
            <p>There are no front desk staff members to schedule. Add staff members first.</p>
        </div>
    <?php else: ?>
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
                    <div class="staff-stat">
                        <div class="value"><?= number_format($staff['monthly_hours'], 1) ?></div>
                        <div class="label">Hours (30 days)</div>
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
                    <button class="btn-primary" onclick="openScheduleModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>')">
                        <i class="fas fa-calendar-plus"></i> Add Shift
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'schedules'): ?>
<!-- Schedules List Tab -->
<?php if (empty($allSchedules)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h3>No Upcoming Schedules</h3>
        <p>Create schedules for your staff members.</p>
        <button class="btn-primary" onclick="openScheduleModal()">
            <i class="fas fa-plus"></i> Create Schedule
        </button>
    </div>
<?php else: ?>
    <table class="schedule-table">
        <thead>
            <tr>
                <th>Staff Member</th>
                <th>Date</th>
                <th>Time</th>
                <th>Lunch Break</th>
                <th>Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allSchedules as $schedule): 
                $scheduleDate = new DateTime($schedule['schedule_date']);
                $isToday = $schedule['schedule_date'] === date('Y-m-d');
            ?>
                <tr style="<?= $isToday ? 'background: rgba(107, 70, 193, 0.1);' : '' ?>">
                    <td>
                        <strong><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></strong>
                    </td>
                    <td>
                        <?= $scheduleDate->format('M j, Y') ?>
                        <?php if ($isToday): ?>
                            <span style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 10px; margin-left: 8px;">TODAY</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                    <td><?= isset($schedule['lunch_break_minutes']) ? $schedule['lunch_break_minutes'] . ' min' : '30 min' ?></td>
                    <td><?= htmlspecialchars($schedule['location'] ?? '-') ?></td>
                    <td class="actions">
                        <button class="btn-secondary btn-sm edit-schedule-btn" 
                                data-id="<?= $schedule['id'] ?>"
                                data-staff-id="<?= $schedule['staff_id'] ?>"
                                data-date="<?= $schedule['schedule_date'] ?>"
                                data-start="<?= $schedule['start_time'] ?>"
                                data-end="<?= $schedule['end_time'] ?>"
                                data-lunch="<?= $schedule['lunch_break_minutes'] ?? 30 ?>"
                                data-location="<?= htmlspecialchars($schedule['location'] ?? '') ?>"
                                data-notes="<?= htmlspecialchars($schedule['notes'] ?? '') ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-danger btn-sm delete-schedule-btn" data-id="<?= $schedule['id'] ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php elseif ($activeTab === 'calendar'): ?>
<!-- Calendar View Tab -->
<div class="calendar-container">
    <div class="calendar-header">
        <h3 class="calendar-title" id="calendar-month-title">Loading...</h3>
        <div class="calendar-nav">
            <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
            <button onclick="goToToday()">Today</button>
            <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
    
    <div class="calendar-grid" id="calendar-grid">
        <!-- Calendar will be generated by JavaScript -->
    </div>
</div>

<?php elseif ($activeTab === 'history'): ?>
<!-- History Tab -->
<div class="history-filters">
    <select id="history-staff-filter" onchange="filterHistory()">
        <option value="all">All Staff</option>
        <?php foreach ($staffMembers as $staff): ?>
            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="history-period-filter" onchange="filterHistory()">
        <option value="30">Last 30 Days</option>
        <option value="60">Last 60 Days</option>
        <option value="90" selected>Last 90 Days</option>
    </select>
</div>

<?php if (empty($historySchedules)): ?>
    <div class="empty-state">
        <i class="fas fa-history"></i>
        <h3>No Schedule History</h3>
        <p>Past schedules will appear here.</p>
    </div>
<?php else: ?>
    <table class="schedule-table" id="history-table">
        <thead>
            <tr>
                <th>Staff Member</th>
                <th>Date</th>
                <th>Time</th>
                <th>Lunch Break</th>
                <th>Location</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historySchedules as $schedule): 
                $scheduleDate = new DateTime($schedule['schedule_date']);
            ?>
                <tr data-staff-id="<?= $schedule['staff_id'] ?>" data-date="<?= $schedule['schedule_date'] ?>">
                    <td><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></td>
                    <td><?= $scheduleDate->format('M j, Y') ?></td>
                    <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                    <td><?= isset($schedule['lunch_break_minutes']) ? $schedule['lunch_break_minutes'] . ' min' : '30 min' ?></td>
                    <td><?= htmlspecialchars($schedule['location'] ?? '-') ?></td>
                    <td><?= $schedule['created_by_first'] ? htmlspecialchars($schedule['created_by_first'] . ' ' . $schedule['created_by_last']) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php endif; ?>

<!-- Floating Add Button -->
<button class="add-schedule-floating" onclick="openScheduleModal()" title="Add New Schedule">
    <i class="fas fa-plus"></i>
</button>

<!-- Schedule Modal -->
<div class="modal" id="schedule-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="schedule-modal-title"><i class="fas fa-calendar-plus" style="color: var(--primary); margin-right: 10px;"></i> Create Schedule</h3>
            <button class="modal-close" aria-label="Close modal" onclick="closeScheduleModal()">&times;</button>
        </div>
        
        <form id="schedule-form" onsubmit="submitSchedule(event)">
            <input type="hidden" id="schedule-id" name="schedule_id">
            
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
                <label>Lunch Break Length</label>
                <select name="lunch_break_minutes" id="schedule-lunch">
                    <option value="0">No Lunch Break</option>
                    <option value="15">15 minutes</option>
                    <option value="30" selected>30 minutes</option>
                    <option value="45">45 minutes</option>
                    <option value="60">60 minutes (1 hour)</option>
                    <option value="90">90 minutes</option>
                </select>
                <div class="form-hint">This is the scheduled lunch break duration. Actual break time is tracked separately.</div>
            </div>
            
            <div class="form-group">
                <label>Location (optional)</label>
                <input type="text" name="location" id="schedule-location" placeholder="e.g., Front Desk, Main Office">
            </div>
            
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" id="schedule-notes" rows="3" placeholder="Any special notes for this shift..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> <span id="schedule-submit-text">Create Schedule</span>
            </button>
        </form>
    </div>
</div>

<!-- PIN Modal -->
<div class="modal" id="pin-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Set Staff PIN</h3>
            <button class="modal-close" aria-label="Close modal" onclick="closePinModal()">&times;</button>
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
const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const schedulesByDate = <?= json_encode($schedulesByDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const staffMembers = <?= json_encode($staffMembers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentDate = new Date();
let editMode = false;

// PIN Modal Functions
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

// Schedule Modal Functions
function openScheduleModal(staffId = null, staffName = null, date = null) {
    editMode = false;
    document.getElementById('schedule-modal-title').innerHTML = '<i class="fas fa-calendar-plus" style="color: var(--primary); margin-right: 10px;"></i> Create Schedule';
    document.getElementById('schedule-submit-text').textContent = 'Create Schedule';
    document.getElementById('schedule-id').value = '';
    document.getElementById('schedule-form').reset();
    
    if (staffId) {
        document.getElementById('schedule-staff').value = staffId;
    }
    if (date) {
        document.getElementById('schedule-date').value = date;
    }
    
    document.getElementById('schedule-modal').classList.add('active');
}

function closeScheduleModal() {
    document.getElementById('schedule-modal').classList.remove('active');
}

function editSchedule(schedule) {
    editMode = true;
    document.getElementById('schedule-modal-title').innerHTML = '<i class="fas fa-edit" style="color: var(--primary); margin-right: 10px;"></i> Edit Schedule';
    document.getElementById('schedule-submit-text').textContent = 'Update Schedule';
    document.getElementById('schedule-id').value = schedule.id;
    document.getElementById('schedule-staff').value = schedule.staff_id;
    document.getElementById('schedule-date').value = schedule.schedule_date;
    document.getElementById('schedule-start').value = schedule.start_time;
    document.getElementById('schedule-end').value = schedule.end_time;
    document.getElementById('schedule-lunch').value = schedule.lunch_break_minutes || 30;
    document.getElementById('schedule-location').value = schedule.location || '';
    document.getElementById('schedule-notes').value = schedule.notes || '';
    
    document.getElementById('schedule-modal').classList.add('active');
}

function submitSchedule(e) {
    e.preventDefault();
    
    const scheduleId = document.getElementById('schedule-id').value;
    const formData = {
        action: scheduleId ? 'update_schedule' : 'create_schedule',
        staff_id: document.getElementById('schedule-staff').value,
        schedule_date: document.getElementById('schedule-date').value,
        start_time: document.getElementById('schedule-start').value,
        end_time: document.getElementById('schedule-end').value,
        lunch_break_minutes: document.getElementById('schedule-lunch').value,
        location: document.getElementById('schedule-location').value,
        notes: document.getElementById('schedule-notes').value,
        csrf_token: csrfToken
    };
    
    if (scheduleId) {
        formData.schedule_id = scheduleId;
    }
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(scheduleId ? 'Schedule updated successfully' : 'Schedule created successfully');
            closeScheduleModal();
            location.reload();
        } else {
            alert(data.message || 'Failed to save schedule');
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

// Calendar Functions
function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('calendar-month-title').textContent = monthNames[month] + ' ' + year;
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    let html = `
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
    `;
    
    // Previous month days
    const prevMonth = new Date(year, month, 0);
    for (let i = startDay - 1; i >= 0; i--) {
        const day = prevMonth.getDate() - i;
        html += `<div class="calendar-day other-month past"><div class="calendar-day-number">${day}</div></div>`;
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = dateStr === todayStr;
        const isPast = new Date(dateStr) < new Date(todayStr);
        const events = schedulesByDate[dateStr] || [];
        
        const classes = ['calendar-day'];
        if (isToday) classes.push('today');
        if (isPast && !isToday) classes.push('past');
        
        html += `<div class="${classes.join(' ')}" ${!isPast || isToday ? `data-date="${dateStr}"` : ''}>
            <div class="calendar-day-number">
                ${day}
                ${(!isPast || isToday) ? `<button class="add-schedule-btn" data-action="add" data-date="${dateStr}">+</button>` : ''}
            </div>`;
        
        events.forEach(event => {
            const startTime = event.start_time.substring(0, 5);
            const staffName = escapeHtml(event.first_name.substring(0, 1) + '. ' + event.last_name);
            const fullName = escapeHtml(event.first_name + ' ' + event.last_name);
            html += `<div class="calendar-event" data-schedule-id="${event.id}" title="${fullName}: ${startTime} - ${event.end_time.substring(0, 5)}">
                ${startTime} ${staffName}
            </div>`;
        });
        
        html += '</div>';
    }
    
    // Next month days
    const totalCells = startDay + daysInMonth;
    const remainingCells = (7 - (totalCells % 7)) % 7;
    for (let day = 1; day <= remainingCells; day++) {
        html += `<div class="calendar-day other-month"><div class="calendar-day-number">${day}</div></div>`;
    }
    
    document.getElementById('calendar-grid').innerHTML = html;
    
    // Attach event listeners
    attachCalendarEventListeners();
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Attach event listeners to calendar elements
function attachCalendarEventListeners() {
    // Day click handlers
    document.querySelectorAll('.calendar-day[data-date]').forEach(day => {
        day.addEventListener('click', function(e) {
            if (e.target.classList.contains('calendar-event') || e.target.classList.contains('add-schedule-btn')) {
                return;
            }
            openScheduleModal(null, null, this.dataset.date);
        });
    });
    
    // Add button handlers
    document.querySelectorAll('.add-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            openScheduleModal(null, null, this.dataset.date);
        });
    });
    
    // Schedule event handlers
    document.querySelectorAll('.calendar-event').forEach(event => {
        event.addEventListener('click', function(e) {
            e.stopPropagation();
            const scheduleId = this.dataset.scheduleId;
            // Find the schedule data from our loaded data
            for (const date in schedulesByDate) {
                const found = schedulesByDate[date].find(s => s.id == scheduleId);
                if (found) {
                    editSchedule(found);
                    return;
                }
            }
        });
    });
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

function goToToday() {
    currentDate = new Date();
    renderCalendar();
}

// History Filtering
function filterHistory() {
    const staffFilter = document.getElementById('history-staff-filter')?.value || 'all';
    const periodFilter = parseInt(document.getElementById('history-period-filter')?.value || 90);
    
    const rows = document.querySelectorAll('#history-table tbody tr');
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - periodFilter);
    
    rows.forEach(row => {
        const staffId = row.dataset.staffId;
        const rowDate = new Date(row.dataset.date);
        
        const staffMatch = staffFilter === 'all' || staffId === staffFilter;
        const dateMatch = rowDate >= cutoffDate;
        
        row.style.display = (staffMatch && dateMatch) ? '' : 'none';
    });
}

// Initialize calendar if on calendar tab
document.addEventListener('DOMContentLoaded', function() {
    const calendarGrid = document.getElementById('calendar-grid');
    if (calendarGrid) {
        renderCalendar();
    }
    
    // Attach event listeners for schedule list buttons
    document.querySelectorAll('.edit-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const schedule = {
                id: this.dataset.id,
                staff_id: this.dataset.staffId,
                schedule_date: this.dataset.date,
                start_time: this.dataset.start,
                end_time: this.dataset.end,
                lunch_break_minutes: this.dataset.lunch,
                location: this.dataset.location,
                notes: this.dataset.notes
            };
            editSchedule(schedule);
        });
    });
    
    document.querySelectorAll('.delete-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteSchedule(this.dataset.id);
        });
    });
});

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScheduleModal();
        closePinModal();
    }
});

// Close modals on backdrop click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeScheduleModal();
            closePinModal();
        }
    });
});
</script>
