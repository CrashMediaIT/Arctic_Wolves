<?php
/**
 * PWA My Program - Mobile-native enrolled program view
 * Shows active enrollments with drill assignments, appointments, videos, and chat
 * Matches desktop personal_development_my_program.php features in mobile-friendly layout
 */
$user_id = $_SESSION['user_id'] ?? 0;

$enrollments = [];
try {
    $enrollments_stmt = $pdo->prepare("
        SELECT dpe.*, dpe.program_name, dpe.template_id, dpe.start_date, dpe.end_date
        FROM development_program_enrollments dpe
        WHERE dpe.athlete_id = ? AND dpe.status = 'active'
        ORDER BY dpe.enrolled_at DESC
    ");
    $enrollments_stmt->execute([$user_id]);
    $enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $enrollments = []; }

// Deduplicate enrollments by template_id (keep most recent)
$seen_templates = [];
$unique_enrollments = [];
foreach ($enrollments as $enrollment) {
    $tid = (int)($enrollment['template_id'] ?? 0);
    if ($tid > 0 && isset($seen_templates[$tid])) {
        continue;
    }
    if ($tid > 0) $seen_templates[$tid] = true;
    $unique_enrollments[] = $enrollment;
}
$enrollments = $unique_enrollments;

foreach ($enrollments as &$enrollment) {
    // Get drills with full details (matching desktop query)
    $enrollment['drills'] = [];
    try {
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
    } catch (PDOException $e) { /* ignore */ }
    if (function_exists('decryptUserRows')) {
        $enrollment['drills'] = decryptUserRows($enrollment['drills']);
    }
    if (class_exists('FieldEncryption')) {
        $enrollment['drills'] = FieldEncryption::decryptRows($enrollment['drills'], ['coach_first', 'coach_last']);
    }

    // Get recent messages
    $enrollment['messages'] = [];
    try {
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
    } catch (PDOException $e) { /* ignore */ }
    if (function_exists('decryptUserRows')) {
        $enrollment['messages'] = decryptUserRows($enrollment['messages']);
    }
    if (class_exists('FieldEncryption')) {
        $msg_fields = ['sender_first', 'sender_last'];
        try { $msg_fields = array_merge(FieldEncryption::MESSAGE_ENCRYPTED_FIELDS, $msg_fields); } catch (\Throwable $e) { /* constant may not exist */ }
        $enrollment['messages'] = FieldEncryption::decryptRows($enrollment['messages'], $msg_fields);
    }

    // Get athlete-uploaded videos
    $enrollment['videos'] = [];
    try {
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
    } catch (PDOException $e) { /* ignore */ }

    // Get upcoming appointments
    $enrollment['appointments'] = [];
    try {
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
    } catch (PDOException $e) { /* ignore */ }
    if (function_exists('decryptUserRows')) {
        $enrollment['appointments'] = decryptUserRows($enrollment['appointments']);
    }
    if (class_exists('FieldEncryption')) {
        $enrollment['appointments'] = FieldEncryption::decryptRows($enrollment['appointments'], ['coach_first', 'coach_last']);
    }
}
unset($enrollment);

$completed_programs = [];
try {
    $completed_stmt = $pdo->prepare("
        SELECT dpe.*, dpe.program_name,
               (SELECT COUNT(*) FROM development_program_drills dpd WHERE dpd.enrollment_id = dpe.id) as drill_count
        FROM development_program_enrollments dpe
        WHERE dpe.athlete_id = ? AND dpe.status IN ('completed', 'paused', 'cancelled')
        ORDER BY dpe.completed_at DESC, dpe.enrolled_at DESC
    ");
    $completed_stmt->execute([$user_id]);
    $completed_programs = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $completed_programs = []; }
?>
<style>
/* ===== My Program – Mobile-native list→detail layout ===== */
.m-myprog { font-family: Inter, sans-serif; min-height: 100%; }

/* --- LIST VIEW --- */
.m-myprog-list { padding: 16px; }
.m-myprog-header { margin-bottom: 16px; }
.m-myprog-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 6px; }
.m-myprog-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }

/* Program cards in the list */
.m-myprog-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 12px; cursor: pointer;
    transition: border-color 0.2s, transform 0.15s;
    display: flex !important; align-items: center; gap: 12px; min-height: 44px;
}
.m-myprog-card:active { border-color: rgba(107,70,193,0.5); transform: scale(0.98); }
.m-myprog-card-icon {
    width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #fff;
}
.m-myprog-card-body { flex: 1; min-width: 0; }
.m-myprog-card-name { font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-myprog-card-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.m-myprog-card-meta span { display: flex; align-items: center; gap: 3px; }
.m-myprog-card-arrow { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-myprog-weeks-badge {
    display: inline-block; font-size: 10px; font-weight: 600;
    padding: 2px 8px; border-radius: 20px;
}
.m-myprog-weeks-badge.active { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-weeks-badge.ended { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-myprog-card-stats {
    display: flex; gap: 12px; margin-top: 6px; font-size: 11px; color: #A8A8B8;
}
.m-myprog-card-stats span { display: flex; align-items: center; gap: 4px; }
.m-myprog-card-stats i { font-size: 10px; color: #8B5CF6; }

/* --- DETAIL VIEW (hidden by default, shown via JS) --- */
.m-myprog-detail {
    display: none; flex-direction: column;
    position: fixed; inset: 0; z-index: 100;
    background: #0A0A0F;
    animation: mProgSlideIn 0.25s ease-out;
}
@keyframes mProgSlideIn { from { transform: translateX(30%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.m-myprog.m-detail-active .m-myprog-list { display: none !important; }
.m-myprog.m-detail-active .m-myprog-detail { display: flex !important; }

/* Detail header */
.m-myprog-detail-header {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; background: #16161F;
    border-bottom: 1px solid #2D2D3F; flex-shrink: 0;
}
.m-myprog-back {
    width: 36px; height: 36px; border-radius: 50%;
    background: transparent; border: 1px solid #2D2D3F;
    color: #fff; font-size: 14px; display: flex;
    align-items: center; justify-content: center; cursor: pointer;
    flex-shrink: 0; min-height: 44px; min-width: 44px;
}
.m-myprog-back:active { background: rgba(107,70,193,0.2); }
.m-myprog-detail-title { flex: 1; min-width: 0; }
.m-myprog-detail-title h3 { font-size: 15px; font-weight: 700; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-myprog-detail-title span { font-size: 11px; color: #A8A8B8; }

/* Tab bar */
.m-myprog-tabs {
    display: flex; background: #16161F; border-bottom: 1px solid #2D2D3F;
    flex-shrink: 0; overflow-x: auto; -webkit-overflow-scrolling: touch;
}
.m-myprog-tabs::-webkit-scrollbar { display: none; }
.m-myprog-tab {
    flex: 1; min-width: 0; padding: 12px 6px; text-align: center;
    font-size: 12px; font-weight: 600; color: #6B6B7B;
    border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
    white-space: nowrap; min-height: 44px;
    display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 4px;
}
.m-myprog-tab i { font-size: 14px; }
.m-myprog-tab.active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-myprog-tab:active { color: #8B5CF6; }

/* Tab content area */
.m-myprog-tab-content {
    flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch;
    padding: 16px; min-height: 0;
}
.m-myprog-tab-pane { display: none; }
.m-myprog-tab-pane.active { display: block; }

/* --- OVERVIEW TAB --- */
.m-myprog-info-row {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-myprog-info-label { font-size: 10px; font-weight: 600; color: #8B5CF6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.m-myprog-info-value { font-size: 13px; color: #fff; font-weight: 500; }
.m-myprog-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.m-myprog-section-label {
    font-size: 13px; font-weight: 600; color: #8B5CF6; margin: 16px 0 8px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.m-myprog-section-label i { margin-right: 4px; }

/* Appointment cards */
.m-myprog-appt {
    background: #1E1E2E; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px; display: flex; gap: 10px; align-items: center;
}
.m-myprog-appt-date {
    min-width: 48px; text-align: center; padding: 6px 8px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 8px; color: #fff; flex-shrink: 0;
}
.m-myprog-appt-date .appt-day { font-size: 18px; font-weight: 700; display: block; }
.m-myprog-appt-date .appt-month { font-size: 10px; text-transform: uppercase; }
.m-myprog-appt-info { flex: 1; min-width: 0; }
.m-myprog-appt-info h5 { font-size: 13px; font-weight: 600; color: #fff; margin: 0 0 2px; }
.m-myprog-appt-meta { font-size: 11px; color: #A8A8B8; display: flex; flex-wrap: wrap; gap: 6px; }
.m-myprog-appt-meta i { margin-right: 2px; }
.m-myprog-appt-type {
    display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px;
    font-size: 10px; font-weight: 600; text-transform: uppercase;
}
.m-myprog-appt-type.call { background: rgba(16,185,129,0.15); color: #10B981; }
.m-myprog-appt-type.video_call { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-myprog-appt-type.in_person { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-overview-empty { text-align: center; padding: 20px; color: #6B6B7B; font-size: 12px; }
.m-myprog-overview-empty i { display: block; font-size: 20px; margin-bottom: 6px; }

/* --- DRILLS TAB --- */
.m-myprog-drill {
    display: block !important; /* Override global pwa.css [role="button"] inline-flex rule */
    background: #1E1E2E; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; cursor: pointer;
    transition: border-color 0.2s; width: 100%;
}
.m-myprog-drill:active { border-color: rgba(107,70,193,0.4); }
.m-myprog-drill-header { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.m-myprog-drill-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; }
.m-myprog-drill-desc { font-size: 12px; color: #A8A8B8; line-height: 1.4; margin-top: 6px; }
.m-myprog-drill-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 20px;
    flex-shrink: 0;
}
.m-myprog-drill-status.assigned { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-myprog-drill-status.pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-drill-status.completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-myprog-drill-status.in_progress { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-myprog-drill-expand {
    font-size: 12px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; margin-top: 8px;
    min-height: 44px; padding: 8px 0;
}
.m-myprog-drill-expand i { transition: transform 0.2s; }
.m-myprog-drill.expanded .m-myprog-drill-expand i { transform: rotate(180deg); }
.m-myprog-drill-detail {
    display: none; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #2D2D3F; font-size: 12px; color: #A8A8B8;
}
.m-myprog-drill.expanded .m-myprog-drill-detail { display: block; }
.m-myprog-drill-detail-section { margin-bottom: 10px; }
.m-myprog-drill-detail-label { font-size: 11px; font-weight: 600; color: #8B5CF6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.m-myprog-drill-detail p { margin: 0; line-height: 1.5; color: #A8A8B8; }
.m-myprog-drill-detail .coach-note { color: #F59E0B; }
.m-myprog-drill-detail .coach-name { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-myprog-drill-video-link {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 10px 14px; background: rgba(59,130,246,0.15); color: #3B82F6;
    border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none;
    margin-top: 4px; min-height: 44px;
}
.m-myprog-drill-video-link i { font-size: 14px; }
.m-myprog-drills-empty { text-align: center; padding: 30px 16px; color: #6B6B7B; font-size: 12px; }
.m-myprog-drills-empty i { display: block; font-size: 28px; margin-bottom: 8px; color: #2D2D3F; }

/* --- VIDEOS TAB --- */
.m-myprog-upload {
    background: #1E1E2E; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 12px;
}
.m-myprog-upload h4 { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 6px; }
.m-myprog-upload h4 i { margin-right: 4px; color: #8B5CF6; }
.m-myprog-upload p { font-size: 12px; color: #A8A8B8; margin: 0 0 12px; line-height: 1.4; }
.m-myprog-upload-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.m-myprog-upload-opt {
    display: block !important; /* Override global pwa.css [role="button"] inline-flex rule */
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px 12px; text-align: center; cursor: pointer;
    min-height: 44px; transition: border-color 0.2s;
}
.m-myprog-upload-opt:active { border-color: rgba(107,70,193,0.4); }
.m-myprog-upload-opt i { font-size: 22px; color: #8B5CF6; display: block; margin-bottom: 6px; }
.m-myprog-upload-opt span { font-size: 12px; font-weight: 600; color: #fff; }
.m-myprog-upload-form { display: none; }
.m-myprog-upload-form.active { display: block; }
.m-myprog-upload-form label { display: block; font-size: 11px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; text-transform: uppercase; }
.m-myprog-upload-form input[type="text"],
.m-myprog-upload-form textarea,
.m-myprog-upload-form select {
    width: 100%; padding: 10px 12px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 8px; color: #fff; font-size: 13px; font-family: Inter, sans-serif;
    min-height: 44px; margin-bottom: 10px; box-sizing: border-box;
}
.m-myprog-upload-form textarea { min-height: 60px; resize: vertical; }
.m-myprog-upload-form input[type="file"] { margin-bottom: 12px; color: #A8A8B8; font-size: 13px; }
.m-myprog-upload-btn {
    padding: 12px 20px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer;
    min-height: 44px; display: inline-flex; align-items: center; gap: 6px;
    width: 100%; justify-content: center;
}
.m-myprog-upload-btn:active { background: #5B3AA8; }
.m-myprog-drills-label { font-size: 12px; font-weight: 600; color: #8B5CF6; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-myprog-vid-item {
    background: #1E1E2E; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px; display: flex; gap: 10px; align-items: center;
}
.m-myprog-vid-thumb { width: 64px; height: 44px; border-radius: 8px; overflow: hidden; background: #0A0A0F; flex-shrink: 0; }
.m-myprog-vid-thumb img { width: 100%; height: 100%; object-fit: cover; }
.m-myprog-vid-info { flex: 1; min-width: 0; }
.m-myprog-vid-info h5 { font-size: 13px; font-weight: 600; color: #fff; margin: 0 0 2px; }
.m-myprog-vid-info span { font-size: 11px; color: #A8A8B8; }
.m-myprog-vid-info .coach-fb { font-size: 11px; color: #10B981; margin-top: 2px; }
.m-myprog-vid-info .coach-fb i { margin-right: 2px; }
.m-myprog-vid-status {
    padding: 3px 8px; border-radius: 20px; font-size: 10px; font-weight: 600;
    text-transform: uppercase; white-space: nowrap; flex-shrink: 0;
}
.m-myprog-vid-status.pending_review { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-vid-status.reviewed { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-myprog-vid-status.feedback_given { background: rgba(16,185,129,0.15); color: #10B981; }
.m-myprog-videos-empty { text-align: center; padding: 20px; color: #6B6B7B; font-size: 12px; }
.m-myprog-videos-empty i { display: block; font-size: 20px; margin-bottom: 6px; color: #2D2D3F; }

/* --- CHAT TAB (full-height flex layout) --- */
.m-myprog-tab-pane.m-myprog-pane-chat {
    display: none; flex-direction: column; height: 100%;
}
.m-myprog-tab-pane.m-myprog-pane-chat.active {
    display: flex;
}
.m-myprog-chat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-shrink: 0; }
.m-myprog-chat-title { font-size: 14px; font-weight: 600; color: #fff; }
.m-myprog-chat-title i { margin-right: 4px; }
.m-myprog-chat-badge { font-size: 10px; color: #6B6B7B; background: rgba(107,70,193,0.1); padding: 2px 6px; border-radius: 4px; }
.m-myprog-chat-badge i { margin-right: 2px; font-size: 9px; }
.m-myprog-chat-msgs {
    flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch;
    display: flex; flex-direction: column; gap: 4px;
    padding: 8px 0; min-height: 0;
}
.m-myprog-chat-empty { color: #6B6B7B; font-size: 12px; text-align: center; padding: 40px 20px; }
.m-myprog-chat-empty i { display: block; font-size: 28px; margin-bottom: 8px; color: #2D2D3F; }
.m-myprog-chat-row { display: flex; max-width: 85%; }
.m-myprog-chat-row.from-me { align-self: flex-end; }
.m-myprog-chat-row.from-coach { align-self: flex-start; }
.m-myprog-chat-bubble { padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
.m-myprog-chat-row.from-me .m-myprog-chat-bubble { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; border-bottom-right-radius: 4px; }
.m-myprog-chat-row.from-coach .m-myprog-chat-bubble { background: #16161F; color: #fff; border: 1px solid #2D2D3F; border-bottom-left-radius: 4px; }
.m-myprog-chat-meta { font-size: 10px; color: #6B6B7B; margin-top: 2px; }
.m-myprog-chat-row.from-me .m-myprog-chat-meta { text-align: right; }
.m-myprog-chat-input {
    display: flex; gap: 8px; flex-shrink: 0; padding-top: 10px;
    border-top: 1px solid #2D2D3F;
    padding-bottom: max(8px, env(safe-area-inset-bottom));
}
.m-myprog-chat-input input {
    flex: 1; padding: 10px 14px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 12px; color: #fff; font-size: 14px; min-height: 44px;
    font-family: Inter, sans-serif;
}
.m-myprog-chat-input input:focus { outline: none; border-color: #6B46C1; }
.m-myprog-chat-input button {
    padding: 10px 16px; background: #6B46C1; color: #fff; border: none;
    border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 14px;
    min-height: 44px; min-width: 44px;
}
.m-myprog-chat-input button:active { background: #5B3AA8; }

/* --- COMPLETED PROGRAMS (list view bottom) --- */
.m-myprog-section-title { font-size: 14px; font-weight: 600; color: #8B5CF6; margin: 24px 0 10px; display: flex; align-items: center; gap: 6px; }
.m-myprog-completed {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 16px; margin-bottom: 8px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;
    min-height: 44px;
}
.m-myprog-comp-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-myprog-comp-meta { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-myprog-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.m-myprog-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-myprog-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-myprog-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }

/* Empty state */
.m-empty-state { text-align: center; padding: 60px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 40px; display: block; margin-bottom: 14px; color: #2D2D3F; }
.m-empty-state p { margin: 0 0 12px; font-size: 14px; }

/* --- RESPONSIVE: small phones (<360px) --- */
@media (max-width: 359px) {
    .m-myprog-list { padding: 12px; }
    .m-myprog-tab-content { padding: 12px; }
    .m-myprog-card { padding: 12px; gap: 10px; }
    .m-myprog-card-icon { width: 44px; height: 44px; font-size: 15px; min-height: 44px; min-width: 44px; }
    .m-myprog-card-name { font-size: 14px; }
    .m-myprog-tabs { gap: 0; }
    .m-myprog-tab { font-size: 11px; padding: 10px 4px; }
    .m-myprog-tab i { font-size: 12px; }
    .m-myprog-upload-opts { grid-template-columns: 1fr 1fr; gap: 6px; }
    .m-myprog-upload-opt { padding: 12px 8px; }
    .m-myprog-info-grid { grid-template-columns: 1fr; }
    .m-myprog-chat-bubble { font-size: 13px; padding: 8px 12px; }
}
</style>

<div class="m-myprog">
    <!-- ==================== LIST VIEW ==================== -->
    <div class="m-myprog-list">
        <div class="m-myprog-header">
            <h2 class="m-myprog-title"><i class="fas fa-clipboard-list" style="color:#10B981;"></i> My Program</h2>
            <p class="m-myprog-sub">Your enrolled programs, drills, and progress</p>
        </div>

        <?php if (empty($enrollments)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>You are not enrolled in any programs.</p>
            <a href="?page=personal_development_programs" style="color:#8B5CF6;font-size:13px;display:inline-block;text-decoration:none;min-height:44px;line-height:44px;">Browse Programs <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php else: ?>
        <?php foreach ($enrollments as $idx => $enrollment):
            $prog_name = $enrollment['program_name'] ?: 'Development Program';
            $weeks_left = null;
            if (!empty($enrollment['end_date'])) {
                $end_ts = strtotime($enrollment['end_date']);
                $diff_days = ($end_ts - time()) / 86400;
                $weeks_left = max(0, ceil($diff_days / 7));
            }
        ?>
        <div class="m-myprog-card" onclick="mProgOpen(<?= $idx ?>)" role="button" tabindex="0" aria-label="Open <?= htmlspecialchars($prog_name) ?>" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();mProgOpen(<?= $idx ?>);}">
            <div class="m-myprog-card-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="m-myprog-card-body">
                <div class="m-myprog-card-name"><?= htmlspecialchars($prog_name) ?></div>
                <div class="m-myprog-card-meta">
                    <?php if ($weeks_left !== null): ?>
                    <span class="m-myprog-weeks-badge <?= $weeks_left > 0 ? 'active' : 'ended' ?>">
                        <?= $weeks_left > 0 ? $weeks_left . ' wk' . ($weeks_left !== 1 ? 's' : '') . ' left' : 'Ended' ?>
                    </span>
                    <?php endif; ?>
                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($enrollment['enrolled_at'])) ?></span>
                </div>
                <div class="m-myprog-card-stats">
                    <span><i class="fas fa-dumbbell"></i> <?= count($enrollment['drills']) ?> drills</span>
                    <span><i class="fas fa-video"></i> <?= count($enrollment['videos']) ?> videos</span>
                    <?php if (!empty($enrollment['appointments'])): ?>
                    <span><i class="fas fa-calendar-check"></i> <?= count($enrollment['appointments']) ?> upcoming</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="m-myprog-card-arrow"><i class="fas fa-chevron-right"></i></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($completed_programs)): ?>
        <div class="m-myprog-section-title"><i class="fas fa-history"></i> Past Programs</div>
        <?php foreach ($completed_programs as $cp):
            $cp_display = $cp['program_name'] ?: 'Development Program';
            $badge_class = $cp['status'] === 'completed' ? 'completed' : ($cp['status'] === 'paused' ? 'paused' : 'cancelled');
        ?>
        <div class="m-myprog-completed">
            <div>
                <div class="m-myprog-comp-name"><?= htmlspecialchars($cp_display) ?></div>
                <div class="m-myprog-comp-meta">
                    <?= date('M j, Y', strtotime($cp['enrolled_at'])) ?><?= $cp['completed_at'] ? ' — ' . date('M j, Y', strtotime($cp['completed_at'])) : '' ?>
                    &bull; <?= (int)$cp['drill_count'] ?> drills
                </div>
            </div>
            <span class="m-myprog-badge m-myprog-badge-<?= $badge_class ?>"><?= ucfirst(htmlspecialchars($cp['status'])) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ==================== DETAIL VIEW ==================== -->
    <div class="m-myprog-detail" id="mProgDetail">
        <div class="m-myprog-detail-header">
            <button class="m-myprog-back" id="mProgBack" aria-label="Back to programs"><i class="fas fa-chevron-left"></i></button>
            <div class="m-myprog-detail-title">
                <h3 id="mProgDetailName"></h3>
                <span id="mProgDetailMeta"></span>
            </div>
        </div>
        <div class="m-myprog-tabs" id="mProgTabs">
            <button class="m-myprog-tab active" data-tab="overview"><i class="fas fa-info-circle"></i> Overview</button>
            <button class="m-myprog-tab" data-tab="drills"><i class="fas fa-dumbbell"></i> Drills</button>
            <button class="m-myprog-tab" data-tab="videos"><i class="fas fa-video"></i> Videos</button>
            <button class="m-myprog-tab" data-tab="chat"><i class="fas fa-comments"></i> Chat</button>
        </div>
        <div class="m-myprog-tab-content" id="mProgTabContent">
            <!-- Tab panes are injected per-enrollment by JS -->
        </div>
    </div>
</div>

<!-- Hidden data containers for each enrollment (rendered server-side, consumed by JS) -->
<?php if (!empty($enrollments)): ?>
<?php foreach ($enrollments as $idx => $enrollment):
    $prog_name = $enrollment['program_name'] ?: 'Development Program';
    $weeks_left = null;
    if (!empty($enrollment['end_date'])) {
        $end_ts = strtotime($enrollment['end_date']);
        $diff_days = ($end_ts - time()) / 86400;
        $weeks_left = max(0, ceil($diff_days / 7));
    }
?>
<div id="mProgData-<?= $idx ?>" style="display:none;"
     data-id="<?= (int)$enrollment['id'] ?>"
     data-name="<?= htmlspecialchars($prog_name) ?>"
     data-weeks="<?= $weeks_left !== null ? ($weeks_left > 0 ? $weeks_left . ' wk' . ($weeks_left !== 1 ? 's' : '') . ' left' : 'Ended') : '' ?>"
     data-weeks-class="<?= $weeks_left !== null ? ($weeks_left > 0 ? 'active' : 'ended') : '' ?>"
     data-enrolled="<?= date('M j, Y', strtotime($enrollment['enrolled_at'])) ?>"
     data-start="<?= $enrollment['start_date'] ? date('M j, Y', strtotime($enrollment['start_date'])) : '' ?>"
     data-end="<?= $enrollment['end_date'] ? date('M j, Y', strtotime($enrollment['end_date'])) : '' ?>">

    <!-- OVERVIEW TAB CONTENT -->
    <div class="m-myprog-pane-data" data-pane="overview">
        <div class="m-myprog-info-grid">
            <div class="m-myprog-info-row">
                <div class="m-myprog-info-label">Enrolled</div>
                <div class="m-myprog-info-value"><?= date('M j, Y', strtotime($enrollment['enrolled_at'])) ?></div>
            </div>
            <?php if ($enrollment['start_date']): ?>
            <div class="m-myprog-info-row">
                <div class="m-myprog-info-label">Start Date</div>
                <div class="m-myprog-info-value"><?= date('M j, Y', strtotime($enrollment['start_date'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($enrollment['end_date']): ?>
            <div class="m-myprog-info-row">
                <div class="m-myprog-info-label">End Date</div>
                <div class="m-myprog-info-value"><?= date('M j, Y', strtotime($enrollment['end_date'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="m-myprog-info-row">
                <div class="m-myprog-info-label">Status</div>
                <div class="m-myprog-info-value" style="color:#10B981;">● Active</div>
            </div>
        </div>

        <?php if (!empty($enrollment['appointments'])): ?>
        <div class="m-myprog-section-label"><i class="fas fa-calendar-check"></i> Upcoming Sessions</div>
        <?php foreach ($enrollment['appointments'] as $appt): ?>
        <div class="m-myprog-appt">
            <div class="m-myprog-appt-date">
                <span class="appt-day"><?= date('j', strtotime($appt['appointment_date'])) ?></span>
                <span class="appt-month"><?= date('M', strtotime($appt['appointment_date'])) ?></span>
            </div>
            <div class="m-myprog-appt-info">
                <h5><?= htmlspecialchars($appt['title']) ?></h5>
                <div class="m-myprog-appt-meta">
                    <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($appt['appointment_time'])) ?> (<?= (int)$appt['duration_minutes'] ?> min)</span>
                    <span class="m-myprog-appt-type <?= htmlspecialchars($appt['appointment_type']) ?>"><?= str_replace('_', ' ', htmlspecialchars($appt['appointment_type'])) ?></span>
                    <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars(($appt['coach_first'] ?? '') . ' ' . ($appt['coach_last'] ?? '')) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="m-myprog-overview-empty">
            <i class="fas fa-calendar-check"></i>
            No upcoming sessions scheduled
        </div>
        <?php endif; ?>
    </div>

    <!-- DRILLS TAB CONTENT -->
    <div class="m-myprog-pane-data" data-pane="drills">
        <?php if (!empty($enrollment['drills'])): ?>
        <?php foreach ($enrollment['drills'] as $drill):
            $drill_status = $drill['status'] ?? 'assigned';
        ?>
        <div class="m-myprog-drill" onclick="mToggleDrill(this)" tabindex="0" role="button" aria-expanded="false" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();mToggleDrill(this);}">
            <div class="m-myprog-drill-header">
                <div class="m-myprog-drill-title"><?= htmlspecialchars($drill['drill_title'] ?? 'Untitled Drill') ?></div>
                <span class="m-myprog-drill-status <?= htmlspecialchars($drill_status) ?>">
                    <i class="fas fa-<?= $drill_status === 'completed' ? 'check' : ($drill_status === 'in_progress' ? 'spinner' : 'clock') ?>"></i>
                    <?= ucfirst(str_replace('_', ' ', $drill_status)) ?>
                </span>
            </div>
            <?php if (!empty($drill['drill_description'])): ?>
            <div class="m-myprog-drill-desc"><?= htmlspecialchars(mb_strimwidth($drill['drill_description'], 0, 120, '...')) ?></div>
            <?php endif; ?>
            <div class="m-myprog-drill-expand"><i class="fas fa-chevron-down"></i> Tap for details</div>

            <div class="m-myprog-drill-detail">
                <?php if (!empty($drill['drill_description'])): ?>
                <div class="m-myprog-drill-detail-section">
                    <div class="m-myprog-drill-detail-label">Description</div>
                    <p><?= nl2br(htmlspecialchars($drill['drill_description'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($drill['drill_setup'])): ?>
                <div class="m-myprog-drill-detail-section">
                    <div class="m-myprog-drill-detail-label">Setup</div>
                    <p><?= nl2br(htmlspecialchars($drill['drill_setup'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($drill['drill_coaching_points'])): ?>
                <div class="m-myprog-drill-detail-section">
                    <div class="m-myprog-drill-detail-label">Coaching Points</div>
                    <p><?= nl2br(htmlspecialchars($drill['drill_coaching_points'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($drill['coach_notes'])): ?>
                <div class="m-myprog-drill-detail-section">
                    <div class="m-myprog-drill-detail-label">Coach Notes</div>
                    <p class="coach-note"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($drill['coach_notes']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($drill['drill_video_url'])): ?>
                <div class="m-myprog-drill-detail-section">
                    <a href="<?= htmlspecialchars($drill['drill_video_url']) ?>" target="_blank" rel="noopener noreferrer" class="m-myprog-drill-video-link" onclick="event.stopPropagation();">
                        <i class="fas fa-play-circle"></i> Watch Drill Video
                    </a>
                </div>
                <?php endif; ?>
                <div class="coach-name"><i class="fas fa-user-tie"></i> Assigned by: <?= htmlspecialchars(($drill['coach_first'] ?? '') . ' ' . ($drill['coach_last'] ?? '')) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="m-myprog-drills-empty">
            <i class="fas fa-dumbbell"></i>
            No drills assigned yet.<br>Your coach will add drills to your program.
        </div>
        <?php endif; ?>
    </div>

    <!-- VIDEOS TAB CONTENT -->
    <div class="m-myprog-pane-data" data-pane="videos">
        <div class="m-myprog-upload">
            <h4><i class="fas fa-cloud-upload-alt"></i> Upload Video</h4>
            <p>Record or upload a video for your coach to review.</p>
            <div class="m-myprog-upload-opts">
                <div class="m-myprog-upload-opt" onclick="mShowUploadForm(<?= (int)$enrollment['id'] ?>, 'record')" role="button" tabindex="0">
                    <i class="fas fa-circle-dot"></i>
                    <span>Record</span>
                </div>
                <div class="m-myprog-upload-opt" onclick="mShowUploadForm(<?= (int)$enrollment['id'] ?>, 'upload')" role="button" tabindex="0">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Upload</span>
                </div>
            </div>
            <div class="m-myprog-upload-form" id="m-upload-form-<?= (int)$enrollment['id'] ?>">
                <label>Title *</label>
                <input type="text" id="m-vid-title-<?= (int)$enrollment['id'] ?>" placeholder="e.g. Skating drill practice">
                <label>Description</label>
                <textarea id="m-vid-desc-<?= (int)$enrollment['id'] ?>" placeholder="Optional notes..."></textarea>
                <?php if (!empty($enrollment['drills'])): ?>
                <label>Drill (optional)</label>
                <select id="m-vid-drill-<?= (int)$enrollment['id'] ?>">
                    <option value="">General Development Video</option>
                    <?php foreach ($enrollment['drills'] as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['drill_title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <label>Video File</label>
                <input type="file" id="m-vid-file-<?= (int)$enrollment['id'] ?>" accept="video/*" capture="environment">
                <button class="m-myprog-upload-btn" onclick="mSubmitDevVideo(<?= (int)$enrollment['id'] ?>)">
                    <i class="fas fa-cloud-upload-alt"></i> Submit Video
                </button>
            </div>
        </div>

        <?php if (!empty($enrollment['videos'])): ?>
        <div class="m-myprog-drills-label" style="margin-top:12px;"><i class="fas fa-film"></i> Your Submitted Videos</div>
        <?php foreach ($enrollment['videos'] as $vid): ?>
        <div class="m-myprog-vid-item">
            <?php if (!empty($vid['thumbnail_path'])): ?>
            <div class="m-myprog-vid-thumb">
                <img src="<?= htmlspecialchars($vid['thumbnail_path']) ?>" alt="Thumbnail" loading="lazy">
            </div>
            <?php endif; ?>
            <div class="m-myprog-vid-info">
                <h5><?= htmlspecialchars($vid['title']) ?></h5>
                <span><?= date('M j, Y', strtotime($vid['created_at'])) ?><?= !empty($vid['drill_title']) ? ' &bull; ' . htmlspecialchars($vid['drill_title']) : '' ?></span>
                <?php if (!empty($vid['coach_feedback'])): ?>
                <div class="coach-fb"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($vid['coach_feedback']) ?></div>
                <?php endif; ?>
            </div>
            <span class="m-myprog-vid-status <?= htmlspecialchars($vid['status'] ?? 'pending_review') ?>"><?= str_replace('_', ' ', htmlspecialchars($vid['status'] ?? 'pending review')) ?></span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="m-myprog-videos-empty" style="margin-top:12px;">
            <i class="fas fa-film"></i>
            No videos submitted yet
        </div>
        <?php endif; ?>
    </div>

    <!-- CHAT TAB CONTENT -->
    <div class="m-myprog-pane-data" data-pane="chat">
        <div class="m-myprog-chat-header">
            <div class="m-myprog-chat-title"><i class="fas fa-comments"></i> Program Chat</div>
            <span class="m-myprog-chat-badge"><i class="fas fa-lock"></i> Encrypted</span>
        </div>
        <div class="m-myprog-chat-msgs" id="m-chat-<?= (int)$enrollment['id'] ?>">
            <?php if (empty($enrollment['messages'])): ?>
            <div class="m-myprog-chat-empty">
                <i class="fas fa-comments"></i>
                No messages yet.<br>Start a conversation with your coach.
            </div>
            <?php else: ?>
            <?php foreach ($enrollment['messages'] as $msg): ?>
            <div class="m-myprog-chat-row <?= (int)$msg['sender_id'] === (int)$user_id ? 'from-me' : 'from-coach' ?>">
                <div>
                    <div class="m-myprog-chat-bubble">
                        <?= htmlspecialchars($msg['message']) ?>
                        <?php if (!empty($msg['video_url'])): ?>
                        <div><a href="<?= htmlspecialchars($msg['video_url']) ?>" target="_blank" style="color:inherit;font-size:12px;"><i class="fas fa-video"></i> Video</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-myprog-chat-meta">
                        <?= htmlspecialchars(($msg['sender_first'] ?? '') . ' ' . ($msg['sender_last'] ?? '')) ?> &bull; <?= date('M j, g:ia', strtotime($msg['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="m-myprog-chat-input">
            <input type="text" id="m-msg-input-<?= (int)$enrollment['id'] ?>" placeholder="Type a message..." onkeydown="if(event.key==='Enter')mSendDevMessage(<?= (int)$enrollment['id'] ?>)">
            <button onclick="mSendDevMessage(<?= (int)$enrollment['id'] ?>)"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
var mDevCsrf = document.querySelector('meta[name="csrf-token"]')?.content
            || (document.querySelector('[name="csrf_token"]') ? document.querySelector('[name="csrf_token"]').value : '');

var mProgCurrentIdx = null;

/* ---- Open detail view for a program ---- */
function mProgOpen(idx) {
    var dataEl = document.getElementById('mProgData-' + idx);
    if (!dataEl) return;
    mProgCurrentIdx = idx;

    var container = document.querySelector('.m-myprog');
    var detail = document.getElementById('mProgDetail');
    var tabContent = document.getElementById('mProgTabContent');

    // Set header info
    document.getElementById('mProgDetailName').textContent = dataEl.dataset.name;
    var metaParts = [];
    if (dataEl.dataset.weeks) metaParts.push(dataEl.dataset.weeks);
    metaParts.push('Enrolled ' + dataEl.dataset.enrolled);
    document.getElementById('mProgDetailMeta').textContent = metaParts.join(' · ');

    // Move pane data into the tab content area
    tabContent.innerHTML = '';
    var panes = dataEl.querySelectorAll('.m-myprog-pane-data');
    panes.forEach(function(pane) {
        var wrapper = document.createElement('div');
        var paneName = pane.dataset.pane;
        wrapper.className = 'm-myprog-tab-pane' + (paneName === 'chat' ? ' m-myprog-pane-chat' : '');
        wrapper.dataset.pane = paneName;
        wrapper.innerHTML = pane.innerHTML;
        tabContent.appendChild(wrapper);
    });

    // Reset to overview tab
    var tabs = document.querySelectorAll('#mProgTabs .m-myprog-tab');
    tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.tab === 'overview'); });
    var allPanes = tabContent.querySelectorAll('.m-myprog-tab-pane');
    allPanes.forEach(function(p) { p.classList.toggle('active', p.dataset.pane === 'overview'); });

    // Show detail, hide list
    container.classList.add('m-detail-active');

    // Hide PWA shell elements
    var tabBar = document.querySelector('.pwa-tab-bar');
    if (tabBar) tabBar.style.display = 'none';
    var pwaHeader = document.querySelector('.pwa-header');
    if (pwaHeader) pwaHeader.style.display = 'none';

    // Scroll chat to bottom if chat messages exist
    setTimeout(function() {
        var chatMsgs = tabContent.querySelector('.m-myprog-chat-msgs');
        if (chatMsgs) chatMsgs.scrollTop = chatMsgs.scrollHeight;
    }, 50);
}

/* ---- Close detail view, return to list ---- */
function mProgClose() {
    var container = document.querySelector('.m-myprog');

    // Before closing, sync any changed content back to the hidden data containers
    if (mProgCurrentIdx !== null) {
        var tabContent = document.getElementById('mProgTabContent');
        var dataEl = document.getElementById('mProgData-' + mProgCurrentIdx);
        if (tabContent && dataEl) {
            var activePanes = tabContent.querySelectorAll('.m-myprog-tab-pane');
            activePanes.forEach(function(pane) {
                var paneName = pane.dataset.pane;
                var sourcePane = dataEl.querySelector('.m-myprog-pane-data[data-pane="' + paneName + '"]');
                if (sourcePane) sourcePane.innerHTML = pane.innerHTML;
            });
        }
    }

    container.classList.remove('m-detail-active');
    mProgCurrentIdx = null;

    // Restore PWA shell
    var tabBar = document.querySelector('.pwa-tab-bar');
    if (tabBar) tabBar.style.display = '';
    var pwaHeader = document.querySelector('.pwa-header');
    if (pwaHeader) pwaHeader.style.display = '';
}

/* ---- Back button ---- */
document.getElementById('mProgBack').addEventListener('click', function() { mProgClose(); });

/* ---- Tab switching ---- */
document.getElementById('mProgTabs').addEventListener('click', function(e) {
    var tab = e.target.closest('.m-myprog-tab');
    if (!tab) return;
    var targetPane = tab.dataset.tab;

    // Update tab active state
    var allTabs = document.querySelectorAll('#mProgTabs .m-myprog-tab');
    allTabs.forEach(function(t) { t.classList.toggle('active', t === tab); });

    // Update pane visibility
    var allPanes = document.querySelectorAll('#mProgTabContent .m-myprog-tab-pane');
    allPanes.forEach(function(p) { p.classList.toggle('active', p.dataset.pane === targetPane); });

    // Scroll chat to bottom when switching to chat
    if (targetPane === 'chat') {
        setTimeout(function() {
            var chatMsgs = document.querySelector('#mProgTabContent .m-myprog-chat-msgs');
            if (chatMsgs) chatMsgs.scrollTop = chatMsgs.scrollHeight;
        }, 50);
    }
});

/* ---- Drill expand/collapse ---- */
function mToggleDrill(el) {
    el.classList.toggle('expanded');
    el.setAttribute('aria-expanded', el.classList.contains('expanded') ? 'true' : 'false');
}

/* ---- API helper ---- */
function mDevFetch(data) {
    data.csrf_token = mDevCsrf;
    return fetch('process_development_programs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': mDevCsrf },
        body: JSON.stringify(data)
    }).then(function(r) { return r.json(); });
}

/* ---- Send chat message ---- */
function mSendDevMessage(enrollmentId) {
    var input = document.getElementById('m-msg-input-' + enrollmentId);
    var message = input.value.trim();
    if (!message) return;
    mDevFetch({ action: 'send_message', enrollment_id: enrollmentId, message: message })
    .then(function(data) {
        if (data.success) { input.value = ''; location.reload(); }
        else { if (typeof showToast === 'function') showToast(data.error || 'Failed to send', 'error'); else alert(data.error || 'Failed to send.'); }
    }).catch(function() { if (typeof showToast === 'function') showToast('Network error', 'error'); else alert('An error occurred.'); });
}

/* ---- Show upload form ---- */
function mShowUploadForm(enrollmentId, mode) {
    var form = document.getElementById('m-upload-form-' + enrollmentId);
    if (form) {
        form.classList.add('active');
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var fileInput = document.getElementById('m-vid-file-' + enrollmentId);
        if (mode === 'record' && fileInput) {
            fileInput.setAttribute('capture', 'environment');
        } else if (fileInput) {
            fileInput.removeAttribute('capture');
        }
    }
}

/* ---- Submit video ---- */
function mSubmitDevVideo(enrollmentId) {
    var title = (document.getElementById('m-vid-title-' + enrollmentId)?.value || '').trim();
    var desc = (document.getElementById('m-vid-desc-' + enrollmentId)?.value || '').trim();
    var drillSel = document.getElementById('m-vid-drill-' + enrollmentId);
    var drillId = drillSel ? drillSel.value : '';
    var fileInput = document.getElementById('m-vid-file-' + enrollmentId);
    var uploadBtn = fileInput?.closest('.m-myprog-upload')?.querySelector('.m-myprog-upload-btn');

    if (!title) { if (typeof showToast === 'function') showToast('Please enter a title', 'error'); else alert('Please enter a title.'); return; }
    if (!fileInput?.files?.length) { if (typeof showToast === 'function') showToast('Please select a video', 'error'); else alert('Please select a video.'); return; }

    var videoFile = fileInput.files[0];
    if (uploadBtn) { uploadBtn.disabled = true; uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...'; }

    var formMeta = new FormData();
    formMeta.append('action', 'get_video_upload_url');
    formMeta.append('upload_type', 'dev_video');
    formMeta.append('csrf_token', mDevCsrf);
    formMeta.append('title', title);
    formMeta.append('file_name', videoFile.name);
    formMeta.append('file_size', videoFile.size);
    formMeta.append('file_type', videoFile.type || 'video/mp4');

    var uploadNonce = null;

    fetch('process_video.php', { method: 'POST', body: formMeta })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
            uploadNonce = data.upload_nonce;
            var presignedUrl = data.presigned_url;
            if (!presignedUrl) throw new Error('No presigned URL');
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('PUT', presignedUrl, true);
                xhr.setRequestHeader('Content-Type', data.content_type || videoFile.type || 'application/octet-stream');
                xhr.onload = function() { xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error('Upload HTTP ' + xhr.status)); };
                xhr.onerror = function() { reject(new Error('Network error')); };
                xhr.send(videoFile);
            });
        })
        .then(function() {
            var confirmForm = new FormData();
            confirmForm.append('action', 'confirm_dev_video_upload');
            confirmForm.append('csrf_token', mDevCsrf);
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
            if (data.success) { if (typeof showToast === 'function') showToast('Video submitted!', 'success'); location.reload(); }
            else { throw new Error(data.error || 'Failed to confirm'); }
        })
        .catch(function(err) {
            var formData = new FormData();
            formData.append('action', 'upload_dev_video');
            formData.append('enrollment_id', enrollmentId);
            formData.append('title', title);
            formData.append('description', desc || '');
            if (drillId) formData.append('drill_assignment_id', drillId);
            formData.append('video_file', videoFile);
            fetch('process_development_programs.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': mDevCsrf },
                body: formData
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) { if (typeof showToast === 'function') showToast('Video submitted!', 'success'); location.reload(); }
                else { if (typeof showToast === 'function') showToast(data.error || 'Upload failed', 'error'); else alert(data.error || 'Upload failed.'); }
                if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Submit Video'; }
            }).catch(function() {
                if (typeof showToast === 'function') showToast('Upload failed', 'error'); else alert('Upload failed.');
                if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Submit Video'; }
            });
        });
}

/* ---- Handle browser back ---- */
window.addEventListener('popstate', function() {
    if (document.querySelector('.m-myprog.m-detail-active')) {
        mProgClose();
    }
});
</script>
