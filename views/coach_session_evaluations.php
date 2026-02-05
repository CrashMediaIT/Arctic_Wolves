<!-- Coach Session Evaluations View -->
<!-- Shows list and calendar view of sessions with evaluations assigned -->

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-clipboard-check"></i> Session Evaluations</h1>
    <p class="page-description">View and manage evaluations assigned to sessions</p>
</div>

<?php
// Fetch sessions with evaluations - only upcoming sessions assigned to this coach (admins see all)
try {
    $eval_query = "
        SELECT s.id, s.title, s.session_date, s.duration_minutes, s.status as session_status,
               se.id as evaluation_id, se.name as evaluation_name, se.status as evaluation_status,
               se.created_at as evaluation_created,
               (SELECT COUNT(*) FROM session_evaluation_athletes WHERE session_evaluation_id = se.id) as athlete_count,
               COALESCE(l.name, 'TBD') as location_name
        FROM sessions s
        INNER JOIN session_evaluations se ON s.id = se.session_id
        LEFT JOIN locations l ON s.location_id = l.id
        LEFT JOIN session_coaches sc ON sc.session_id = s.id
        WHERE s.session_date >= CURDATE()
    ";
    $eval_params = [];
    
    if ($user_role !== 'admin') {
        $eval_query .= " AND (s.coach_id = ? OR sc.coach_id = ?)";
        $eval_params[] = $user_id;
        $eval_params[] = $user_id;
    }
    
    $eval_query .= " GROUP BY s.id, se.id ORDER BY s.session_date ASC";
    
    $stmt = $pdo->prepare($eval_query);
    $stmt->execute($eval_params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sessions for calendar (filter to current and future months)
    $calendar_sessions = array_filter($sessions, function($s) {
        return strtotime($s['session_date']) >= strtotime('-30 days');
    });
    
} catch (PDOException $e) {
    error_log("Session evaluations fetch error: " . $e->getMessage());
    $sessions = [];
    $calendar_sessions = [];
}

// Get status from URL for alerts
$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$activeView = $_GET['view'] ?? 'list';
?>

<?php if ($status && $message): ?>
<div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?>">
    <i class="fas fa-<?= $status === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="page-tabs-wrapper">
    <div class="page-tabs">
        <button type="button" class="page-tab <?= $activeView === 'list' ? 'active' : '' ?>" data-view="list" onclick="switchEvalView('list')">
            <i class="fas fa-list"></i> List View
        </button>
        <button type="button" class="page-tab <?= $activeView === 'calendar' ? 'active' : '' ?>" data-view="calendar" onclick="switchEvalView('calendar')">
            <i class="fas fa-calendar-alt"></i> Calendar View
        </button>
    </div>
    <?php if ($user_role === 'admin' || $user_role === 'coach_plus'): ?>
    <div class="page-tabs-action">
        <button type="button" class="btn btn-primary" onclick="openAssignModal()">
            <i class="fas fa-plus"></i> Assign Evaluation to Session
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="page-tab-content">
    <!-- List View -->
    <div id="list-view" class="view-container <?= $activeView === 'list' ? 'active' : '' ?>">
        <?php if (empty($sessions)): ?>
            <div class="empty-state-card">
                <i class="fas fa-clipboard-list"></i>
                <h4>No Session Evaluations Found</h4>
                <p>No evaluations have been assigned to sessions yet. Click "Assign Evaluation to Session" to get started.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Sessions with Evaluations</h3>
                    <span class="badge badge-primary"><?= count($sessions) ?> sessions</span>
                </div>
                <div class="card-body">
                    <div class="sessions-list">
                        <?php foreach ($sessions as $session): 
                            $date = strtotime($session['session_date']);
                            $is_past = $date < strtotime('today');
                            $is_today = date('Y-m-d', $date) === date('Y-m-d');
                        ?>
                            <div class="session-item <?= $is_past ? 'past' : '' ?> <?= $is_today ? 'today' : '' ?>">
                                <div class="session-date-col">
                                    <div class="date-display">
                                        <span class="day"><?= date('d', $date) ?></span>
                                        <span class="month"><?= date('M', $date) ?></span>
                                        <span class="year"><?= date('Y', $date) ?></span>
                                    </div>
                                    <span class="time"><?= date('g:i A', $date) ?></span>
                                </div>
                                <div class="session-info-col">
                                    <h4 class="session-title"><?= htmlspecialchars($session['title']) ?></h4>
                                    <?php if ($session['evaluation_name']): ?>
                                        <p class="evaluation-name"><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($session['evaluation_name']) ?></p>
                                    <?php endif; ?>
                                    <div class="session-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span>
                                        <span><i class="fas fa-users"></i> <?= $session['athlete_count'] ?> athletes</span>
                                        <span class="status-badge status-<?= $session['evaluation_status'] ?>">
                                            <?= ucfirst($session['evaluation_status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="session-actions-col">
                                    <a href="?page=session_evaluation_form&evaluation_id=<?= $session['evaluation_id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-clipboard-check"></i> Open Evaluation
                                    </a>
                                    <button class="btn btn-secondary" onclick="manageAthletes(<?= $session['evaluation_id'] ?>)">
                                        <i class="fas fa-user-plus"></i> Manage Athletes
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Calendar View -->
    <div id="calendar-view" class="view-container">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar"></i> Calendar View</h3>
                <div class="calendar-nav">
                    <button class="btn-icon" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <span id="current-month"></span>
                    <button class="btn-icon" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="calendar-grid">
                    <div class="calendar-header">
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                    </div>
                    <div id="calendar-body" class="calendar-body"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Evaluation Modal -->
<div id="assign-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Assign Evaluation to Session</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('assign-modal')">&times;</button>
        </div>
        <form id="assign-form" method="POST" action="process_session_evaluations.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="assign_evaluation_to_session">
            
            <div class="form-group">
                <label>Select Session *</label>
                <select name="session_id" id="session-select" class="form-select" required>
                    <option value="">-- Select Session --</option>
                </select>
                <p class="help-text">Only sessions without evaluations are shown</p>
            </div>
            
            <div class="form-group">
                <label>Evaluation Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g., Skills Assessment">
                <p class="help-text">Optional custom name for this evaluation</p>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-textarea" rows="3" placeholder="Add notes about this evaluation..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assign-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Evaluation</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Athletes Modal -->
<div id="athletes-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2><i class="fas fa-users"></i> Manage Athletes</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('athletes-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="current-evaluation-id">
            
            <!-- Add Athlete Tabs -->
            <div class="athlete-tabs">
                <button class="athlete-tab active" data-tab="existing">Add Existing User</button>
                <button class="athlete-tab" data-tab="manual">Add Manually</button>
                <button class="athlete-tab" data-tab="import">Import CSV</button>
            </div>
            
            <!-- Add Existing User -->
            <div id="tab-existing" class="athlete-tab-content active">
                <div class="info-box" style="margin-bottom: 16px;">
                    <p><i class="fas fa-info-circle"></i> Select from users already registered in the system. Users who are already added to this evaluation will not appear in the list.</p>
                </div>
                <form id="add-existing-form">
                    <div class="form-group">
                        <label>Select User from System</label>
                        <select id="existing-user-select" class="form-select">
                            <option value="">-- Select User --</option>
                        </select>
                        <p class="help-text">Shows athletes and other users registered in the system</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addExistingAthlete()">
                        <i class="fas fa-plus"></i> Add to Evaluation
                    </button>
                </form>
            </div>
            
            <!-- Add Manually -->
            <div id="tab-manual" class="athlete-tab-content">
                <div class="info-box" style="margin-bottom: 16px;">
                    <p><i class="fas fa-info-circle"></i> Add an athlete who is not yet in the system. If you provide an email, a user account will be created automatically.</p>
                </div>
                <form id="add-manual-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" id="manual-first-name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" id="manual-last-name" class="form-input" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="manual-email" class="form-input">
                            <p class="help-text">If provided, a user account will be created</p>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" id="manual-dob" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="manual-notes" class="form-textarea" rows="2"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addManualAthlete()">
                        <i class="fas fa-plus"></i> Add Athlete
                    </button>
                </form>
            </div>
            
            <!-- Import CSV -->
            <div id="tab-import" class="athlete-tab-content">
                <div class="import-info">
                    <p><i class="fas fa-info-circle"></i> Upload a CSV file with athlete information.</p>
                    <p>Required columns: <strong>first_name, last_name</strong></p>
                    <p>Optional columns: email, date_of_birth, notes</p>
                    <a href="process_session_evaluations.php?action=download_csv_template" class="btn btn-secondary btn-sm">
                        <i class="fas fa-download"></i> Download Template
                    </a>
                </div>
                <form id="import-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>CSV File</label>
                        <input type="file" id="csv-file" accept=".csv" class="form-input">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="importCSV()">
                        <i class="fas fa-upload"></i> Import Athletes
                    </button>
                </form>
                <div id="import-results" class="import-results" style="display: none;"></div>
            </div>
            
            <!-- Current Athletes List -->
            <div class="athletes-list-section">
                <h4><i class="fas fa-users"></i> Athletes in this Evaluation</h4>
                <div id="athletes-list" class="athletes-list">
                    <p class="loading">Loading athletes...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Session Evaluations Styles */
.session-evaluations-content {
    max-width: 1400px;
    margin: 0 auto;
}

/* Alert Styles */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* View Toggle */
.view-toggle-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.view-toggle {
    display: flex;
    gap: 8px;
}

.view-btn {
    padding: 10px 20px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    color: var(--text, #A8A8B8);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
}

.view-btn:hover {
    border-color: var(--primary, #6B46C1);
    color: var(--primary-light, #8B5CF6);
}

.view-btn.active {
    background: var(--primary, #6B46C1);
    border-color: var(--primary, #6B46C1);
    color: #fff;
}

/* View Container */
.view-container {
    display: none;
}

.view-container.active {
    display: block;
}

/* Sessions List */
.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.session-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 24px;
    padding: 20px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    transition: all 0.3s ease;
    align-items: center;
}

.session-item:hover {
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.15);
}

.session-item.past {
    opacity: 0.7;
}

.session-item.today {
    border-color: var(--primary, #6B46C1);
    background: rgba(107, 70, 193, 0.05);
}

.session-date-col {
    text-align: center;
    min-width: 80px;
}

.date-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 16px;
    background: rgba(107, 70, 193, 0.15);
    border-radius: 10px;
}

.date-display .day {
    font-size: 28px;
    font-weight: 800;
    color: var(--primary-light, #8B5CF6);
    line-height: 1;
}

.date-display .month {
    font-size: 14px;
    font-weight: 700;
    color: var(--text, #A8A8B8);
    text-transform: uppercase;
}

.date-display .year {
    font-size: 12px;
    color: var(--text-dim, #6B6B7B);
}

.session-date-col .time {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    color: var(--text-dim, #6B6B7B);
}

.session-info-col {
    flex: 1;
}

.session-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0 0 6px 0;
}

.evaluation-name {
    font-size: 14px;
    color: var(--primary-light, #8B5CF6);
    margin: 0 0 10px 0;
}

.evaluation-name i {
    margin-right: 6px;
}

.session-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 13px;
    color: var(--text-dim, #6B6B7B);
}

.session-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.session-meta i {
    color: var(--primary, #6B46C1);
}

.session-actions-col {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Status Badges */
.status-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-draft {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.status-active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.status-completed {
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
}

/* Calendar Styles */
.calendar-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

#current-month {
    font-size: 16px;
    font-weight: 700;
    min-width: 150px;
    text-align: center;
}

.calendar-grid {
    background: var(--bg-main, #0A0A0F);
    border-radius: 8px;
    overflow: hidden;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: rgba(107, 70, 193, 0.1);
}

.calendar-day-header {
    padding: 12px;
    text-align: center;
    font-weight: 700;
    font-size: 12px;
    color: var(--text, #A8A8B8);
    text-transform: uppercase;
}

.calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    min-height: 100px;
    padding: 8px;
    border: 1px solid var(--border, #2D2D3F);
    position: relative;
}

.calendar-day.other-month {
    background: rgba(0, 0, 0, 0.2);
}

.calendar-day.today {
    background: rgba(107, 70, 193, 0.1);
}

.calendar-day-number {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim, #6B6B7B);
    margin-bottom: 6px;
}

.calendar-day.today .calendar-day-number {
    color: var(--primary-light, #8B5CF6);
}

.calendar-event {
    padding: 4px 8px;
    margin-bottom: 4px;
    background: var(--primary, #6B46C1);
    border-radius: 4px;
    font-size: 11px;
    color: #fff;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-event:hover {
    background: var(--primary-hover, #7C3AED);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-content.modal-large {
    max-width: 800px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h2 i {
    color: var(--primary, #6B46C1);
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-dim, #6B6B7B);
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}

.modal-close:hover {
    color: var(--text-white, #fff);
}

.modal-body {
    padding: 24px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--border, #2D2D3F);
}

/* Athlete Tabs */
.athlete-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    padding-bottom: 16px;
}

.athlete-tab {
    padding: 10px 16px;
    background: transparent;
    border: 1px solid var(--border, #2D2D3F);
    color: var(--text, #A8A8B8);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.athlete-tab:hover {
    border-color: var(--primary, #6B46C1);
    color: var(--primary-light, #8B5CF6);
}

.athlete-tab.active {
    background: var(--primary, #6B46C1);
    border-color: var(--primary, #6B46C1);
    color: #fff;
}

.athlete-tab-content {
    display: none;
    margin-bottom: 24px;
}

.athlete-tab-content.active {
    display: block;
}

/* Form Styles */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim, #6B6B7B);
    margin-bottom: 8px;
    text-transform: uppercase;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: var(--text-white, #fff);
    font-size: 14px;
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.help-text {
    font-size: 12px;
    color: var(--text-dim, #6B6B7B);
    margin-top: 6px;
}

/* Import Info */
.import-info {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}

.import-info p {
    margin: 0 0 8px 0;
    font-size: 13px;
    color: var(--text, #A8A8B8);
}

.import-info p:last-of-type {
    margin-bottom: 12px;
}

.import-results {
    margin-top: 16px;
    padding: 12px;
    background: var(--bg-main, #0A0A0F);
    border-radius: 8px;
}

/* Info Box */
.info-box {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 12px 16px;
}

.info-box p {
    margin: 0;
    font-size: 13px;
    color: var(--text, #A8A8B8);
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.info-box p i {
    color: var(--primary-light, #8B5CF6);
    margin-top: 2px;
}

/* Athletes List */
.athletes-list-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border, #2D2D3F);
}

.athletes-list-section h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.athletes-list-section h4 i {
    color: var(--primary, #6B46C1);
}

.athletes-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
}

.athlete-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
}

.athlete-item:hover {
    border-color: var(--primary, #6B46C1);
}

.athlete-info {
    display: flex;
    flex-direction: column;
}

.athlete-name {
    font-weight: 600;
    color: var(--text-white, #fff);
}

.athlete-email {
    font-size: 12px;
    color: var(--text-dim, #6B6B7B);
}

.athlete-actions button {
    padding: 6px 12px;
    font-size: 12px;
}

/* Placeholder */
.placeholder-container {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 60px 24px;
    text-align: center;
}

.placeholder-container h3 {
    font-size: 20px;
    color: var(--text-white, #fff);
    margin-bottom: 12px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary, #6B46C1);
    opacity: 0.5;
    display: block;
    margin-bottom: 20px;
}

.placeholder-text {
    font-size: 14px;
    color: var(--text-dim, #6B6B7B);
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 768px) {
    .session-item {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .session-date-col {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .session-actions-col {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .view-toggle-row {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
// Switch eval view function
function switchEvalView(view) {
    document.querySelectorAll('.page-tabs .page-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.view-container').forEach(v => v.classList.remove('active'));
    
    document.querySelector('.page-tabs .page-tab[data-view="' + view + '"]').classList.add('active');
    document.getElementById(view + '-view').classList.add('active');
    
    if (view === 'calendar') {
        initCalendar();
    }
}

// Athlete tabs
document.querySelectorAll('.athlete-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.athlete-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.athlete-tab-content').forEach(c => c.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Calendar data
const calendarSessions = <?= json_encode(array_values($calendar_sessions)) ?>;
let currentDate = new Date();

function initCalendar() {
    renderCalendar();
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    document.getElementById('current-month').textContent = 
        currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    let html = '';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Previous month days
    const prevMonth = new Date(year, month, 0);
    const prevDays = prevMonth.getDate();
    for (let i = startDay - 1; i >= 0; i--) {
        html += `<div class="calendar-day other-month">
            <span class="calendar-day-number">${prevDays - i}</span>
        </div>`;
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const isToday = date.getTime() === today.getTime();
        const dateStr = date.toISOString().split('T')[0];
        
        // Find sessions for this day
        const daySessions = calendarSessions.filter(s => {
            const sessionDate = new Date(s.session_date).toISOString().split('T')[0];
            return sessionDate === dateStr;
        });
        
        html += `<div class="calendar-day ${isToday ? 'today' : ''}">
            <span class="calendar-day-number">${day}</span>`;
        
        daySessions.forEach(session => {
            html += `<div class="calendar-event" onclick="window.location='?page=session_evaluation_form&evaluation_id=${session.evaluation_id}'">
                ${session.title}
            </div>`;
        });
        
        html += '</div>';
    }
    
    // Next month days
    const totalCells = Math.ceil((startDay + daysInMonth) / 7) * 7;
    const remaining = totalCells - (startDay + daysInMonth);
    for (let i = 1; i <= remaining; i++) {
        html += `<div class="calendar-day other-month">
            <span class="calendar-day-number">${i}</span>
        </div>`;
    }
    
    document.getElementById('calendar-body').innerHTML = html;
}

// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

async function openAssignModal() {
    // Load available sessions (sessions without evaluations)
    try {
        const response = await fetch('process_session_evaluations.php?action=get_available_sessions');
        const data = await response.json();
        
        const select = document.getElementById('session-select');
        select.innerHTML = '<option value="">-- Select Session --</option>';
        
        if (data.success && data.sessions) {
            data.sessions.forEach(session => {
                const date = new Date(session.session_date);
                const option = document.createElement('option');
                option.value = session.id;
                option.textContent = `${session.title} - ${date.toLocaleDateString()} ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} @ ${session.location_name}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading sessions:', error);
    }
    
    openModal('assign-modal');
}

async function manageAthletes(evaluationId) {
    document.getElementById('current-evaluation-id').value = evaluationId;
    
    // Load existing athletes
    await loadAthletes(evaluationId);
    
    // Load available users
    await loadAvailableUsers();
    
    openModal('athletes-modal');
}

async function loadAthletes(evaluationId) {
    const list = document.getElementById('athletes-list');
    list.innerHTML = '<p class="loading">Loading athletes...</p>';
    
    try {
        const response = await fetch(`process_session_evaluations.php?action=get_evaluation_details&evaluation_id=${evaluationId}`);
        const data = await response.json();
        
        if (data.success && data.athletes) {
            if (data.athletes.length === 0) {
                list.innerHTML = '<p class="text-muted">No athletes added yet</p>';
            } else {
                list.innerHTML = data.athletes.map(athlete => `
                    <div class="athlete-item">
                        <div class="athlete-info">
                            <span class="athlete-name">${athlete.first_name} ${athlete.last_name}</span>
                            ${athlete.email ? `<span class="athlete-email">${athlete.email}</span>` : ''}
                        </div>
                        <div class="athlete-actions">
                            <button class="btn btn-danger btn-sm" onclick="removeAthlete(${athlete.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        list.innerHTML = '<p class="text-danger">Error loading athletes</p>';
    }
}

async function loadAvailableUsers() {
    const evaluationId = document.getElementById('current-evaluation-id').value;
    try {
        const response = await fetch(`process_session_evaluations.php?action=get_existing_users&evaluation_id=${evaluationId}`);
        const data = await response.json();
        
        const select = document.getElementById('existing-user-select');
        select.innerHTML = '<option value="">-- Select User --</option>';
        
        if (data.success && data.users) {
            data.users.forEach(user => {
                if (!user.already_added) {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.first_name} ${user.last_name}${user.email ? ' (' + user.email + ')' : ''} - ${user.role}`;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

async function addExistingAthlete() {
    const evaluationId = document.getElementById('current-evaluation-id').value;
    const userId = document.getElementById('existing-user-select').value;
    
    if (!userId) {
        alert('Please select an athlete');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_athlete');
    formData.append('evaluation_id', evaluationId);
    formData.append('user_id', userId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    try {
        const response = await fetch('process_session_evaluations.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await loadAthletes(evaluationId);
            document.getElementById('existing-user-select').value = '';
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error adding athlete');
    }
}

async function addManualAthlete() {
    const evaluationId = document.getElementById('current-evaluation-id').value;
    const firstName = document.getElementById('manual-first-name').value.trim();
    const lastName = document.getElementById('manual-last-name').value.trim();
    const email = document.getElementById('manual-email').value.trim();
    const dob = document.getElementById('manual-dob').value;
    const notes = document.getElementById('manual-notes').value.trim();
    
    if (!firstName || !lastName) {
        alert('First name and last name are required');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_athlete');
    formData.append('evaluation_id', evaluationId);
    formData.append('first_name', firstName);
    formData.append('last_name', lastName);
    formData.append('email', email);
    formData.append('date_of_birth', dob);
    formData.append('notes', notes);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    try {
        const response = await fetch('process_session_evaluations.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await loadAthletes(evaluationId);
            // Clear form
            document.getElementById('manual-first-name').value = '';
            document.getElementById('manual-last-name').value = '';
            document.getElementById('manual-email').value = '';
            document.getElementById('manual-dob').value = '';
            document.getElementById('manual-notes').value = '';
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error adding athlete');
    }
}

async function importCSV() {
    const evaluationId = document.getElementById('current-evaluation-id').value;
    const fileInput = document.getElementById('csv-file');
    
    if (!fileInput.files.length) {
        alert('Please select a CSV file');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'import_athletes_csv');
    formData.append('evaluation_id', evaluationId);
    formData.append('csv_file', fileInput.files[0]);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    const resultsDiv = document.getElementById('import-results');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<p>Importing...</p>';
    
    try {
        const response = await fetch('process_session_evaluations.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            resultsDiv.innerHTML = `<p class="text-success">${data.message}</p>`;
            if (data.errors && data.errors.length > 0) {
                resultsDiv.innerHTML += '<ul class="text-warning">' + 
                    data.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
            }
            await loadAthletes(evaluationId);
            fileInput.value = '';
        } else {
            resultsDiv.innerHTML = `<p class="text-danger">${data.message}</p>`;
        }
    } catch (error) {
        resultsDiv.innerHTML = '<p class="text-danger">Error importing CSV</p>';
    }
}

async function removeAthlete(athleteId) {
    if (!confirm('Are you sure you want to remove this athlete from the evaluation?')) {
        return;
    }
    
    const evaluationId = document.getElementById('current-evaluation-id').value;
    const formData = new FormData();
    formData.append('action', 'remove_athlete');
    formData.append('athlete_id', athleteId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    try {
        const response = await fetch('process_session_evaluations.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            await loadAthletes(evaluationId);
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error removing athlete');
    }
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>
