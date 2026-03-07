<?php
/**
 * Development Programs - Coaches Corner View
 * Only visible to users with goalie_dev or player_dev roles
 * Shows enrolled athletes and allows managing drills, communication, video review, and appointments
 */

// Access control: only goalie_dev, player_dev, or admin
$allowed = false;
if (isset($user_roles_list) && is_array($user_roles_list)) {
    $allowed = array_intersect(['goalie_dev', 'player_dev', 'admin'], $user_roles_list);
}
if (!$allowed) {
    echo '<div style="text-align:center;padding:60px 20px;color:var(--text-dim);"><i class="fas fa-lock" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i><h3>Access Denied</h3><p>You need goalie_dev or player_dev role to access this page.</p></div>';
    return;
}

$user_id = $_SESSION['user_id'] ?? 0;
$isGoalieDev = in_array('goalie_dev', $user_roles_list);
$isPlayerDev = in_array('player_dev', $user_roles_list);
$isAdmin = in_array('admin', $user_roles_list);

// Determine which programs this coach manages
$program_types = [];
if ($isGoalieDev || $isAdmin) $program_types[] = 'goalie_dev';
if ($isPlayerDev || $isAdmin) $program_types[] = 'player_dev';

$placeholders = implode(',', array_fill(0, count($program_types), '?'));

// Get enrolled athletes
$athletes_stmt = $pdo->prepare("
    SELECT dpe.*, u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count,
           (SELECT COUNT(*) FROM development_program_messages dpm WHERE dpm.enrollment_id = dpe.id) as message_count,
           (SELECT COUNT(*) FROM development_program_videos dpv WHERE dpv.enrollment_id = dpe.id AND dpv.status = 'pending_review') as pending_video_count
    FROM development_program_enrollments dpe
    JOIN users u ON dpe.athlete_id = u.id
    WHERE dpe.program_type IN ($placeholders) AND dpe.status = 'active'
    ORDER BY dpe.enrolled_at DESC
");
$athletes_stmt->execute($program_types);
$athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);

// If decryption function exists, use it
if (function_exists('decryptUserRows')) {
    $athletes = decryptUserRows($athletes);
}

// Get selected athlete detail
$selected_enrollment_id = isset($_GET['enrollment_id']) ? (int)$_GET['enrollment_id'] : 0;
$selected = null;
$selected_drills = [];
$selected_messages = [];
$selected_videos = [];
$selected_appointments = [];

if ($selected_enrollment_id) {
    // Verify access
    $sel_stmt = $pdo->prepare("
        SELECT dpe.*, u.first_name, u.last_name
        FROM development_program_enrollments dpe
        JOIN users u ON dpe.athlete_id = u.id
        WHERE dpe.id = ? AND dpe.program_type IN ($placeholders)
    ");
    $sel_stmt->execute(array_merge([$selected_enrollment_id], $program_types));
    $selected = $sel_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected) {
        if (function_exists('decryptUserRows')) {
            $selected = decryptUserRows([$selected])[0];
        }
        
        // Get assigned drills
        $drills_stmt = $pdo->prepare("
            SELECT dpd.*, d.title as drill_title, d.description as drill_description,
                   d.video_url as drill_video_url, d.custom_image,
                   u.first_name as coach_first, u.last_name as coach_last
            FROM development_program_drills dpd
            JOIN drills d ON dpd.drill_id = d.id
            JOIN users u ON dpd.assigned_by = u.id
            WHERE dpd.enrollment_id = ?
            ORDER BY dpd.sort_order, dpd.created_at
        ");
        $drills_stmt->execute([$selected_enrollment_id]);
        $selected_drills = $drills_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get messages
        $msgs_stmt = $pdo->prepare("
            SELECT dpm.*, u.first_name as sender_first, u.last_name as sender_last
            FROM development_program_messages dpm
            JOIN users u ON dpm.sender_id = u.id
            WHERE dpm.enrollment_id = ?
            ORDER BY dpm.created_at ASC
        ");
        $msgs_stmt->execute([$selected_enrollment_id]);
        $selected_messages = $msgs_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get athlete-uploaded videos
        $videos_stmt = $pdo->prepare("
            SELECT dpv.*, d.title as drill_title
            FROM development_program_videos dpv
            LEFT JOIN development_program_drills dpd ON dpv.drill_assignment_id = dpd.id
            LEFT JOIN drills d ON dpd.drill_id = d.id
            WHERE dpv.enrollment_id = ?
            ORDER BY dpv.created_at DESC
        ");
        $videos_stmt->execute([$selected_enrollment_id]);
        $selected_videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get appointments
        $appts_stmt = $pdo->prepare("
            SELECT da.*, u.first_name as coach_first, u.last_name as coach_last
            FROM development_appointments da
            JOIN users u ON da.coach_id = u.id
            WHERE da.enrollment_id = ?
            ORDER BY da.appointment_date DESC, da.appointment_time DESC
        ");
        $appts_stmt->execute([$selected_enrollment_id]);
        $selected_appointments = $appts_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get drill library for adding drills
$all_drills = $pdo->query("SELECT id, title, category_id FROM drills ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

// Get locations for appointment form
$locations = $pdo->query("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.dev-coach-container { display: flex; gap: 24px; min-height: 600px; }
.dev-athlete-list { width: 320px; flex-shrink: 0; }
.dev-athlete-detail { flex: 1; min-width: 0; }
.dev-athlete-card {
    display: block; background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44); border-radius: 10px;
    padding: 14px 16px; margin-bottom: 8px; text-decoration: none;
    color: var(--text-white, #e2e8f0); transition: all 0.2s;
}
.dev-athlete-card:hover, .dev-athlete-card.active {
    border-color: var(--primary, #6B46C1); background: rgba(107, 70, 193, 0.08);
}
.dev-athlete-card .athlete-name { font-weight: 600; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
.dev-athlete-card .athlete-meta { font-size: 12px; color: var(--text-dim, #94a3b8); margin-top: 4px; }
.dev-athlete-card .video-notify {
    background: #ef4444; color: #fff; border-radius: 10px; font-size: 11px;
    padding: 1px 7px; font-weight: 700; margin-left: 6px;
}
.dev-program-badge {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 11px; font-weight: 600;
}
.dev-program-badge.goalie_dev { background: rgba(59,130,246,0.15); color: #3b82f6; }
.dev-program-badge.player_dev { background: rgba(16,185,129,0.15); color: #10b981; }
.detail-panel {
    background: var(--bg-card, #1a1a2e); border: 1px solid var(--border, #2d2d44);
    border-radius: 12px; padding: 24px;
}
.detail-panel h3 { font-size: 18px; font-weight: 700; color: var(--text-white, #e2e8f0); margin-bottom: 20px; }
.detail-section-title {
    font-size: 14px; font-weight: 600; color: var(--text-white, #e2e8f0);
    margin: 20px 0 10px; padding-top: 16px;
    border-top: 1px solid var(--border, #2d2d44);
}
.detail-section-title:first-of-type { border-top: none; margin-top: 0; padding-top: 0; }
.detail-section-title i { margin-right: 6px; }
.drill-mgmt-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.drill-mgmt-item {
    display: flex; justify-content: space-between; align-items: center;
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 8px; padding: 12px 16px;
}
.drill-mgmt-item h4 { font-size: 14px; font-weight: 600; color: var(--text-white, #e2e8f0); margin: 0; }
.drill-mgmt-item .drill-meta { font-size: 11px; color: var(--text-dim); margin-top: 2px; }
.drill-mgmt-actions { display: flex; gap: 8px; align-items: center; }
.drill-mgmt-actions button {
    padding: 6px 12px; border-radius: 6px; font-size: 12px;
    font-weight: 600; cursor: pointer; border: none; transition: all 0.2s;
}
.btn-sm-primary { background: var(--primary, #6B46C1); color: #fff; }
.btn-sm-danger { background: transparent; border: 1px solid #ef4444 !important; color: #ef4444; }
.btn-sm-danger:hover { background: #ef4444; color: #fff; }
.add-drill-row { display: flex; gap: 8px; margin-bottom: 20px; }
.add-drill-row select, .add-drill-row input[type="text"] {
    flex: 1; padding: 10px; background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44); border-radius: 8px;
    color: var(--text-white, #e2e8f0); font-size: 13px;
}
.add-drill-row button {
    padding: 10px 16px; background: var(--primary, #6B46C1); color: #fff;
    border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
    font-size: 13px; white-space: nowrap;
}
/* Athlete Videos Section */
.dev-video-review-list { display: flex; flex-direction: column; gap: 10px; }
.dev-video-review-item {
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 8px; padding: 14px 16px;
}
.dev-video-review-item .video-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.dev-video-review-item .video-header h5 { font-size: 14px; font-weight: 600; color: var(--text-white, #e2e8f0); margin: 0; }
.dev-video-review-item .video-meta { font-size: 12px; color: var(--text-dim, #94a3b8); margin-bottom: 8px; }
.dev-video-review-item .video-actions { display: flex; gap: 8px; align-items: center; }
.dev-video-review-item .video-actions a {
    color: var(--primary, #6B46C1); font-size: 13px; font-weight: 600; text-decoration: none;
}
.coach-video-status {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.coach-video-status.pending_review { background: rgba(245,158,11,0.15); color: #f59e0b; }
.coach-video-status.reviewed { background: rgba(59,130,246,0.15); color: #3b82f6; }
.coach-video-status.feedback_given { background: rgba(16,185,129,0.15); color: #10b981; }
/* Appointment Section */
.appt-form-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;
}
.appt-form-grid label { display: block; font-size: 12px; font-weight: 600; color: var(--text-dim, #94a3b8); margin-bottom: 3px; }
.appt-form-grid input, .appt-form-grid select, .appt-form-grid textarea {
    width: 100%; padding: 9px 12px; background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44); border-radius: 8px;
    color: var(--text-white, #e2e8f0); font-size: 13px;
}
.appt-form-grid .full-width { grid-column: 1 / -1; }
.appt-form-grid textarea { min-height: 50px; resize: vertical; }
.appt-list { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.appt-item {
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;
}
.appt-item .appt-info h5 { font-size: 13px; font-weight: 600; color: var(--text-white, #e2e8f0); margin: 0 0 3px; }
.appt-item .appt-info span { font-size: 12px; color: var(--text-dim, #94a3b8); }
.appt-type-badge {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.appt-type-badge.call { background: rgba(16,185,129,0.15); color: #10b981; }
.appt-type-badge.video_call { background: rgba(59,130,246,0.15); color: #3b82f6; }
.appt-type-badge.in_person { background: rgba(245,158,11,0.15); color: #f59e0b; }
.appt-status-badge { font-size: 11px; font-weight: 600; }
.appt-status-badge.scheduled { color: #3b82f6; }
.appt-status-badge.completed { color: #10b981; }
.appt-status-badge.cancelled { color: #ef4444; }
/* Chat */
.dev-chat-section { margin-top: 20px; border-top: 1px solid var(--border, #2d2d44); padding-top: 16px; }
.dev-chat-messages { max-height: 350px; overflow-y: auto; margin-bottom: 12px; }
.dev-chat-msg { padding: 8px 12px; margin-bottom: 6px; border-radius: 8px; font-size: 13px; }
.dev-chat-msg.from-coach { background: rgba(107,70,193,0.1); border-left: 3px solid var(--primary, #6B46C1); }
.dev-chat-msg.from-athlete { background: rgba(59,130,246,0.1); border-left: 3px solid #3b82f6; }
.dev-chat-msg .msg-meta { font-size: 11px; color: var(--text-dim); margin-bottom: 4px; }
.dev-chat-input { display: flex; gap: 8px; }
.dev-chat-input input {
    flex: 1; padding: 10px 14px; background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44); border-radius: 8px;
    color: var(--text-white, #e2e8f0); font-size: 13px;
}
.dev-chat-input button {
    padding: 10px 16px; background: var(--primary, #6B46C1); color: #fff;
    border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.video-upload-row { display: flex; gap: 8px; margin-top: 8px; }
.video-upload-row input[type="text"] {
    flex: 1; padding: 8px 12px; background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44); border-radius: 8px;
    color: var(--text-white, #e2e8f0); font-size: 12px;
}
.video-upload-row button {
    padding: 8px 14px; background: #3b82f6; color: #fff; border: none;
    border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600;
}
@media (max-width: 768px) {
    .dev-coach-container { flex-direction: column; }
    .dev-athlete-list { width: 100%; }
    .appt-form-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-hockey-puck"></i> Development Programs</h1>
    <p class="page-description">Manage athlete development programs, assign drills, and communicate with athletes</p>
</div>

<div class="dev-coach-container">
    <!-- Athlete List -->
    <div class="dev-athlete-list">
        <h3 style="font-size:15px;font-weight:700;color:var(--text-white);margin-bottom:12px;">Enrolled Athletes (<?= count($athletes) ?>)</h3>
        <?php if (empty($athletes)): ?>
            <p style="color:var(--text-dim);font-size:13px;text-align:center;padding:20px;">No athletes enrolled yet.</p>
        <?php else: ?>
            <?php foreach ($athletes as $a): ?>
            <a href="?page=development_programs&enrollment_id=<?= (int)$a['id'] ?>" class="dev-athlete-card <?= $selected_enrollment_id == $a['id'] ? 'active' : '' ?>">
                <div class="athlete-name">
                    <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                    <?php if ((int)($a['pending_video_count'] ?? 0) > 0): ?>
                    <span class="video-notify"><?= (int)$a['pending_video_count'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="athlete-meta">
                    <span class="dev-program-badge <?= htmlspecialchars($a['program_type']) ?>">
                        <?= $a['program_type'] === 'goalie_dev' ? 'Goalie Dev' : 'Player Dev' ?>
                    </span>
                    &bull; <?= (int)$a['drill_count'] ?> drills &bull; <?= (int)$a['message_count'] ?> messages
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Detail Panel -->
    <div class="dev-athlete-detail">
        <?php if (!$selected): ?>
            <div class="detail-panel" style="text-align:center;padding:60px 20px;">
                <i class="fas fa-user-friends" style="font-size:48px;color:var(--text-dim);opacity:0.5;display:block;margin-bottom:16px;"></i>
                <p style="color:var(--text-dim);">Select an athlete from the list to manage their program.</p>
            </div>
        <?php else: ?>
            <div class="detail-panel">
                <h3>
                    <?= htmlspecialchars($selected['first_name'] . ' ' . $selected['last_name']) ?>
                    <span class="dev-program-badge <?= htmlspecialchars($selected['program_type']) ?>" style="font-size:12px;vertical-align:middle;">
                        <?= $selected['program_type'] === 'goalie_dev' ? 'Goalie Dev' : 'Player Dev' ?>
                    </span>
                </h3>

                <!-- Add Drill -->
                <h4 class="detail-section-title"><i class="fas fa-plus-circle"></i> Add Drill from Library</h4>
                <div class="add-drill-row">
                    <select id="drill-select">
                        <option value="">Select a drill...</option>
                        <?php foreach ($all_drills as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="drill-notes" placeholder="Coach notes (optional)" style="flex:0.6;">
                    <button onclick="addDrill(<?= (int)$selected['id'] ?>)"><i class="fas fa-plus"></i> Add</button>
                </div>

                <!-- Assigned Drills -->
                <h4 class="detail-section-title"><i class="fas fa-clipboard-list"></i> Assigned Drills (<?= count($selected_drills) ?>)</h4>
                <?php if (empty($selected_drills)): ?>
                    <p style="color:var(--text-dim);font-size:13px;margin-bottom:20px;">No drills assigned. Use the selector above to add drills from the library.</p>
                <?php else: ?>
                    <div class="drill-mgmt-list">
                    <?php foreach ($selected_drills as $sd): ?>
                        <div class="drill-mgmt-item">
                            <div>
                                <h4><?= htmlspecialchars($sd['drill_title']) ?></h4>
                                <div class="drill-meta">
                                    Status: <span class="drill-status <?= htmlspecialchars($sd['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($sd['status'])) ?></span>
                                    <?php if ($sd['coach_notes']): ?> &bull; <?= htmlspecialchars(substr($sd['coach_notes'], 0, 80)) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="drill-mgmt-actions">
                                <button class="btn-sm-primary" onclick="updateDrillStatus(<?= (int)$sd['id'] ?>, '<?= $sd['status'] === 'assigned' ? 'in_progress' : 'completed' ?>')">
                                    <?= $sd['status'] === 'assigned' ? 'Start' : ($sd['status'] === 'in_progress' ? 'Complete' : '✓') ?>
                                </button>
                                <button class="btn-sm-danger" onclick="removeDrill(<?= (int)$sd['id'] ?>, <?= (int)$selected['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Athlete Uploaded Videos -->
                <h4 class="detail-section-title"><i class="fas fa-film"></i> Athlete Videos (<?= count($selected_videos) ?>)</h4>
                <?php if (empty($selected_videos)): ?>
                    <p style="color:var(--text-dim);font-size:13px;margin-bottom:20px;">No videos uploaded by this athlete yet.</p>
                <?php else: ?>
                    <div class="dev-video-review-list">
                    <?php foreach ($selected_videos as $vid): ?>
                        <div class="dev-video-review-item">
                            <div class="video-header">
                                <h5><?= htmlspecialchars($vid['title']) ?></h5>
                                <span class="coach-video-status <?= htmlspecialchars($vid['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($vid['status'])) ?></span>
                            </div>
                            <div class="video-meta">
                                <?= date('M j, Y g:ia', strtotime($vid['created_at'])) ?>
                                <?php if (!empty($vid['drill_title'])): ?> &bull; Drill: <?= htmlspecialchars($vid['drill_title']) ?><?php endif; ?>
                                <?php if (!empty($vid['description'])): ?> &bull; <?= htmlspecialchars(substr($vid['description'], 0, 100)) ?><?php endif; ?>
                            </div>
                            <div class="video-actions">
                                <?php if ($vid['video_url']): ?>
                                <a href="<?= htmlspecialchars($vid['video_url']) ?>" target="_blank"><i class="fas fa-play-circle"></i> Watch</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Schedule Appointment -->
                <h4 class="detail-section-title"><i class="fas fa-calendar-plus"></i> Schedule Appointment</h4>
                <div class="appt-form-grid" id="appointment-form">
                    <div>
                        <label for="appt-type">Type *</label>
                        <select id="appt-type" onchange="toggleApptFields()">
                            <option value="call">Phone Call</option>
                            <option value="video_call">Video Call</option>
                            <option value="in_person">In Person</option>
                        </select>
                    </div>
                    <div>
                        <label for="appt-title">Title *</label>
                        <input type="text" id="appt-title" placeholder="e.g. Progress Review">
                    </div>
                    <div>
                        <label for="appt-date">Date *</label>
                        <input type="date" id="appt-date" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label for="appt-time">Time *</label>
                        <input type="time" id="appt-time">
                    </div>
                    <div>
                        <label for="appt-duration">Duration (min)</label>
                        <input type="number" id="appt-duration" value="30" min="5" max="480">
                    </div>
                    <div id="appt-location-wrap">
                        <label for="appt-location">Location</label>
                        <input type="text" id="appt-location" placeholder="Meeting location">
                    </div>
                    <div id="appt-url-wrap" style="display:none;">
                        <label for="appt-meeting-url">Meeting URL</label>
                        <input type="url" id="appt-meeting-url" placeholder="https://zoom.us/...">
                    </div>
                    <div id="appt-phone-wrap" style="display:none;">
                        <label for="appt-phone">Phone Number</label>
                        <input type="text" id="appt-phone" placeholder="Phone number">
                    </div>
                    <div class="full-width">
                        <label for="appt-notes">Notes</label>
                        <textarea id="appt-notes" placeholder="Optional notes..."></textarea>
                    </div>
                    <div class="full-width">
                        <button style="padding:10px 20px;background:var(--primary,#6B46C1);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;" onclick="createAppointment(<?= (int)$selected['id'] ?>, <?= (int)$selected['athlete_id'] ?>)">
                            <i class="fas fa-calendar-check"></i> Schedule Appointment
                        </button>
                    </div>
                </div>

                <!-- Existing Appointments -->
                <?php if (!empty($selected_appointments)): ?>
                <div class="appt-list">
                    <?php foreach ($selected_appointments as $appt): ?>
                    <div class="appt-item">
                        <div class="appt-info">
                            <h5>
                                <?= htmlspecialchars($appt['title']) ?>
                                <span class="appt-type-badge <?= htmlspecialchars($appt['appointment_type']) ?>"><?= str_replace('_', ' ', htmlspecialchars($appt['appointment_type'])) ?></span>
                            </h5>
                            <span>
                                <?= date('M j, Y', strtotime($appt['appointment_date'])) ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                                (<?= (int)$appt['duration_minutes'] ?> min)
                                <?php if ($appt['location']): ?> &bull; <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($appt['location']) ?><?php endif; ?>
                                &bull; <span class="appt-status-badge <?= htmlspecialchars($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span>
                            </span>
                        </div>
                        <?php if ($appt['status'] === 'scheduled'): ?>
                        <button class="btn-sm-danger" onclick="cancelAppointment(<?= (int)$appt['id'] ?>, <?= (int)$selected['id'] ?>)"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Chat / Communication -->
                <div class="dev-chat-section">
                    <h4 style="font-size:14px;font-weight:600;color:var(--text-white);margin-bottom:12px;"><i class="fas fa-comments"></i> Communication</h4>
                    <div class="dev-chat-messages">
                        <?php if (empty($selected_messages)): ?>
                            <p style="color:var(--text-dim);font-size:13px;text-align:center;padding:20px;">No messages yet.</p>
                        <?php else: ?>
                            <?php foreach ($selected_messages as $m): ?>
                            <div class="dev-chat-msg <?= $m['sender_id'] == $user_id ? 'from-coach' : 'from-athlete' ?>">
                                <div class="msg-meta"><?= htmlspecialchars($m['sender_first'] . ' ' . $m['sender_last']) ?> &bull; <?= date('M j, g:ia', strtotime($m['created_at'])) ?></div>
                                <?= htmlspecialchars($m['message']) ?>
                                <?php if ($m['video_url']): ?>
                                    <div style="margin-top:4px;"><a href="<?= htmlspecialchars($m['video_url']) ?>" target="_blank" style="color:var(--primary);font-size:12px;"><i class="fas fa-video"></i> Watch Video</a></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dev-chat-input">
                        <input type="text" id="coach-msg-input" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendCoachMessage(<?= (int)$selected['id'] ?>)">
                        <button onclick="sendCoachMessage(<?= (int)$selected['id'] ?>)"><i class="fas fa-paper-plane"></i> Send</button>
                    </div>
                    <div class="video-upload-row">
                        <input type="text" id="coach-video-url" placeholder="Paste video URL to share with athlete...">
                        <button onclick="sendCoachVideo(<?= (int)$selected['id'] ?>)"><i class="fas fa-video"></i> Send Video</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const devHeaders = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-Token': csrfToken
};

function devPost(data) {
    return fetch('process_development_programs.php', {
        method: 'POST', headers: devHeaders, body: JSON.stringify(data)
    }).then(r => r.json());
}

function addDrill(enrollmentId) {
    const drillId = document.getElementById('drill-select').value;
    const notes = document.getElementById('drill-notes').value;
    if (!drillId) { alert('Please select a drill.'); return; }
    devPost({ action: 'add_drill', enrollment_id: enrollmentId, drill_id: drillId, coach_notes: notes })
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

function removeDrill(drillAssignmentId, enrollmentId) {
    if (!confirm('Remove this drill?')) return;
    devPost({ action: 'remove_drill', drill_assignment_id: drillAssignmentId, enrollment_id: enrollmentId })
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

function updateDrillStatus(drillAssignmentId, newStatus) {
    devPost({ action: 'update_drill_status', drill_assignment_id: drillAssignmentId, status: newStatus })
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

function sendCoachMessage(enrollmentId) {
    const input = document.getElementById('coach-msg-input');
    const message = input.value.trim();
    if (!message) return;
    devPost({ action: 'send_message', enrollment_id: enrollmentId, message: message })
    .then(d => { if (d.success) { input.value = ''; location.reload(); } else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

function sendCoachVideo(enrollmentId) {
    const urlInput = document.getElementById('coach-video-url');
    const videoUrl = urlInput.value.trim();
    if (!videoUrl) { alert('Please enter a video URL.'); return; }
    devPost({ action: 'send_message', enrollment_id: enrollmentId, message: 'Shared a video', video_url: videoUrl })
    .then(d => { if (d.success) { urlInput.value = ''; location.reload(); } else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

function toggleApptFields() {
    const type = document.getElementById('appt-type').value;
    document.getElementById('appt-location-wrap').style.display = type === 'in_person' ? '' : 'none';
    document.getElementById('appt-url-wrap').style.display = type === 'video_call' ? '' : 'none';
    document.getElementById('appt-phone-wrap').style.display = type === 'call' ? '' : 'none';
}

function createAppointment(enrollmentId, athleteId) {
    const data = {
        action: 'create_appointment',
        enrollment_id: enrollmentId,
        athlete_id: athleteId,
        appointment_type: document.getElementById('appt-type').value,
        title: document.getElementById('appt-title').value,
        appointment_date: document.getElementById('appt-date').value,
        appointment_time: document.getElementById('appt-time').value,
        duration_minutes: parseInt(document.getElementById('appt-duration').value) || 30,
        location: document.getElementById('appt-location')?.value || '',
        meeting_url: document.getElementById('appt-meeting-url')?.value || '',
        phone_number: document.getElementById('appt-phone')?.value || '',
        description: document.getElementById('appt-notes')?.value || ''
    };
    if (!data.title || !data.appointment_date || !data.appointment_time) {
        alert('Please fill in title, date and time.');
        return;
    }
    devPost(data).then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

function cancelAppointment(appointmentId, enrollmentId) {
    if (!confirm('Cancel this appointment?')) return;
    devPost({ action: 'cancel_appointment', appointment_id: appointmentId })
    .then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); }).catch(() => alert('Error'));
}

toggleApptFields();
</script>
