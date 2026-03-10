<?php
/**
 * My Program - View assigned drills and communicate with coach
 * Shows athlete's enrolled programs with assigned drills, video upload, and upcoming appointments
 */

$user_id = $_SESSION['user_id'] ?? 0;

try {
// Get user's active enrollments with assigned drills
$enrollments_stmt = $pdo->prepare("
    SELECT dpe.*, dpe.program_name, dpe.template_id, dpe.start_date, dpe.end_date
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ? AND dpe.status = 'active'
    ORDER BY dpe.enrolled_at DESC
");
$enrollments_stmt->execute([$user_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get COMPLETED programs for history view
$completed_stmt = $pdo->prepare("
    SELECT dpe.*, dpe.program_name, dpe.start_date, dpe.end_date,
           (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ? AND dpe.status IN ('completed', 'paused', 'cancelled')
    ORDER BY dpe.completed_at DESC, dpe.enrolled_at DESC
");
$completed_stmt->execute([$user_id]);
$completed_programs = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get drills, messages, videos, and appointments for each enrollment
foreach ($enrollments as &$enrollment) {
    $drills_stmt = $pdo->prepare("
        SELECT dpd.*, d.title as drill_title, d.description as drill_description,
               d.video_url as drill_video_url, d.custom_image as drill_image,
               d.setup as drill_setup, d.coaching_points as drill_coaching_points,
               u.first_name as coach_first, u.last_name as coach_last
        FROM development_program_drills dpd
        JOIN drills d ON dpd.drill_id = d.id
        JOIN users u ON dpd.assigned_by = u.id
        WHERE dpd.enrollment_id = ?
        ORDER BY dpd.sort_order, dpd.created_at
    ");
    $drills_stmt->execute([$enrollment['id']]);
    $enrollment['drills'] = $drills_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $enrollment['drills'] = decryptUserRows($enrollment['drills']);
    }
    
    // Get recent messages
    $msgs_stmt = $pdo->prepare("
        SELECT dpm.*, u.first_name as sender_first, u.last_name as sender_last
        FROM development_program_messages dpm
        JOIN users u ON dpm.sender_id = u.id
        WHERE dpm.enrollment_id = ?
        ORDER BY dpm.created_at DESC
        LIMIT 20
    ");
    $msgs_stmt->execute([$enrollment['id']]);
    $enrollment['messages'] = array_reverse($msgs_stmt->fetchAll(PDO::FETCH_ASSOC));
    if (function_exists('decryptUserRows')) {
        $enrollment['messages'] = decryptUserRows($enrollment['messages']);
    }
    if (class_exists('FieldEncryption')) {
        $enrollment['messages'] = FieldEncryption::decryptRows($enrollment['messages'], array_merge(
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
        WHERE dpv.enrollment_id = ? AND dpv.athlete_id = ?
        ORDER BY dpv.created_at DESC
        LIMIT 10
    ");
    $videos_stmt->execute([$enrollment['id'], $user_id]);
    $enrollment['videos'] = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get upcoming appointments
    $appts_stmt = $pdo->prepare("
        SELECT da.*, u.first_name as coach_first, u.last_name as coach_last
        FROM development_appointments da
        JOIN users u ON da.coach_id = u.id
        WHERE da.enrollment_id = ? AND da.athlete_id = ? AND da.status = 'scheduled'
              AND (da.appointment_date > CURDATE() OR (da.appointment_date = CURDATE() AND da.appointment_time >= CURTIME()))
        ORDER BY da.appointment_date, da.appointment_time
    ");
    $appts_stmt->execute([$enrollment['id'], $user_id]);
    $enrollment['appointments'] = $appts_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $enrollment['appointments'] = decryptUserRows($enrollment['appointments']);
    }
}
unset($enrollment);
} catch (PDOException $e) {
    error_log("My Program view error: " . $e->getMessage());
    $enrollments = $enrollments ?? [];
    $completed_programs = $completed_programs ?? [];
}
?>

<style>
.my-program-empty {
    text-align: center;
    padding: 60px var(--space-5);
    color: var(--text-dim);
}
.my-program-empty i { font-size: 48px; margin-bottom: var(--space-4); display: block; opacity: 0.5; }
.enrollment-section {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl);
    padding: var(--space-6);
    margin-bottom: var(--space-6);
}
.enrollment-section h3 {
    font-size: var(--font-size-lg); font-weight: var(--font-weight-bold);
    color: var(--text-white); margin-bottom: var(--space-4);
}
.enrollment-section h3 i { margin-right: var(--space-2); }
.enrollment-section h3 .icon-goalie { color: var(--info); }
.enrollment-section h3 .icon-player { color: var(--success); }
.enrollment-section h3 .weeks-badge {
    font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold);
    padding: 4px var(--space-3); border-radius: var(--radius-2xl);
    margin-left: var(--space-2);
}
.enrollment-section h3 .weeks-badge.active { background: rgba(245, 158, 11, 0.12); color: var(--warning); }
.enrollment-section h3 .weeks-badge.ended { background: rgba(239, 68, 68, 0.12); color: var(--error); }
.enrollment-section .section-label {
    font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white);
    margin: var(--space-5) 0 var(--space-3); padding-top: var(--space-4);
    border-top: 1px solid var(--border, #2D2D3F);
}
.enrollment-section .section-label:first-of-type { border-top: none; margin-top: 0; padding-top: 0; }
.enrollment-section .section-label i { margin-right: 6px; }

/* Drill cards - clickable links */
.drill-list { display: flex; flex-direction: column; gap: var(--space-3); }
.drill-card {
    background: var(--bg-main, #0A0A0F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-xl); padding: var(--space-4); cursor: pointer;
    transition: border-color var(--transition-normal), transform var(--transition-fast), box-shadow var(--transition-normal);
}
.drill-card:hover {
    border-color: rgba(107, 70, 193, 0.4); transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}
.drill-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2); }
.drill-card-header h4 { font-size: 15px; font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0; }
.drill-card p { font-size: var(--font-size-sm); color: var(--text-dim); line-height: 1.5; margin: 0 0 6px; }
.drill-card .coach-note-text { color: var(--warning); font-size: var(--font-size-sm); }
.drill-card .coach-note-text i { margin-right: 2px; }
.drill-card-footer { display: flex; align-items: center; gap: var(--space-3); margin-top: var(--space-2); }
.drill-card-footer .btn-view-drill {
    font-size: var(--font-size-sm); color: var(--primary); font-weight: var(--font-weight-semibold);
    text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
}
.drill-card-footer .btn-view-drill.has-video { color: var(--info); }
.drill-status {
    display: inline-flex; align-items: center; padding: 4px var(--space-3); border-radius: var(--radius-2xl);
    font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); text-transform: uppercase;
}
.drill-status.assigned { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.drill-status.in_progress { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.drill-status.completed { background: rgba(16, 185, 129, 0.15); color: var(--success); }

/* Upload section */
.dev-upload-section {
    margin-top: var(--space-5); padding-top: var(--space-4);
    border-top: 1px solid var(--border, #2D2D3F);
}
.dev-upload-card {
    background: var(--bg-main, #0A0A0F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-xl); padding: var(--space-5);
}
.dev-upload-card h4 { font-size: 15px; font-weight: var(--font-weight-semibold); color: var(--text-white); margin-bottom: var(--space-3); }
.dev-upload-card h4 i { margin-right: 6px; }
.dev-upload-card .upload-description { font-size: var(--font-size-sm); color: var(--text-dim); margin-bottom: var(--space-3); }
.dev-upload-options { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: 14px; }
.dev-upload-option {
    background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg); padding: var(--space-4); text-align: center; cursor: pointer;
    transition: border-color var(--transition-normal), transform var(--transition-fast);
}
.dev-upload-option:hover { border-color: rgba(107, 70, 193, 0.4); transform: translateY(-1px); }
.dev-upload-option i { font-size: 28px; color: var(--primary); display: block; margin-bottom: var(--space-2); }
.dev-upload-option span { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-white); }
.dev-upload-form { display: none; }
.dev-upload-form.active { display: block; }
.dev-upload-form label { display: block; font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-dim); margin-bottom: var(--space-1); }
.dev-upload-form input[type="text"],
.dev-upload-form input[type="url"],
.dev-upload-form textarea,
.dev-upload-form select {
    width: 100%; padding: 10px var(--space-3); background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F); border-radius: var(--radius-lg);
    color: var(--text-white); font-size: var(--font-size-sm); margin-bottom: 10px;
}
.dev-upload-form textarea { min-height: 60px; resize: vertical; }
.dev-upload-form .btn-upload {
    padding: 0 var(--space-5); height: 40px; background: var(--primary); color: var(--text-white);
    border: none; border-radius: var(--radius-lg); font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: all var(--transition-normal);
}
.dev-upload-form .btn-upload:hover { background: var(--primary-hover); }

/* Uploaded videos list */
.dev-video-list { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
.dev-video-item {
    background: var(--bg-main, #0A0A0F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg); padding: var(--space-3) var(--space-4); display: flex; justify-content: space-between; align-items: center;
}
.dev-video-item .video-info h5 { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0 0 4px; }
.dev-video-item .video-info span { font-size: var(--font-size-sm); color: var(--text-dim); }
.dev-video-item .video-info .coach-feedback { margin: 4px 0 0; font-size: var(--font-size-sm); color: var(--success); }
.dev-video-item .video-info .coach-feedback i { margin-right: 2px; }
.dev-video-item .video-status {
    padding: 4px var(--space-3); border-radius: var(--radius-2xl); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); text-transform: uppercase;
}
.dev-video-item .video-status.pending_review { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.dev-video-item .video-status.reviewed { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.dev-video-item .video-status.feedback_given { background: rgba(16, 185, 129, 0.15); color: var(--success); }

/* Upcoming appointments */
.dev-appointments { display: flex; flex-direction: column; gap: 10px; }
.dev-appointment-card {
    background: var(--bg-main, #0A0A0F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-xl); padding: var(--space-4); display: flex; gap: var(--space-4); align-items: center;
}
.dev-appointment-card .appt-date-box {
    min-width: 56px; text-align: center; padding: var(--space-2) 10px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: var(--radius-lg); color: var(--text-white);
}
.dev-appointment-card .appt-date-box .appt-day { font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); display: block; }
.dev-appointment-card .appt-date-box .appt-month { font-size: var(--font-size-xs); text-transform: uppercase; }
.dev-appointment-card .appt-details { flex: 1; }
.dev-appointment-card .appt-details h5 { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0 0 4px; }
.dev-appointment-card .appt-meta { font-size: var(--font-size-sm); color: var(--text-dim); display: flex; gap: var(--space-3); flex-wrap: wrap; }
.dev-appointment-card .appt-meta i { margin-right: 3px; }
.dev-appointment-type {
    display: inline-flex; align-items: center; padding: 4px var(--space-2); border-radius: var(--radius-md);
    font-size: var(--font-size-xs); font-weight: var(--font-weight-semibold); text-transform: uppercase;
}
.dev-appointment-type.call { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.dev-appointment-type.video_call { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.dev-appointment-type.in_person { background: rgba(245, 158, 11, 0.15); color: var(--warning); }

/* Chat */
.program-chat { margin-top: var(--space-5); border-top: 1px solid var(--border, #2D2D3F); padding-top: var(--space-4); }
.program-chat .chat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3); }
.program-chat .chat-title { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0; }
.program-chat .chat-title i { margin-right: 6px; }
.chat-e2e-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: var(--text-dim); background: rgba(107, 70, 193, 0.1); padding: 3px 8px; border-radius: var(--radius-md); }
.chat-e2e-badge i { font-size: 9px; }
.chat-messages { max-height: 380px; overflow-y: auto; margin-bottom: var(--space-3); display: flex; flex-direction: column; gap: 4px; padding: var(--space-2) 0; }
.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-thumb { background: var(--border, #2D2D3F); border-radius: 4px; }
.chat-empty { color: var(--text-dim); font-size: var(--font-size-sm); text-align: center; padding: var(--space-5); }
.chat-bubble-row { display: flex; max-width: 75%; }
.chat-bubble-row.from-me { align-self: flex-end; }
.chat-bubble-row.from-coach { align-self: flex-start; }
.chat-bubble { padding: 10px 14px; border-radius: 16px; font-size: var(--font-size-sm); line-height: 1.5; word-wrap: break-word; }
.chat-bubble-row.from-me .chat-bubble { background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6)); color: #fff; border-bottom-right-radius: 4px; }
.chat-bubble-row.from-coach .chat-bubble { background: var(--bg-main, #0a0a0f); color: var(--text-white, #e2e8f0); border: 1px solid var(--border, #2D2D3F); border-bottom-left-radius: 4px; }
.chat-bubble-meta { font-size: 10px; color: var(--text-dim); margin-top: 4px; display: flex; align-items: center; gap: 4px; }
.chat-bubble-row.from-me .chat-bubble-meta { justify-content: flex-end; }
.chat-bubble .msg-video-link { color: inherit; font-size: var(--font-size-sm); margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; opacity: 0.9; }
.chat-bubble-row.from-coach .chat-bubble .msg-video-link { color: var(--primary); }
.chat-input-row { display: flex; gap: var(--space-2); }
.chat-input-row input {
    flex: 1; padding: 10px 14px; background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F); border-radius: var(--radius-lg);
    color: var(--text-white); font-size: var(--font-size-sm);
}
.chat-input-row input:focus { outline: none; border-color: var(--primary, #6B46C1); box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15); }
.chat-input-row button {
    padding: 10px var(--space-4); background: var(--primary); color: var(--text-white);
    border: none; border-radius: var(--radius-lg); cursor: pointer; font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);
    transition: all var(--transition-normal);
}
.chat-input-row button:hover { background: var(--primary-hover); }

/* Previously uploaded videos section */
.dev-submitted-videos { margin-top: var(--space-4); }
.dev-submitted-videos h4 { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white); margin-bottom: 10px; }
.dev-submitted-videos h4 i { margin-right: 6px; }

/* Completed programs history */
.dev-completed-section {
    margin-top: var(--space-8);
}
.dev-completed-section h3 {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-4);
}
.dev-completed-section h3 i {
    color: var(--text-dim);
    margin-right: var(--space-2);
}
.dev-completed-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl);
    padding: var(--space-4) var(--space-5);
    margin-bottom: var(--space-3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-3);
}
.dev-completed-card .completed-icon-goalie { color: var(--info); margin-right: 6px; }
.dev-completed-card .completed-icon-player { color: var(--success); margin-right: 6px; }
.dev-completed-card .completed-name {
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    font-size: 15px;
}
.dev-completed-card .completed-meta {
    font-size: var(--font-size-sm);
    color: var(--text-dim);
    margin-top: var(--space-1);
}

/* No drills message */
.no-drills-msg { color: var(--text-dim); font-size: var(--font-size-base); }

@media (max-width: 600px) {
    .dev-upload-options { grid-template-columns: 1fr; }
    .dev-appointment-card { flex-direction: column; text-align: center; }
    .dev-appointment-card .appt-meta { justify-content: center; }
}
</style>

<?php if (empty($enrollments) && empty($completed_programs)): ?>
<div class="my-program-empty">
    <i class="fas fa-hockey-puck"></i>
    <h3>No Active Programs</h3>
    <p>You haven't enrolled in any development programs yet. Visit the <a href="?page=personal_development_programs" style="color:var(--primary);">Development Programs</a> tab to register.</p>
</div>
<?php else: ?>
    <?php foreach ($enrollments as $enrollment):
        $program_display = $enrollment['program_name'] ?: ($enrollment['program_type'] === 'goalie_dev' ? 'Long Term Goalie Development' : 'Long Term Player Development');
        $weeks_left = null;
        if (!empty($enrollment['end_date'])) {
            $end_ts = strtotime($enrollment['end_date']);
            $diff_days = ($end_ts - time()) / 86400;
            $weeks_left = max(0, ceil($diff_days / 7));
        }
    ?>
    <div class="enrollment-section">
        <h3>
            <?php if ($enrollment['program_type'] === 'goalie_dev'): ?>
                <i class="fas fa-shield-alt icon-goalie"></i>
            <?php else: ?>
                <i class="fas fa-hockey-puck icon-player"></i>
            <?php endif; ?>
            <?= htmlspecialchars($program_display) ?>
            <?php if ($weeks_left !== null): ?>
            <span class="weeks-badge <?= $weeks_left > 0 ? 'active' : 'ended' ?>">
                <?= $weeks_left > 0 ? $weeks_left . ' week' . ($weeks_left !== 1 ? 's' : '') . ' left' : 'Program ended' ?>
            </span>
            <?php endif; ?>
        </h3>

        <!-- Upcoming Appointments -->
        <?php if (!empty($enrollment['appointments'])): ?>
        <div class="section-label"><i class="fas fa-calendar-check"></i> Upcoming Sessions</div>
        <div class="dev-appointments">
            <?php foreach ($enrollment['appointments'] as $appt): ?>
            <div class="dev-appointment-card">
                <div class="appt-date-box">
                    <span class="appt-day"><?= date('j', strtotime($appt['appointment_date'])) ?></span>
                    <span class="appt-month"><?= date('M', strtotime($appt['appointment_date'])) ?></span>
                </div>
                <div class="appt-details">
                    <h5><?= htmlspecialchars($appt['title']) ?></h5>
                    <div class="appt-meta">
                        <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($appt['appointment_time'])) ?> (<?= (int)$appt['duration_minutes'] ?> min)</span>
                        <span class="dev-appointment-type <?= htmlspecialchars($appt['appointment_type']) ?>"><?= str_replace('_', ' ', htmlspecialchars($appt['appointment_type'])) ?></span>
                        <?php if ($appt['appointment_type'] === 'in_person' && !empty($appt['location'])): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($appt['location']) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($appt['coach_first'] . ' ' . $appt['coach_last']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Assigned Drills -->
        <div class="section-label"><i class="fas fa-clipboard-list"></i> Assigned Drills</div>
        <?php if (empty($enrollment['drills'])): ?>
            <p class="no-drills-msg">No drills assigned yet. Your coach will add drills to your program soon.</p>
        <?php else: ?>
            <div class="drill-list">
            <?php foreach ($enrollment['drills'] as $drill): ?>
                <a href="?page=dev_drill_detail&id=<?= (int)$drill['id'] ?>&enrollment_id=<?= (int)$enrollment['id'] ?>" class="drill-card" data-drill-assignment-id="<?= (int)$drill['id'] ?>" style="text-decoration:none;display:block;">
                    <div class="drill-card-header">
                        <h4><?= htmlspecialchars($drill['drill_title']) ?></h4>
                        <span class="drill-status <?= htmlspecialchars($drill['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($drill['status'])) ?></span>
                    </div>
                    <?php if ($drill['drill_description']): ?>
                        <p><?= htmlspecialchars(substr($drill['drill_description'], 0, 150)) ?><?= strlen($drill['drill_description']) > 150 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <?php if ($drill['coach_notes']): ?>
                        <p class="coach-note-text"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($drill['coach_notes']) ?></p>
                    <?php endif; ?>
                    <div class="drill-card-footer">
                        <span class="btn-view-drill"><i class="fas fa-eye"></i> View Full Details</span>
                        <?php if ($drill['drill_video_url']): ?>
                        <span class="btn-view-drill has-video"><i class="fas fa-play-circle"></i> Has Video</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Development Video Upload -->
        <div class="dev-upload-section">
            <div class="dev-upload-card">
                <h4><i class="fas fa-video"></i> Upload Development Video</h4>
                <p class="upload-description">Record or upload a video for your coach to review. You can submit general development videos or videos specific to an assigned drill.</p>
                <div class="dev-upload-options">
                    <div class="dev-upload-option" onclick="showDevUploadForm(<?= (int)$enrollment['id'] ?>, 'record')">
                        <i class="fas fa-circle-dot"></i>
                        <span>Record Video</span>
                    </div>
                    <div class="dev-upload-option" onclick="showDevUploadForm(<?= (int)$enrollment['id'] ?>, 'upload')">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Upload Video</span>
                    </div>
                </div>
                <div class="dev-upload-form" id="dev-upload-form-<?= (int)$enrollment['id'] ?>">
                    <label for="dev-video-title-<?= (int)$enrollment['id'] ?>">Title *</label>
                    <input type="text" id="dev-video-title-<?= (int)$enrollment['id'] ?>" placeholder="e.g. Skating drill practice">
                    <label for="dev-video-desc-<?= (int)$enrollment['id'] ?>">Description</label>
                    <textarea id="dev-video-desc-<?= (int)$enrollment['id'] ?>" placeholder="Optional notes for your coach..."></textarea>
                    <label for="dev-video-drill-<?= (int)$enrollment['id'] ?>">Drill (optional)</label>
                    <select id="dev-video-drill-<?= (int)$enrollment['id'] ?>">
                        <option value="">General Development Video</option>
                        <?php foreach ($enrollment['drills'] as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['drill_title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="dev-video-file-wrap-<?= (int)$enrollment['id'] ?>">
                        <label for="dev-video-file-<?= (int)$enrollment['id'] ?>">Video File</label>
                        <input type="file" id="dev-video-file-<?= (int)$enrollment['id'] ?>" accept="video/*" capture="environment" style="margin-bottom:10px;">
                    </div>
                    <button class="btn-upload" onclick="submitDevVideo(<?= (int)$enrollment['id'] ?>)">
                        <i class="fas fa-cloud-upload-alt"></i> Submit Video
                    </button>
                </div>
            </div>

            <!-- Previously Uploaded Videos -->
            <?php if (!empty($enrollment['videos'])): ?>
            <div class="dev-submitted-videos">
                <h4><i class="fas fa-film"></i> Your Submitted Videos</h4>
                <div class="dev-video-list">
                    <?php foreach ($enrollment['videos'] as $vid): ?>
                    <div class="dev-video-item">
                        <div class="video-info">
                            <h5><?= htmlspecialchars($vid['title']) ?></h5>
                            <span><?= date('M j, Y g:ia', strtotime($vid['created_at'])) ?><?= $vid['drill_title'] ? ' &bull; ' . htmlspecialchars($vid['drill_title']) : '' ?></span>
                            <?php if ($vid['coach_feedback']): ?>
                            <p class="coach-feedback"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($vid['coach_feedback']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="video-status <?= htmlspecialchars($vid['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($vid['status'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Chat Section -->
        <div class="program-chat">
            <div class="chat-header">
                <h4 class="chat-title"><i class="fas fa-comments"></i> Program Chat</h4>
                <span class="chat-e2e-badge" title="Messages are end-to-end encrypted"><i class="fas fa-lock"></i> Encrypted</span>
            </div>
            <div class="chat-messages" id="chat-<?= (int)$enrollment['id'] ?>">
                <?php if (empty($enrollment['messages'])): ?>
                    <p class="chat-empty">No messages yet. Start a conversation with your coach.</p>
                <?php else: ?>
                    <?php foreach ($enrollment['messages'] as $msg): ?>
                    <div class="chat-bubble-row <?= $msg['sender_id'] == $user_id ? 'from-me' : 'from-coach' ?>">
                        <div>
                            <div class="chat-bubble">
                                <?= htmlspecialchars($msg['message']) ?>
                                <?php if (!empty($msg['video_url'])): ?>
                                    <div><a href="<?= htmlspecialchars($msg['video_url']) ?>" target="_blank" class="msg-video-link"><i class="fas fa-video"></i> Video</a></div>
                                <?php endif; ?>
                            </div>
                            <div class="chat-bubble-meta">
                                <?= htmlspecialchars($msg['sender_first'] . ' ' . $msg['sender_last']) ?> &bull; <?= date('M j, g:ia', strtotime($msg['created_at'])) ?>
                                <i class="fas fa-lock" style="font-size:10px;" title="Encrypted"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="chat-input-row">
                <input type="text" id="msg-input-<?= (int)$enrollment['id'] ?>" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendDevMessage(<?= (int)$enrollment['id'] ?>)">
                <button onclick="sendDevMessage(<?= (int)$enrollment['id'] ?>)"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Completed Programs History -->
    <?php if (!empty($completed_programs)): ?>
    <div class="dev-completed-section">
        <h3>
            <i class="fas fa-history"></i> Previous Programs
        </h3>
        <?php foreach ($completed_programs as $cp):
            $cp_display = $cp['program_name'] ?: ($cp['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development');
        ?>
        <div class="dev-completed-card">
            <div>
                <div class="completed-name">
                    <?php if ($cp['program_type'] === 'goalie_dev'): ?>
                        <i class="fas fa-shield-alt completed-icon-goalie"></i>
                    <?php else: ?>
                        <i class="fas fa-hockey-puck completed-icon-player"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($cp_display) ?>
                </div>
                <div class="completed-meta">
                    <?= date('M j, Y', strtotime($cp['enrolled_at'])) ?><?= $cp['completed_at'] ? ' — ' . date('M j, Y', strtotime($cp['completed_at'])) : '' ?>
                    &bull; <?= (int)$cp['drill_count'] ?> drills
                </div>
            </div>
            <span class="badge badge-<?= $cp['status'] === 'completed' ? 'success' : ($cp['status'] === 'paused' ? 'warning' : 'danger') ?>">
                <?= ucfirst(htmlspecialchars($cp['status'])) ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

<script>
const devCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function devFetch(data) {
    data.csrf_token = devCsrfToken;
    return fetch('process_development_programs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': devCsrfToken },
        body: JSON.stringify(data)
    }).then(r => r.json());
}

function sendDevMessage(enrollmentId) {
    const input = document.getElementById('msg-input-' + enrollmentId);
    const message = input.value.trim();
    if (!message) return;
    devFetch({ action: 'send_message', enrollment_id: enrollmentId, message: message })
    .then(data => {
        if (data.success) { input.value = ''; location.reload(); }
        else { alert(data.error || 'Failed to send message.'); }
    }).catch(() => alert('An error occurred.'));
}

function showDevUploadForm(enrollmentId, mode) {
    const form = document.getElementById('dev-upload-form-' + enrollmentId);
    if (form) {
        form.classList.add('active');
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const fileInput = document.getElementById('dev-video-file-' + enrollmentId);
        if (mode === 'record' && fileInput) {
            fileInput.setAttribute('capture', 'environment');
        } else if (fileInput) {
            fileInput.removeAttribute('capture');
        }
    }
}

/**
 * Submit development video using the application's standard presigned URL upload flow:
 * 1. POST to process_video.php with action=get_video_upload_url → presigned URL
 * 2. PUT file directly to RustFS/S3 via presigned URL with XHR progress
 * 3. POST to process_development_programs.php with action=confirm_dev_video_upload → DB insert
 * Falls back to legacy upload if presigned URL flow is unavailable.
 */
function submitDevVideo(enrollmentId) {
    var title = document.getElementById('dev-video-title-' + enrollmentId)?.value?.trim();
    var desc = document.getElementById('dev-video-desc-' + enrollmentId)?.value?.trim();
    var drillId = document.getElementById('dev-video-drill-' + enrollmentId)?.value || '';
    var fileInput = document.getElementById('dev-video-file-' + enrollmentId);
    var uploadBtn = fileInput?.closest('.dev-upload-card')?.querySelector('.btn-upload');

    if (!title) { alert('Please enter a title for your video.'); return; }
    if (!fileInput?.files?.length) { alert('Please select or record a video file.'); return; }

    var videoFile = fileInput.files[0];
    if (uploadBtn) { uploadBtn.disabled = true; uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...'; }

    // Phase 1: Get presigned URL from process_video.php
    var formMeta = new FormData();
    formMeta.append('action', 'get_video_upload_url');
    formMeta.append('upload_type', 'dev_video');
    formMeta.append('csrf_token', devCsrfToken);
    formMeta.append('title', title);
    formMeta.append('file_name', videoFile.name);
    formMeta.append('file_size', videoFile.size);
    formMeta.append('file_type', videoFile.type || 'video/mp4');

    var uploadNonce = null;
    var contentType = null;

    fetch('process_video.php', { method: 'POST', body: formMeta })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
            uploadNonce = data.upload_nonce;
            contentType = data.content_type || videoFile.type || 'application/octet-stream';
            var presignedUrl = data.presigned_url;
            if (!presignedUrl) throw new Error('No presigned URL — falling back to legacy upload');

            // Phase 2: PUT directly to RustFS
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('PUT', presignedUrl, true);
                xhr.setRequestHeader('Content-Type', contentType);
                var uploadStarted = false;
                var connTimer = setTimeout(function() {
                    if (!uploadStarted) { xhr.abort(); reject(new Error('Upload connection timed out')); }
                }, 30000);
                xhr.upload.onprogress = function(ev) {
                    if (!uploadStarted && ev.loaded > 0) { uploadStarted = true; clearTimeout(connTimer); }
                };
                xhr.onload = function() {
                    clearTimeout(connTimer);
                    if (xhr.status >= 200 && xhr.status < 300) resolve();
                    else reject(new Error('Upload failed (HTTP ' + xhr.status + ')'));
                };
                xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error')); };
                xhr.send(videoFile);
            });
        })
        .then(function() {
            // Phase 3: Confirm upload in DB
            var confirmForm = new FormData();
            confirmForm.append('action', 'confirm_dev_video_upload');
            confirmForm.append('csrf_token', devCsrfToken);
            confirmForm.append('upload_nonce', uploadNonce);
            confirmForm.append('enrollment_id', enrollmentId);
            if (drillId) confirmForm.append('drill_assignment_id', drillId);
            confirmForm.append('title', title);
            confirmForm.append('description', desc || '');
            return fetch('process_development_programs.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: confirmForm
            }).then(function(r) { return r.json(); });
        })
        .then(function(data) {
            if (data.success) { alert('Video submitted successfully! Your coach will be notified.'); location.reload(); }
            else { throw new Error(data.error || 'Failed to confirm upload'); }
        })
        .catch(function(err) {
            console.warn('[Dev Upload] Presigned URL flow failed:', err.message, '— falling back to legacy upload');
            // Legacy fallback: direct file upload
            var formData = new FormData();
            formData.append('action', 'upload_dev_video');
            formData.append('enrollment_id', enrollmentId);
            formData.append('title', title);
            formData.append('description', desc || '');
            if (drillId) formData.append('drill_assignment_id', drillId);
            formData.append('video_file', videoFile);
            fetch('process_development_programs.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': devCsrfToken },
                body: formData
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) { alert('Video submitted successfully! Your coach will be notified.'); location.reload(); }
                else { alert(data.error || 'Upload failed.'); }
                if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Submit Video'; }
            }).catch(function() {
                alert('Upload failed. Please try again.');
                if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Submit Video'; }
            });
        });
}
</script>
