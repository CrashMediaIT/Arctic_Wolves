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
        if (function_exists('decryptUserRows')) {
            $selected_drills = decryptUserRows($selected_drills);
        }
        
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
        if (function_exists('decryptUserRows')) {
            $selected_messages = decryptUserRows($selected_messages);
        }

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
        if (function_exists('decryptUserRows')) {
            $selected_appointments = decryptUserRows($selected_appointments);
        }
    }
}

// Get drill library for adding drills
$all_drills = $pdo->query("SELECT id, title, category_id FROM drills ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

// Get locations for appointment form
$locations = $pdo->query("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* ========================================
   Development Programs - Coach Management
   ======================================== */

/* Layout: Sidebar + Detail */
.dev-coach-container {
    display: flex;
    gap: var(--space-6, 24px);
    min-height: 600px;
    align-items: flex-start;
}

/* --- Athlete Sidebar --- */
.dev-athlete-list {
    width: 300px;
    flex-shrink: 0;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl, 12px);
    overflow: hidden;
}
.dev-athlete-list-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
}
.dev-athlete-list-header h3 {
    font-size: var(--font-size-base, 14px);
    font-weight: var(--font-weight-bold, 700);
    color: var(--text-white, #FFFFFF);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dev-athlete-list-header h3 .count-badge {
    background: var(--primary, #6B46C1);
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}
.dev-athlete-list-body {
    padding: 8px;
    max-height: 560px;
    overflow-y: auto;
}
.dev-athlete-list-body::-webkit-scrollbar { width: 4px; }
.dev-athlete-list-body::-webkit-scrollbar-thumb { background: var(--border, #2D2D3F); border-radius: 4px; }
.dev-athlete-card {
    display: block;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--radius-lg, 8px);
    padding: 12px 14px;
    margin-bottom: 4px;
    text-decoration: none;
    color: var(--text-white, #FFFFFF);
    transition: all var(--transition-normal, 0.2s ease);
}
.dev-athlete-card:hover {
    background: rgba(107, 70, 193, 0.06);
    border-color: var(--border-light, #3A3A4F);
}
.dev-athlete-card.active {
    background: rgba(107, 70, 193, 0.1);
    border-color: var(--primary, #6B46C1);
}
.dev-athlete-card .athlete-name {
    font-weight: var(--font-weight-semibold, 600);
    font-size: var(--font-size-base, 14px);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dev-athlete-card .athlete-meta {
    font-size: var(--font-size-sm, 12px);
    color: var(--text-dim, #A8A8B8);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.dev-athlete-card .video-notify {
    background: var(--error, #EF4444);
    color: #fff;
    border-radius: 10px;
    font-size: 11px;
    padding: 1px 7px;
    font-weight: 700;
    margin-left: 6px;
    animation: pulse-notify 2s ease-in-out infinite;
}
@keyframes pulse-notify {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Program Badges */
.dev-program-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.dev-program-badge.goalie_dev { background: rgba(59,130,246,0.15); color: #3b82f6; }
.dev-program-badge.player_dev { background: rgba(16,185,129,0.15); color: #10b981; }

/* --- Detail Panel --- */
.dev-athlete-detail { flex: 1; min-width: 0; }

.detail-panel {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl, 12px);
    overflow: hidden;
}

/* Athlete Header */
.detail-athlete-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.detail-athlete-header h3 {
    font-size: var(--font-size-lg, 18px);
    font-weight: var(--font-weight-bold, 700);
    color: var(--text-white, #FFFFFF);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.detail-header-stats {
    display: flex;
    gap: 16px;
}
.detail-header-stat {
    text-align: center;
    padding: 6px 14px;
    background: var(--bg-main, #0A0A0F);
    border-radius: var(--radius-lg, 8px);
    border: 1px solid var(--border, #2D2D3F);
}
.detail-header-stat .stat-val {
    font-size: var(--font-size-md, 16px);
    font-weight: var(--font-weight-bold, 700);
    color: var(--text-white, #FFFFFF);
    display: block;
}
.detail-header-stat .stat-lbl {
    font-size: 11px;
    color: var(--text-dim, #A8A8B8);
    text-transform: uppercase;
    font-weight: 600;
}

/* Tab Navigation */
.detail-tabs {
    display: flex;
    border-bottom: 1px solid var(--border, #2D2D3F);
    background: rgba(13, 13, 20, 0.3);
    overflow-x: auto;
}
.detail-tab {
    flex: 1;
    padding: 14px 16px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-dim, #A8A8B8);
    font-size: var(--font-size-sm, 12px);
    font-weight: var(--font-weight-semibold, 600);
    cursor: pointer;
    transition: all var(--transition-slow, 0.3s ease);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.detail-tab:hover {
    background: rgba(107, 70, 193, 0.05);
    color: var(--text-white, #FFFFFF);
}
.detail-tab.active {
    background: rgba(107, 70, 193, 0.08);
    color: var(--primary, #6B46C1);
    border-bottom-color: var(--primary, #6B46C1);
}
.detail-tab .tab-count {
    background: var(--border, #2D2D3F);
    color: var(--text-dim, #A8A8B8);
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 8px;
    font-weight: 700;
}
.detail-tab.active .tab-count {
    background: rgba(107, 70, 193, 0.2);
    color: var(--primary, #6B46C1);
}

/* Tab Content */
.detail-tab-content {
    padding: 24px;
    display: none;
    animation: fadeInTab 0.25s ease;
}
.detail-tab-content.active { display: block; }
@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Section Titles */
.detail-section-title {
    font-size: var(--font-size-base, 14px);
    font-weight: var(--font-weight-semibold, 600);
    color: var(--text-white, #FFFFFF);
    margin: 24px 0 12px;
    padding-top: 20px;
    border-top: 1px solid var(--border, #2D2D3F);
}
.detail-section-title:first-child,
.detail-section-title--first {
    border-top: none;
    margin-top: 0;
    padding-top: 0;
}
.detail-section-title i { margin-right: 6px; color: var(--primary, #6B46C1); }

/* --- Drill Management --- */
.add-drill-row {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    align-items: flex-end;
}
.add-drill-field { flex: 1; }
.add-drill-field label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-dim, #A8A8B8);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.add-drill-row select, .add-drill-row input[type="text"] {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    color: var(--text-white, #FFFFFF);
    font-size: var(--font-size-sm, 13px);
    transition: border-color var(--transition-normal, 0.2s ease);
}
.add-drill-row select:focus, .add-drill-row input[type="text"]:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}
.add-drill-row button {
    padding: 10px 18px;
    background: var(--primary, #6B46C1);
    color: #fff;
    border: none;
    border-radius: var(--radius-lg, 8px);
    cursor: pointer;
    font-weight: 600;
    font-size: var(--font-size-sm, 13px);
    white-space: nowrap;
    transition: all var(--transition-normal, 0.2s ease);
    display: inline-flex;
    align-items: center;
    align-self: flex-end;
    gap: 6px;
}
.add-drill-row button:hover {
    background: var(--primary-hover, #7C3AED);
    transform: translateY(-1px);
    box-shadow: var(--shadow-primary, 0 4px 12px rgba(107, 70, 193, 0.3));
}

.drill-mgmt-list { display: flex; flex-direction: column; gap: 10px; }
.drill-mgmt-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    padding: 14px 18px;
    transition: border-color var(--transition-normal, 0.2s ease);
}
.drill-mgmt-item:hover { border-color: var(--border-light, #3A3A4F); }
.drill-mgmt-item h4 {
    font-size: var(--font-size-base, 14px);
    font-weight: var(--font-weight-semibold, 600);
    color: var(--text-white, #FFFFFF);
    margin: 0;
}
.drill-mgmt-item .drill-meta {
    font-size: 11px;
    color: var(--text-dim, #A8A8B8);
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.drill-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}
.drill-status.assigned { background: rgba(59,130,246,0.15); color: #3b82f6; }
.drill-status.in_progress { background: rgba(245,158,11,0.15); color: #f59e0b; }
.drill-status.completed { background: rgba(16,185,129,0.15); color: #10b981; }
.drill-mgmt-actions { display: flex; gap: 8px; align-items: center; }
.drill-mgmt-actions button {
    padding: 7px 14px;
    border-radius: var(--radius-md, 6px);
    font-size: var(--font-size-sm, 12px);
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all var(--transition-normal, 0.2s ease);
}
.btn-sm-primary {
    background: var(--primary, #6B46C1);
    color: #fff;
}
.btn-sm-primary:hover {
    background: var(--primary-hover, #7C3AED);
    transform: translateY(-1px);
}
.btn-sm-danger {
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
    color: #ef4444;
}
.btn-sm-danger:hover {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444 !important;
}

/* --- Video Review --- */
.dev-video-review-list { display: flex; flex-direction: column; gap: 10px; }
.dev-video-review-item {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    padding: 16px 18px;
    transition: border-color var(--transition-normal, 0.2s ease);
}
.dev-video-review-item:hover { border-color: var(--border-light, #3A3A4F); }
.dev-video-review-item .video-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.dev-video-review-item .video-header h5 {
    font-size: var(--font-size-base, 14px);
    font-weight: var(--font-weight-semibold, 600);
    color: var(--text-white, #FFFFFF);
    margin: 0;
}
.dev-video-review-item .video-meta {
    font-size: var(--font-size-sm, 12px);
    color: var(--text-dim, #A8A8B8);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.dev-video-review-item .video-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.dev-video-review-item .video-actions a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary, #6B46C1);
    font-size: var(--font-size-sm, 13px);
    font-weight: 600;
    text-decoration: none;
    border-radius: var(--radius-md, 6px);
    transition: all var(--transition-normal, 0.2s ease);
}
.dev-video-review-item .video-actions a:hover {
    background: rgba(107, 70, 193, 0.2);
}
.coach-video-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.coach-video-status.pending_review { background: rgba(245,158,11,0.15); color: #f59e0b; }
.coach-video-status.reviewed { background: rgba(59,130,246,0.15); color: #3b82f6; }
.coach-video-status.feedback_given { background: rgba(16,185,129,0.15); color: #10b981; }

/* --- Appointment Section --- */
.appt-form-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    padding: 20px;
    margin-bottom: 20px;
}
.appt-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.appt-form-grid label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-dim, #A8A8B8);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.appt-form-grid input, .appt-form-grid select, .appt-form-grid textarea {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    color: var(--text-white, #FFFFFF);
    font-size: var(--font-size-sm, 13px);
    transition: border-color var(--transition-normal, 0.2s ease);
    box-sizing: border-box;
}
.appt-form-grid input:focus, .appt-form-grid select:focus, .appt-form-grid textarea:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}
.appt-form-grid .full-width { grid-column: 1 / -1; }
.appt-form-grid textarea { min-height: 60px; resize: vertical; }
.appt-form-submit {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
.appt-form-submit button {
    padding: 10px 22px;
    background: var(--primary, #6B46C1);
    color: #fff;
    border: none;
    border-radius: var(--radius-lg, 8px);
    cursor: pointer;
    font-weight: 600;
    font-size: var(--font-size-sm, 13px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all var(--transition-normal, 0.2s ease);
}
.appt-form-submit button:hover {
    background: var(--primary-hover, #7C3AED);
    transform: translateY(-1px);
    box-shadow: var(--shadow-primary, 0 4px 12px rgba(107, 70, 193, 0.3));
}

/* Existing Appointments */
.appt-list { display: flex; flex-direction: column; gap: 10px; }
.appt-item {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    padding: 14px 18px;
    display: flex;
    gap: 14px;
    align-items: center;
    transition: border-color var(--transition-normal, 0.2s ease);
}
.appt-item:hover { border-color: var(--border-light, #3A3A4F); }
.appt-date-box {
    min-width: 52px;
    text-align: center;
    padding: 8px 10px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), #8b5cf6);
    border-radius: var(--radius-lg, 8px);
    color: #fff;
    flex-shrink: 0;
}
.appt-date-box .appt-day {
    font-size: 18px;
    font-weight: var(--font-weight-bold, 700);
    display: block;
    line-height: 1.1;
}
.appt-date-box .appt-month {
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.appt-item .appt-info { flex: 1; }
.appt-item .appt-info h5 {
    font-size: var(--font-size-sm, 13px);
    font-weight: var(--font-weight-semibold, 600);
    color: var(--text-white, #FFFFFF);
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.appt-item .appt-info span {
    font-size: var(--font-size-sm, 12px);
    color: var(--text-dim, #A8A8B8);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.appt-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.appt-type-badge.call { background: rgba(16,185,129,0.15); color: #10b981; }
.appt-type-badge.video_call { background: rgba(59,130,246,0.15); color: #3b82f6; }
.appt-type-badge.in_person { background: rgba(245,158,11,0.15); color: #f59e0b; }
.appt-status-badge {
    font-size: 11px;
    font-weight: 600;
}
.appt-status-badge.scheduled { color: #3b82f6; }
.appt-status-badge.completed { color: #10b981; }
.appt-status-badge.cancelled { color: #ef4444; }

/* --- Chat / Communication --- */
.dev-chat-section { padding: 0; }
.dev-chat-messages {
    max-height: 380px;
    overflow-y: auto;
    margin-bottom: 16px;
    padding: 4px 0;
}
.dev-chat-messages::-webkit-scrollbar { width: 4px; }
.dev-chat-messages::-webkit-scrollbar-thumb { background: var(--border, #2D2D3F); border-radius: 4px; }
.dev-chat-msg {
    padding: 10px 14px;
    margin-bottom: 8px;
    border-radius: var(--radius-lg, 8px);
    font-size: var(--font-size-sm, 13px);
    line-height: 1.5;
}
.dev-chat-msg.from-coach {
    background: rgba(107,70,193,0.08);
    border-left: 3px solid var(--primary, #6B46C1);
}
.dev-chat-msg.from-athlete {
    background: rgba(59,130,246,0.08);
    border-left: 3px solid #3b82f6;
}
.dev-chat-msg .msg-meta {
    font-size: 11px;
    color: var(--text-dim, #A8A8B8);
    margin-bottom: 4px;
    font-weight: 500;
}
.dev-chat-input {
    display: flex;
    gap: 8px;
}
.dev-chat-input input {
    flex: 1;
    padding: 11px 16px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    color: var(--text-white, #FFFFFF);
    font-size: var(--font-size-sm, 13px);
    transition: border-color var(--transition-normal, 0.2s ease);
}
.dev-chat-input input:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}
.dev-chat-input button {
    padding: 11px 18px;
    background: var(--primary, #6B46C1);
    color: #fff;
    border: none;
    border-radius: var(--radius-lg, 8px);
    cursor: pointer;
    font-weight: 600;
    font-size: var(--font-size-sm, 13px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all var(--transition-normal, 0.2s ease);
}
.dev-chat-input button:hover {
    background: var(--primary-hover, #7C3AED);
}
.video-upload-row {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border, #2D2D3F);
}
.video-upload-row input[type="text"] {
    flex: 1;
    padding: 10px 14px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    color: var(--text-white, #FFFFFF);
    font-size: var(--font-size-sm, 12px);
    transition: border-color var(--transition-normal, 0.2s ease);
}
.video-upload-row input[type="text"]:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}
.video-upload-row button {
    padding: 10px 16px;
    background: var(--info, #3b82f6);
    color: #fff;
    border: none;
    border-radius: var(--radius-lg, 8px);
    cursor: pointer;
    font-size: var(--font-size-sm, 12px);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all var(--transition-normal, 0.2s ease);
}
.video-upload-row button:hover {
    background: #2563eb;
}

/* --- Empty States --- */
.dev-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-dim, #A8A8B8);
}
.dev-empty-state i {
    font-size: 36px;
    display: block;
    margin-bottom: 12px;
    opacity: 0.4;
}
.dev-empty-state--lg { padding: 60px 20px; }
.dev-empty-state--lg i { font-size: 48px; }
.dev-empty-state p {
    font-size: var(--font-size-sm, 13px);
    margin: 0;
    line-height: 1.5;
}

/* --- Responsive --- */
@media (max-width: 900px) {
    .dev-coach-container { flex-direction: column; }
    .dev-athlete-list { width: 100%; }
    .dev-athlete-list-body { max-height: 200px; }
    .detail-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .detail-tab { min-width: 0; padding: 12px 10px; font-size: 11px; }
    .detail-header-stats { gap: 8px; }
    .detail-header-stat { padding: 4px 10px; }
}
@media (max-width: 600px) {
    .detail-athlete-header { flex-direction: column; align-items: flex-start; }
    .detail-header-stats { width: 100%; justify-content: space-between; }
    .appt-form-grid { grid-template-columns: 1fr; }
    .add-drill-row { flex-direction: column; }
    .add-drill-field { width: 100%; }
    .dev-chat-input { flex-direction: column; }
    .video-upload-row { flex-direction: column; }
}
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-hockey-puck"></i> Development Programs</h1>
    <p class="page-description">Manage athlete development programs, assign drills, and communicate with athletes</p>
</div>

<div class="dev-coach-container">
    <!-- Athlete List -->
    <div class="dev-athlete-list">
        <div class="dev-athlete-list-header">
            <h3><i class="fas fa-users"></i> Enrolled Athletes <span class="count-badge"><?= count($athletes) ?></span></h3>
        </div>
        <div class="dev-athlete-list-body">
        <?php if (empty($athletes)): ?>
            <div class="dev-empty-state">
                <i class="fas fa-user-plus"></i>
                <p>No athletes enrolled yet.</p>
            </div>
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
                    <span><?= (int)$a['drill_count'] ?> drills</span>
                    <span><?= (int)$a['message_count'] ?> messages</span>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Detail Panel -->
    <div class="dev-athlete-detail">
        <?php if (!$selected): ?>
            <div class="detail-panel">
                <div class="dev-empty-state dev-empty-state--lg">
                    <i class="fas fa-user-friends"></i>
                    <p>Select an athlete from the list to manage their program.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="detail-panel">
                <!-- Athlete Header -->
                <div class="detail-athlete-header">
                    <h3>
                        <?php if ($selected['program_type'] === 'goalie_dev'): ?>
                            <i class="fas fa-shield-alt" style="color:#3b82f6;"></i>
                        <?php else: ?>
                            <i class="fas fa-hockey-puck" style="color:#10b981;"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($selected['first_name'] . ' ' . $selected['last_name']) ?>
                        <span class="dev-program-badge <?= htmlspecialchars($selected['program_type']) ?>">
                            <?= $selected['program_type'] === 'goalie_dev' ? 'Goalie Dev' : 'Player Dev' ?>
                        </span>
                    </h3>
                    <div class="detail-header-stats">
                        <div class="detail-header-stat">
                            <span class="stat-val"><?= count($selected_drills) ?></span>
                            <span class="stat-lbl">Drills</span>
                        </div>
                        <div class="detail-header-stat">
                            <span class="stat-val"><?= count($selected_videos) ?></span>
                            <span class="stat-lbl">Videos</span>
                        </div>
                        <div class="detail-header-stat">
                            <span class="stat-val"><?= count($selected_appointments) ?></span>
                            <span class="stat-lbl">Appts</span>
                        </div>
                        <div class="detail-header-stat">
                            <span class="stat-val"><?= count($selected_messages) ?></span>
                            <span class="stat-lbl">Msgs</span>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="detail-tabs">
                    <button class="detail-tab active" data-tab="drills" onclick="switchDetailTab('drills')">
                        <i class="fas fa-clipboard-list"></i> Drills <span class="tab-count"><?= count($selected_drills) ?></span>
                    </button>
                    <button class="detail-tab" data-tab="videos" onclick="switchDetailTab('videos')">
                        <i class="fas fa-film"></i> Videos <span class="tab-count"><?= count($selected_videos) ?></span>
                    </button>
                    <button class="detail-tab" data-tab="appointments" onclick="switchDetailTab('appointments')">
                        <i class="fas fa-calendar-alt"></i> Appointments <span class="tab-count"><?= count($selected_appointments) ?></span>
                    </button>
                    <button class="detail-tab" data-tab="communication" onclick="switchDetailTab('communication')">
                        <i class="fas fa-comments"></i> Communication <span class="tab-count"><?= count($selected_messages) ?></span>
                    </button>
                </div>

                <!-- ======== DRILLS TAB ======== -->
                <div class="detail-tab-content active" id="tab-drills">
                    <h4 class="detail-section-title detail-section-title--first"><i class="fas fa-plus-circle"></i> Add Drill from Library</h4>
                    <div class="add-drill-row">
                        <div class="add-drill-field" style="flex:1;">
                            <label for="drill-select">Drill</label>
                            <select id="drill-select">
                                <option value="">Select a drill...</option>
                                <?php foreach ($all_drills as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="add-drill-field" style="flex:0.6;">
                            <label for="drill-notes">Coach Notes</label>
                            <input type="text" id="drill-notes" placeholder="Optional notes...">
                        </div>
                        <button onclick="addDrill(<?= (int)$selected['id'] ?>)"><i class="fas fa-plus"></i> Add</button>
                    </div>

                    <h4 class="detail-section-title"><i class="fas fa-clipboard-list"></i> Assigned Drills (<?= count($selected_drills) ?>)</h4>
                    <?php if (empty($selected_drills)): ?>
                        <div class="dev-empty-state">
                            <i class="fas fa-clipboard"></i>
                            <p>No drills assigned yet. Use the selector above to add drills from the library.</p>
                        </div>
                    <?php else: ?>
                        <div class="drill-mgmt-list">
                        <?php foreach ($selected_drills as $sd): ?>
                            <div class="drill-mgmt-item">
                                <div>
                                    <h4><?= htmlspecialchars($sd['drill_title']) ?></h4>
                                    <div class="drill-meta">
                                        <span class="drill-status <?= htmlspecialchars($sd['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($sd['status'])) ?></span>
                                        <?php if ($sd['coach_notes']): ?><span><?= htmlspecialchars(substr($sd['coach_notes'], 0, 80)) ?></span><?php endif; ?>
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
                </div>

                <!-- ======== VIDEOS TAB ======== -->
                <div class="detail-tab-content" id="tab-videos">
                    <h4 class="detail-section-title detail-section-title--first"><i class="fas fa-film"></i> Athlete Videos (<?= count($selected_videos) ?>)</h4>
                    <?php if (empty($selected_videos)): ?>
                        <div class="dev-empty-state">
                            <i class="fas fa-video-slash"></i>
                            <p>No videos uploaded by this athlete yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="dev-video-review-list">
                        <?php foreach ($selected_videos as $vid): ?>
                            <div class="dev-video-review-item">
                                <div class="video-header">
                                    <h5><?= htmlspecialchars($vid['title']) ?></h5>
                                    <span class="coach-video-status <?= htmlspecialchars($vid['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($vid['status'])) ?></span>
                                </div>
                                <div class="video-meta">
                                    <i class="fas fa-clock"></i> <?= date('M j, Y g:ia', strtotime($vid['created_at'])) ?>
                                    <?php if (!empty($vid['drill_title'])): ?><span>&bull; <i class="fas fa-clipboard-list"></i> <?= htmlspecialchars($vid['drill_title']) ?></span><?php endif; ?>
                                    <?php if (!empty($vid['description'])): ?><span>&bull; <?= htmlspecialchars(substr($vid['description'], 0, 100)) ?></span><?php endif; ?>
                                </div>
                                <div class="video-actions">
                                    <?php if ($vid['video_url']): ?>
                                    <a href="<?= htmlspecialchars($vid['video_url']) ?>" target="_blank"><i class="fas fa-play-circle"></i> Watch Video</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ======== APPOINTMENTS TAB ======== -->
                <div class="detail-tab-content" id="tab-appointments">
                    <h4 class="detail-section-title detail-section-title--first"><i class="fas fa-calendar-plus"></i> Schedule Appointment</h4>
                    <div class="appt-form-card">
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
                        </div>
                        <div class="appt-form-submit">
                            <button onclick="createAppointment(<?= (int)$selected['id'] ?>, <?= (int)$selected['athlete_id'] ?>)">
                                <i class="fas fa-calendar-check"></i> Schedule Appointment
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($selected_appointments)): ?>
                    <h4 class="detail-section-title"><i class="fas fa-calendar-alt"></i> Scheduled Appointments</h4>
                    <div class="appt-list">
                        <?php foreach ($selected_appointments as $appt): ?>
                        <div class="appt-item">
                            <div class="appt-date-box">
                                <span class="appt-day"><?= date('j', strtotime($appt['appointment_date'])) ?></span>
                                <span class="appt-month"><?= date('M', strtotime($appt['appointment_date'])) ?></span>
                            </div>
                            <div class="appt-info">
                                <h5>
                                    <?= htmlspecialchars($appt['title']) ?>
                                    <span class="appt-type-badge <?= htmlspecialchars($appt['appointment_type']) ?>"><?= str_replace('_', ' ', htmlspecialchars($appt['appointment_type'])) ?></span>
                                </h5>
                                <span>
                                    <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                                    (<?= (int)$appt['duration_minutes'] ?> min)
                                    <?php if ($appt['location']): ?> &bull; <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($appt['location']) ?><?php endif; ?>
                                    &bull; <span class="appt-status-badge <?= htmlspecialchars($appt['status']) ?>"><?= htmlspecialchars($appt['status']) ?></span>
                                </span>
                            </div>
                            <?php if ($appt['status'] === 'scheduled'): ?>
                            <button class="btn-sm-danger" onclick="cancelAppointment(<?= (int)$appt['id'] ?>, <?= (int)$selected['id'] ?>)"><i class="fas fa-times"></i> Cancel</button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ======== COMMUNICATION TAB ======== -->
                <div class="detail-tab-content" id="tab-communication">
                    <div class="dev-chat-section">
                        <div class="dev-chat-messages">
                            <?php if (empty($selected_messages)): ?>
                                <div class="dev-empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>No messages yet. Start the conversation below.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($selected_messages as $m): ?>
                                <div class="dev-chat-msg <?= $m['sender_id'] == $user_id ? 'from-coach' : 'from-athlete' ?>">
                                    <div class="msg-meta"><?= htmlspecialchars($m['sender_first'] . ' ' . $m['sender_last']) ?> &bull; <?= date('M j, g:ia', strtotime($m['created_at'])) ?></div>
                                    <?= htmlspecialchars($m['message']) ?>
                                    <?php if ($m['video_url']): ?>
                                        <div style="margin-top:6px;"><a href="<?= htmlspecialchars($m['video_url']) ?>" target="_blank" style="color:var(--primary);font-size:12px;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-video"></i> Watch Video</a></div>
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

/* Tab Switching */
function switchDetailTab(tabName) {
    document.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c => c.classList.remove('active'));
    const tabBtn = document.querySelector('.detail-tab[data-tab="' + tabName + '"]');
    const tabContent = document.getElementById('tab-' + tabName);
    if (tabBtn) tabBtn.classList.add('active');
    if (tabContent) tabContent.classList.add('active');
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
