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

// Get enrolled athletes (ACTIVE programs)
try {
$athletes_stmt = $pdo->prepare("
    SELECT dpe.*, u.first_name, u.last_name, u.email,
           dpe.program_name, dpe.template_id, dpe.start_date, dpe.end_date,
           c.first_name as athlete_coach_first, c.last_name as athlete_coach_last,
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count,
           (SELECT COUNT(*) FROM development_program_messages dpm WHERE dpm.enrollment_id = dpe.id) as message_count,
           (SELECT COUNT(*) FROM development_program_videos dpv WHERE dpv.enrollment_id = dpe.id AND dpv.status = 'pending_review') as pending_video_count
    FROM development_program_enrollments dpe
    JOIN users u ON dpe.athlete_id = u.id
    LEFT JOIN users c ON u.assigned_coach_id = c.id
    WHERE dpe.program_type IN ($placeholders) AND dpe.status = 'active'
    ORDER BY dpe.enrolled_at DESC
");
$athletes_stmt->execute($program_types);
$athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);

// If decryption function exists, use it
if (function_exists('decryptUserRows')) {
    $athletes = decryptUserRows($athletes);
}

// Get COMPLETED/historical programs for the history view
$history_stmt = $pdo->prepare("
    SELECT dpe.*, u.first_name, u.last_name, u.email,
           dpe.program_name, dpe.template_id, dpe.start_date, dpe.end_date,
           c.first_name as athlete_coach_first, c.last_name as athlete_coach_last,
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
    FROM development_program_enrollments dpe
    JOIN users u ON dpe.athlete_id = u.id
    LEFT JOIN users c ON u.assigned_coach_id = c.id
    WHERE dpe.program_type IN ($placeholders) AND dpe.status IN ('completed', 'paused', 'cancelled')
    ORDER BY dpe.completed_at DESC, dpe.enrolled_at DESC
");
$history_stmt->execute($program_types);
$history_athletes = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
if (function_exists('decryptUserRows')) {
    $history_athletes = decryptUserRows($history_athletes);
}

// Determine the coach view mode
$coach_view = 'overview'; // overview (cards) or detail (single athlete)
$selected_enrollment_id = isset($_GET['enrollment_id']) ? (int)$_GET['enrollment_id'] : 0;
if ($selected_enrollment_id) $coach_view = 'detail';
$selected = null;
$selected_drills = [];
$selected_messages = [];
$selected_videos = [];
$selected_appointments = [];

if ($selected_enrollment_id) {
    // Verify access
    $sel_stmt = $pdo->prepare("
        SELECT dpe.*, u.first_name, u.last_name,
               c.first_name as athlete_coach_first, c.last_name as athlete_coach_last
        FROM development_program_enrollments dpe
        JOIN users u ON dpe.athlete_id = u.id
        LEFT JOIN users c ON u.assigned_coach_id = c.id
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
        if (class_exists('FieldEncryption')) {
            $selected_messages = FieldEncryption::decryptRows($selected_messages, array_merge(
                FieldEncryption::MESSAGE_ENCRYPTED_FIELDS,
                ['sender_first', 'sender_last']
            ));
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
$all_drills_stmt = $pdo->prepare("SELECT id, title, category_id FROM drills ORDER BY title");
$all_drills_stmt->execute();
$all_drills = $all_drills_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get locations for appointment form
$locations_stmt = $pdo->prepare("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name");
$locations_stmt->execute();
$locations = $locations_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Development Programs view error: " . $e->getMessage());
    $athletes = $athletes ?? [];
    $history_athletes = $history_athletes ?? [];
    $selected = $selected ?? null;
    $selected_drills = $selected_drills ?? [];
    $selected_messages = $selected_messages ?? [];
    $selected_videos = $selected_videos ?? [];
    $selected_appointments = $selected_appointments ?? [];
    $all_drills = $all_drills ?? [];
    $locations = $locations ?? [];
}
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
    background: var(--primary);
    color: var(--text-white);
    font-size: var(--font-size-xs);
    padding: 4px var(--space-3);
    border-radius: var(--radius-2xl);
    font-weight: var(--font-weight-semibold);
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
    background: var(--error);
    color: var(--text-white);
    border-radius: var(--radius-2xl);
    font-size: var(--font-size-xs);
    padding: 1px 7px;
    font-weight: var(--font-weight-bold);
    margin-left: 6px;
    animation: pulse-notify 2s ease-in-out infinite;
}
@keyframes pulse-notify {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Program Badges */
.dev-program-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px var(--space-3);
    border-radius: var(--radius-2xl);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
}
.dev-program-badge.goalie_dev { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.dev-program-badge.player_dev { background: rgba(16, 185, 129, 0.15); color: var(--success); }

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
    display: inline-flex;
    align-items: center;
    padding: 4px var(--space-3);
    border-radius: var(--radius-2xl);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-bold);
    text-transform: uppercase;
}
.drill-status.assigned { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.drill-status.in_progress { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.drill-status.completed { background: rgba(16, 185, 129, 0.15); color: var(--success); }
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
    background: var(--primary);
    color: var(--text-white);
}
.btn-sm-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
}
.btn-sm-danger {
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
    color: var(--error);
}
.btn-sm-danger:hover {
    background: var(--error);
    color: var(--text-white);
    border-color: var(--error) !important;
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
    display: inline-flex;
    align-items: center;
    padding: 4px var(--space-3);
    border-radius: var(--radius-2xl);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-bold);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.coach-video-status.pending_review { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.coach-video-status.reviewed { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.coach-video-status.feedback_given { background: rgba(16, 185, 129, 0.15); color: var(--success); }

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
    display: inline-flex;
    align-items: center;
    padding: 4px var(--space-2);
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-bold);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.appt-type-badge.call { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.appt-type-badge.video_call { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.appt-type-badge.in_person { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.appt-status-badge {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
}
.appt-status-badge.scheduled { color: var(--info); }
.appt-status-badge.completed { color: var(--success); }
.appt-status-badge.cancelled { color: var(--error); }

/* --- Chat / Communication --- */
.dev-chat-section { padding: 0; }
.dev-chat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.dev-chat-header h4 { font-size: var(--font-size-base, 14px); font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0; }
.dev-chat-e2e-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: var(--text-dim); background: rgba(107, 70, 193, 0.1); padding: 3px 8px; border-radius: var(--radius-md, 6px); }
.dev-chat-e2e-badge i { font-size: 9px; }
.dev-chat-messages {
    max-height: 380px;
    overflow-y: auto;
    margin-bottom: 16px;
    padding: 4px 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.dev-chat-messages::-webkit-scrollbar { width: 4px; }
.dev-chat-messages::-webkit-scrollbar-thumb { background: var(--border, #2D2D3F); border-radius: 4px; }
.dev-chat-bubble-row { display: flex; max-width: 75%; }
.dev-chat-bubble-row.from-coach { align-self: flex-end; }
.dev-chat-bubble-row.from-athlete { align-self: flex-start; }
.dev-chat-bubble {
    padding: 10px 14px;
    border-radius: 16px;
    font-size: var(--font-size-sm, 13px);
    line-height: 1.5;
    word-wrap: break-word;
}
.dev-chat-bubble-row.from-coach .dev-chat-bubble {
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    color: #fff;
    border-bottom-right-radius: 4px;
}
.dev-chat-bubble-row.from-athlete .dev-chat-bubble {
    background: var(--bg-main, #0a0a0f);
    color: var(--text-white, #e2e8f0);
    border: 1px solid var(--border, #2D2D3F);
    border-bottom-left-radius: 4px;
}
.dev-chat-bubble-meta {
    font-size: 10px;
    color: var(--text-dim, #A8A8B8);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.dev-chat-bubble-row.from-coach .dev-chat-bubble-meta { justify-content: flex-end; }
.dev-chat-bubble .msg-video-link { color: inherit; font-size: var(--font-size-sm, 13px); margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; opacity: 0.9; }
.dev-chat-bubble-row.from-athlete .dev-chat-bubble .msg-video-link { color: var(--primary); }
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
    background: var(--primary);
    color: var(--text-white);
    border: none;
    border-radius: var(--radius-lg, 8px);
    cursor: pointer;
    font-weight: var(--font-weight-semibold);
    font-size: var(--font-size-sm, 13px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all var(--transition-normal, 0.2s ease);
}
.dev-chat-input button:hover {
    background: var(--primary-hover);
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
    background: var(--info);
    color: var(--text-white);
    border: none;
    border-radius: var(--radius-lg, 8px);
    cursor: pointer;
    font-size: var(--font-size-sm, 12px);
    font-weight: var(--font-weight-semibold);
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
    color: var(--text-dim);
}
.dev-empty-state i {
    font-size: 36px;
    display: block;
    margin-bottom: var(--space-3);
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
/* Active program cards grid */
.dev-active-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: var(--space-5); }
.dev-active-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-2xl); padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3); transition: transform var(--transition-fast), box-shadow var(--transition-fast); cursor: pointer; text-decoration: none; color: inherit; }
.dev-active-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.dev-active-card .card-athlete-name { font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); color: var(--text-white); }
.dev-active-card .card-program-name { font-size: var(--font-size-sm); color: var(--text-dim); margin-top: 2px; }
.dev-active-card .card-meta { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: center; font-size: var(--font-size-sm); }
.dev-active-card .card-meta-item { display: inline-flex; align-items: center; gap: 4px; padding: 4px var(--space-3); border-radius: var(--radius-2xl); font-weight: var(--font-weight-semibold); }
.card-meta-item.weeks-left { background: rgba(245, 158, 11, 0.12); color: var(--warning); }
.card-meta-item.weeks-left.overdue { background: rgba(239, 68, 68, 0.12); color: var(--error); }
.dev-active-card .video-badge { background: rgba(239, 68, 68, 0.15); color: var(--error); padding: 4px var(--space-2); border-radius: var(--radius-2xl); font-size: var(--font-size-xs); font-weight: var(--font-weight-bold); }
/* Coach view tabs - count badge */
.page-tab .count-badge { background: var(--primary); color: var(--text-white); font-size: var(--font-size-xs); padding: 4px var(--space-3); border-radius: var(--radius-2xl); font-weight: var(--font-weight-semibold); margin-left: var(--space-2); }
.dev-coach-tab-content { display: none; }
.dev-coach-tab-content.active { display: block; }
/* History filters */
.dev-history-filters { display: flex; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-5); }
.dev-history-filters input, .dev-history-filters select { padding: var(--space-2) 14px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-white); font-size: var(--font-size-sm); min-width: 180px; }
/* History table */
.dev-history-table { width: 100%; border-collapse: collapse; }
.dev-history-table th { text-align: left; padding: var(--space-3) 14px; font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border); }
.dev-history-table td { padding: var(--space-3) 14px; font-size: var(--font-size-sm); border-bottom: 1px solid var(--border); color: var(--text-white); }
.dev-history-table tr:hover td { background: rgba(107, 70, 193, 0.05); }
.status-badge-sm { padding: 4px var(--space-3); border-radius: var(--radius-2xl); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); }
.status-badge-sm.completed { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.status-badge-sm.paused { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.status-badge-sm.cancelled { background: rgba(239, 68, 68, 0.15); color: var(--error); }
/* Back button */
.dev-back-btn { display: inline-flex; align-items: center; gap: 6px; padding: var(--space-2) var(--space-4); background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-white); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); cursor: pointer; text-decoration: none; margin-bottom: var(--space-4); transition: border-color var(--transition-normal); }
.dev-back-btn:hover { border-color: var(--primary); }
/* Rich drill cards for coach view */
.drill-card-list { display: flex; flex-direction: column; gap: var(--space-4); }
.drill-card-rich { display: flex; gap: var(--space-4); background: var(--bg-main, #0A0A0F); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; transition: border-color var(--transition-normal), transform var(--transition-fast); }
.drill-card-rich:hover { border-color: rgba(107, 70, 193, 0.4); transform: translateY(-1px); }
.drill-card-rich-thumb { width: 120px; min-height: 90px; flex-shrink: 0; background: var(--bg-card, #16161F); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.drill-card-rich-thumb img { width: 100%; height: 100%; object-fit: cover; }
.drill-card-video-icon, .drill-card-default-icon { font-size: 28px; color: var(--text-dim); opacity: 0.5; }
.drill-card-video-icon { color: var(--info); opacity: 0.7; }
.drill-card-rich-body { flex: 1; padding: var(--space-3) var(--space-4); display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.drill-card-rich-header { display: flex; justify-content: space-between; align-items: center; gap: var(--space-2); }
.drill-card-rich-header h4 { font-size: 15px; font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.drill-card-rich-desc { font-size: var(--font-size-sm); color: var(--text-dim); line-height: 1.5; margin: 0; }
.drill-card-coach-note { font-size: var(--font-size-sm); color: var(--warning); margin: 0; }
.drill-card-coach-note i { margin-right: 4px; }
.drill-card-rich-footer { display: flex; align-items: center; gap: var(--space-3); margin-top: auto; flex-wrap: wrap; }
.drill-view-link { font-size: var(--font-size-sm); color: var(--primary); font-weight: var(--font-weight-semibold); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.drill-view-link:hover { opacity: 0.8; }
.drill-has-video { font-size: var(--font-size-sm); color: var(--info); display: inline-flex; align-items: center; gap: 4px; }
.drill-card-rich-actions { margin-left: auto; display: flex; gap: 6px; }
@media (max-width: 600px) {
    .drill-card-rich { flex-direction: column; }
    .drill-card-rich-thumb { width: 100%; min-height: 60px; max-height: 120px; }
    .dev-active-cards { grid-template-columns: 1fr; }
    .dev-history-filters { flex-direction: column; }
    .dev-history-filters input, .dev-history-filters select { width: 100%; }
}
</style>

<?php if ($coach_view === 'detail' && $selected): ?>
<!-- ==================== DETAIL VIEW (with back button) ==================== -->
<a href="?page=development_programs" class="dev-back-btn"><i class="fas fa-arrow-left"></i> Back to All Programs</a>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-hockey-puck"></i> <?= htmlspecialchars($selected['first_name'] . ' ' . $selected['last_name']) ?>
        <?php if (!empty($selected['athlete_coach_first']) || !empty($selected['athlete_coach_last'])): ?>
        <span style="font-size:var(--font-size-base);font-weight:var(--font-weight-semibold);color:var(--text-dim);margin-left:var(--space-3);"><i class="fas fa-user-tie"></i> Coach: <?= htmlspecialchars(trim(($selected['athlete_coach_first'] ?? '') . ' ' . ($selected['athlete_coach_last'] ?? ''))) ?></span>
        <?php endif; ?>
    </h1>
    <p class="page-description"><?= htmlspecialchars($selected['program_name'] ?? ($selected['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development')) ?></p>
</div>

<div class="dev-coach-container" style="display:block;">
    <!-- Detail Panel -->
    <div class="dev-athlete-detail">
            <div class="detail-panel">
                <!-- Athlete Header -->
                <div class="detail-athlete-header">
                    <h3>
                        <?php if ($selected['program_type'] === 'goalie_dev'): ?>
                            <i class="fas fa-shield-alt" style="color:var(--info);"></i>
                        <?php else: ?>
                            <i class="fas fa-hockey-puck" style="color:var(--success);"></i>
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
                        <div class="drill-card-list">
                        <?php foreach ($selected_drills as $sd): ?>
                            <div class="drill-card-rich">
                                <div class="drill-card-rich-thumb">
                                    <?php if (!empty($sd['custom_image'])): ?>
                                        <img src="<?= htmlspecialchars(function_exists('resolveRustfsUrl') ? resolveRustfsUrl($pdo, $sd['custom_image']) : $sd['custom_image']) ?>" alt="<?= htmlspecialchars($sd['drill_title']) ?> thumbnail">
                                    <?php elseif (!empty($sd['drill_video_url'])): ?>
                                        <div class="drill-card-video-icon"><i class="fas fa-play-circle"></i></div>
                                    <?php else: ?>
                                        <div class="drill-card-default-icon"><i class="fas fa-clipboard-list"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="drill-card-rich-body">
                                    <div class="drill-card-rich-header">
                                        <h4><?= htmlspecialchars($sd['drill_title']) ?></h4>
                                        <span class="drill-status <?= htmlspecialchars($sd['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($sd['status'])) ?></span>
                                    </div>
                                    <?php if (!empty($sd['drill_description'])): ?>
                                    <p class="drill-card-rich-desc"><?= htmlspecialchars(substr($sd['drill_description'], 0, 150)) ?><?= strlen($sd['drill_description']) > 150 ? '...' : '' ?></p>
                                    <?php endif; ?>
                                    <?php if ($sd['coach_notes']): ?>
                                    <p class="drill-card-coach-note"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars(substr($sd['coach_notes'], 0, 100)) ?></p>
                                    <?php endif; ?>
                                    <div class="drill-card-rich-footer">
                                        <a href="?page=dev_drill_detail&id=<?= (int)$sd['id'] ?>&enrollment_id=<?= (int)$selected['id'] ?>&coach_view=1" class="drill-view-link"><i class="fas fa-eye"></i> View Full Details</a>
                                        <?php if (!empty($sd['drill_video_url'])): ?>
                                        <span class="drill-has-video"><i class="fas fa-video"></i> Has Video</span>
                                        <?php endif; ?>
                                        <div class="drill-card-rich-actions">
                                            <button class="btn-sm-primary" onclick="updateDrillStatus(<?= (int)$sd['id'] ?>, '<?= $sd['status'] === 'assigned' ? 'in_progress' : 'completed' ?>')">
                                                <?= $sd['status'] === 'assigned' ? 'Start' : ($sd['status'] === 'in_progress' ? 'Complete' : '✓') ?>
                                            </button>
                                            <button class="btn-sm-danger" onclick="removeDrill(<?= (int)$sd['id'] ?>, <?= (int)$selected['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
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
                        <div class="dev-chat-header">
                            <h4><i class="fas fa-comments"></i> Messages</h4>
                            <span class="dev-chat-e2e-badge" title="Messages are end-to-end encrypted"><i class="fas fa-lock"></i> Encrypted</span>
                        </div>
                        <div class="dev-chat-messages">
                            <?php if (empty($selected_messages)): ?>
                                <div class="dev-empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>No messages yet. Start the conversation below.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($selected_messages as $m): ?>
                                <div class="dev-chat-bubble-row <?= $m['sender_id'] == $user_id ? 'from-coach' : 'from-athlete' ?>">
                                    <div>
                                        <div class="dev-chat-bubble">
                                            <?= htmlspecialchars($m['message']) ?>
                                            <?php if (!empty($m['video_url'])): ?>
                                                <div><a href="<?= htmlspecialchars($m['video_url']) ?>" target="_blank" class="msg-video-link"><i class="fas fa-video"></i> Watch Video</a></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dev-chat-bubble-meta">
                                            <?= htmlspecialchars($m['sender_first'] . ' ' . $m['sender_last']) ?> &bull; <?= date('M j, g:ia', strtotime($m['created_at'])) ?>
                                            <i class="fas fa-lock" style="font-size:10px;" title="Encrypted"></i>
                                        </div>
                                    </div>
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
    </div>
</div>

<?php else: ?>
<!-- ==================== OVERVIEW VIEW (cards + history) ==================== -->
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-hockey-puck"></i> Development Programs</h1>
    <p class="page-description">Manage athlete development programs, assign drills, and communicate with athletes</p>
</div>

<!-- Coach tabs: Active Programs | Program History -->
<div class="page-tabs-wrapper">
    <div class="page-tabs">
        <button class="page-tab active" onclick="switchCoachTab('active')" data-coach-tab="active">
            <i class="fas fa-users"></i> Active Programs <span class="count-badge"><?= count($athletes) ?></span>
        </button>
        <button class="page-tab" onclick="switchCoachTab('history')" data-coach-tab="history">
            <i class="fas fa-history"></i> Program History <span class="count-badge"><?= count($history_athletes) ?></span>
        </button>
    </div>
</div>

<div class="page-tab-content">
<!-- Active Programs Tab -->
<div class="dev-coach-tab-content active" id="coach-tab-active">
    <?php if (empty($athletes)): ?>
    <div class="dev-empty-state dev-empty-state--lg">
        <i class="fas fa-user-plus"></i>
        <p>No athletes currently enrolled in active programs.</p>
    </div>
    <?php else: ?>
    <div class="dev-active-cards">
        <?php foreach ($athletes as $a):
            $weeks_left = null;
            if (!empty($a['end_date'])) {
                $end_ts = strtotime($a['end_date']);
                $now_ts = time();
                $diff_days = ($end_ts - $now_ts) / 86400;
                $weeks_left = max(0, ceil($diff_days / 7));
            }
            $position = $a['program_type'] === 'goalie_dev' ? 'Goalie' : 'Skater';
            $pos_icon = $a['program_type'] === 'goalie_dev' ? 'fa-shield-alt' : 'fa-hockey-puck';
            $pos_color = $a['program_type'] === 'goalie_dev' ? '#3b82f6' : '#10b981';
            $program_display = $a['program_name'] ?: ($a['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development');
        ?>
        <a href="?page=development_programs&enrollment_id=<?= (int)$a['id'] ?>" class="dev-active-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="card-athlete-name"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    <?php if (!empty($a['athlete_coach_first']) || !empty($a['athlete_coach_last'])): ?>
                    <div style="font-size:var(--font-size-sm);color:var(--text-dim);margin-top:2px;"><i class="fas fa-user-tie" style="margin-right:4px;"></i> Coach: <?= htmlspecialchars(trim(($a['athlete_coach_first'] ?? '') . ' ' . ($a['athlete_coach_last'] ?? ''))) ?></div>
                    <?php endif; ?>
                    <div class="card-program-name"><?= htmlspecialchars($program_display) ?></div>
                </div>
                <?php if ((int)($a['pending_video_count'] ?? 0) > 0): ?>
                <span class="video-badge"><i class="fas fa-video"></i> <?= (int)$a['pending_video_count'] ?> pending</span>
                <?php endif; ?>
            </div>
            <div class="card-meta">
                <span class="dev-program-badge <?= htmlspecialchars($a['program_type']) ?>">
                    <i class="fas <?= $pos_icon ?>"></i> <?= $position ?>
                </span>
                <?php if ($weeks_left !== null): ?>
                <span class="card-meta-item weeks-left <?= $weeks_left <= 0 ? 'overdue' : '' ?>">
                    <i class="fas fa-clock"></i> <?= $weeks_left > 0 ? $weeks_left . ' week' . ($weeks_left !== 1 ? 's' : '') . ' left' : 'Program ended' ?>
                </span>
                <?php endif; ?>
                <span class="card-meta-item" style="background:rgba(107,70,193,0.12);color:var(--accent);">
                    <i class="fas fa-clipboard-list"></i> <?= (int)$a['drill_count'] ?> drills
                </span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Program History Tab -->
<div class="dev-coach-tab-content" id="coach-tab-history">
    <div class="dev-history-filters">
        <input type="text" id="history-filter-name" placeholder="Filter by athlete name..." oninput="filterHistory()">
        <select id="history-filter-position" onchange="filterHistory()">
            <option value="">All Positions</option>
            <option value="goalie_dev">Goalie</option>
            <option value="player_dev">Skater</option>
        </select>
        <select id="history-filter-program" onchange="filterHistory()">
            <option value="">All Program Types</option>
            <?php
            $program_names = array_unique(array_filter(array_column($history_athletes, 'program_name')));
            foreach ($program_names as $pn): ?>
            <option value="<?= htmlspecialchars($pn) ?>"><?= htmlspecialchars($pn) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($history_athletes)): ?>
    <div class="dev-empty-state dev-empty-state--lg">
        <i class="fas fa-history"></i>
        <p>No completed programs yet.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="dev-history-table" id="history-table">
        <thead>
            <tr>
                <th>Athlete</th>
                <th>Coach</th>
                <th>Program</th>
                <th>Position</th>
                <th>Enrolled</th>
                <th>Completed</th>
                <th>Drills</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history_athletes as $h):
                $h_position = $h['program_type'] === 'goalie_dev' ? 'Goalie' : 'Skater';
                $h_program = $h['program_name'] ?: ($h['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development');
            ?>
            <tr data-name="<?= htmlspecialchars(strtolower($h['first_name'] . ' ' . $h['last_name'])) ?>"
                data-position="<?= htmlspecialchars($h['program_type']) ?>"
                data-program="<?= htmlspecialchars($h['program_name'] ?? '') ?>">
                <td><strong><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></strong></td>
                <td><?= !empty($h['athlete_coach_first']) || !empty($h['athlete_coach_last']) ? htmlspecialchars(trim(($h['athlete_coach_first'] ?? '') . ' ' . ($h['athlete_coach_last'] ?? ''))) : '<span style="color:var(--text-dim);">—</span>' ?></td>
                <td><?= htmlspecialchars($h_program) ?></td>
                <td>
                    <span class="dev-program-badge <?= htmlspecialchars($h['program_type']) ?>">
                        <?= $h_position ?>
                    </span>
                </td>
                <td><?= date('M j, Y', strtotime($h['enrolled_at'])) ?></td>
                <td><?= $h['completed_at'] ? date('M j, Y', strtotime($h['completed_at'])) : '—' ?></td>
                <td><?= (int)$h['drill_count'] ?></td>
                <td><span class="status-badge-sm <?= htmlspecialchars($h['status']) ?>"><?= ucfirst(htmlspecialchars($h['status'])) ?></span></td>
                <td><a href="?page=development_programs&enrollment_id=<?= (int)$h['id'] ?>" style="color:var(--primary);font-size:12px;font-weight:600;"><i class="fas fa-eye"></i> View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
</div><!-- /.page-tab-content -->

<?php endif; ?>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const devHeaders = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-Token': csrfToken
};

function devPost(data) {
    data.csrf_token = csrfToken;
    return fetch('process_development_programs.php', {
        method: 'POST', headers: devHeaders, body: JSON.stringify(data)
    }).then(r => r.json());
}

/* Coach view tab switching (Active Programs / History) */
function switchCoachTab(tabName) {
    document.querySelectorAll('.page-tabs-wrapper .page-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.dev-coach-tab-content').forEach(c => c.classList.remove('active'));
    var tabBtn = document.querySelector('.page-tab[data-coach-tab="' + tabName + '"]');
    var tabContent = document.getElementById('coach-tab-' + tabName);
    if (tabBtn) tabBtn.classList.add('active');
    if (tabContent) tabContent.classList.add('active');
}

/* History filtering */
function filterHistory() {
    var nameFilter = (document.getElementById('history-filter-name')?.value || '').toLowerCase();
    var posFilter = document.getElementById('history-filter-position')?.value || '';
    var progFilter = document.getElementById('history-filter-program')?.value || '';
    var rows = document.querySelectorAll('#history-table tbody tr');
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        var pos = row.getAttribute('data-position') || '';
        var prog = row.getAttribute('data-program') || '';
        var show = true;
        if (nameFilter && name.indexOf(nameFilter) === -1) show = false;
        if (posFilter && pos !== posFilter) show = false;
        if (progFilter && prog !== progFilter) show = false;
        row.style.display = show ? '' : 'none';
    });
}

/* Detail Tab Switching */
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
