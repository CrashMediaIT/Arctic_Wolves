<?php
/**
 * My Program - View assigned drills and communicate with coach
 * Shows athlete's enrolled programs with assigned drills, video upload, and upcoming appointments
 */

$user_id = $_SESSION['user_id'] ?? 0;

// Get user's active enrollments with assigned drills
$enrollments_stmt = $pdo->prepare("
    SELECT dpe.*
    FROM development_program_enrollments dpe
    WHERE dpe.athlete_id = ? AND dpe.status = 'active'
    ORDER BY dpe.enrolled_at DESC
");
$enrollments_stmt->execute([$user_id]);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

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
}
unset($enrollment);
?>

<style>
.my-program-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-dim, #94a3b8);
}
.my-program-empty i { font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5; }
.enrollment-section {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.enrollment-section h3 {
    font-size: 18px; font-weight: 700;
    color: var(--text-white, #e2e8f0); margin-bottom: 16px;
}
.enrollment-section h3 i { margin-right: 8px; }
.enrollment-section .section-label {
    font-size: 14px; font-weight: 600; color: var(--text-white, #e2e8f0);
    margin: 20px 0 12px; padding-top: 16px;
    border-top: 1px solid var(--border, #2d2d44);
}
.enrollment-section .section-label:first-of-type { border-top: none; margin-top: 0; padding-top: 0; }
.enrollment-section .section-label i { margin-right: 6px; }

/* Drill cards - clickable links */
.drill-list { display: flex; flex-direction: column; gap: 12px; }
.drill-card {
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 10px; padding: 16px; cursor: pointer;
    transition: border-color 0.2s, transform 0.15s, box-shadow 0.2s;
}
.drill-card:hover {
    border-color: rgba(107, 70, 193, 0.4); transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.drill-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.drill-card-header h4 { font-size: 15px; font-weight: 600; color: var(--text-white, #e2e8f0); margin: 0; }
.drill-card p { font-size: 13px; color: var(--text-dim, #94a3b8); line-height: 1.5; margin: 0 0 6px; }
.drill-card-footer { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.drill-card-footer .btn-view-drill {
    font-size: 12px; color: var(--primary, #6B46C1); font-weight: 600;
    text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
}
.drill-status {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.drill-status.assigned { background: rgba(59,130,246,0.15); color: #3b82f6; }
.drill-status.in_progress { background: rgba(245,158,11,0.15); color: #f59e0b; }
.drill-status.completed { background: rgba(16,185,129,0.15); color: #10b981; }

/* Upload section */
.dev-upload-section {
    margin-top: 20px; padding-top: 16px;
    border-top: 1px solid var(--border, #2d2d44);
}
.dev-upload-card {
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 10px; padding: 20px;
}
.dev-upload-card h4 { font-size: 15px; font-weight: 600; color: var(--text-white, #e2e8f0); margin-bottom: 12px; }
.dev-upload-card h4 i { margin-right: 6px; }
.dev-upload-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.dev-upload-option {
    background: var(--bg-card, #1a1a2e); border: 1px solid var(--border, #2d2d44);
    border-radius: 8px; padding: 16px; text-align: center; cursor: pointer;
    transition: border-color 0.2s, transform 0.15s;
}
.dev-upload-option:hover { border-color: rgba(107,70,193,0.4); transform: translateY(-1px); }
.dev-upload-option i { font-size: 28px; color: var(--primary, #6B46C1); display: block; margin-bottom: 8px; }
.dev-upload-option span { font-size: 13px; font-weight: 600; color: var(--text-white, #e2e8f0); }
.dev-upload-form { display: none; }
.dev-upload-form.active { display: block; }
.dev-upload-form label { display: block; font-size: 13px; font-weight: 600; color: var(--text-dim, #94a3b8); margin-bottom: 4px; }
.dev-upload-form input[type="text"],
.dev-upload-form input[type="url"],
.dev-upload-form textarea,
.dev-upload-form select {
    width: 100%; padding: 10px 12px; background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44); border-radius: 8px;
    color: var(--text-white, #e2e8f0); font-size: 13px; margin-bottom: 10px;
}
.dev-upload-form textarea { min-height: 60px; resize: vertical; }
.dev-upload-form .btn-upload {
    padding: 10px 20px; background: var(--primary, #6B46C1); color: #fff;
    border: none; border-radius: 8px; font-weight: 600; font-size: 13px;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}

/* Uploaded videos list */
.dev-video-list { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
.dev-video-item {
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;
}
.dev-video-item .video-info h5 { font-size: 14px; font-weight: 600; color: var(--text-white, #e2e8f0); margin: 0 0 4px; }
.dev-video-item .video-info span { font-size: 12px; color: var(--text-dim, #94a3b8); }
.dev-video-item .video-status {
    padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.dev-video-item .video-status.pending_review { background: rgba(245,158,11,0.15); color: #f59e0b; }
.dev-video-item .video-status.reviewed { background: rgba(59,130,246,0.15); color: #3b82f6; }
.dev-video-item .video-status.feedback_given { background: rgba(16,185,129,0.15); color: #10b981; }

/* Upcoming appointments */
.dev-appointments { display: flex; flex-direction: column; gap: 10px; }
.dev-appointment-card {
    background: var(--bg-main, #0d1117); border: 1px solid var(--border, #2d2d44);
    border-radius: 10px; padding: 16px; display: flex; gap: 16px; align-items: center;
}
.dev-appointment-card .appt-date-box {
    min-width: 56px; text-align: center; padding: 8px 10px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), #8b5cf6);
    border-radius: 8px; color: #fff;
}
.dev-appointment-card .appt-date-box .appt-day { font-size: 20px; font-weight: 700; display: block; }
.dev-appointment-card .appt-date-box .appt-month { font-size: 11px; text-transform: uppercase; }
.dev-appointment-card .appt-details { flex: 1; }
.dev-appointment-card .appt-details h5 { font-size: 14px; font-weight: 600; color: var(--text-white, #e2e8f0); margin: 0 0 4px; }
.dev-appointment-card .appt-meta { font-size: 12px; color: var(--text-dim, #94a3b8); display: flex; gap: 12px; flex-wrap: wrap; }
.dev-appointment-card .appt-meta i { margin-right: 3px; }
.dev-appointment-type {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.dev-appointment-type.call { background: rgba(16,185,129,0.15); color: #10b981; }
.dev-appointment-type.video_call { background: rgba(59,130,246,0.15); color: #3b82f6; }
.dev-appointment-type.in_person { background: rgba(245,158,11,0.15); color: #f59e0b; }

/* Chat */
.program-chat { margin-top: 20px; border-top: 1px solid var(--border, #2d2d44); padding-top: 16px; }
.chat-messages { max-height: 300px; overflow-y: auto; margin-bottom: 12px; }
.chat-msg { padding: 8px 12px; margin-bottom: 8px; border-radius: 8px; font-size: 13px; }
.chat-msg.from-coach { background: rgba(107,70,193,0.1); border-left: 3px solid var(--primary, #6B46C1); }
.chat-msg.from-me { background: rgba(59,130,246,0.1); border-left: 3px solid #3b82f6; }
.chat-msg .msg-meta { font-size: 11px; color: var(--text-dim, #94a3b8); margin-bottom: 4px; }
.chat-input-row { display: flex; gap: 8px; }
.chat-input-row input {
    flex: 1; padding: 10px 14px; background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44); border-radius: 8px;
    color: var(--text-white, #e2e8f0); font-size: 13px;
}
.chat-input-row button {
    padding: 10px 16px; background: var(--primary, #6B46C1); color: #fff;
    border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px;
}
@media (max-width: 600px) {
    .dev-upload-options { grid-template-columns: 1fr; }
    .dev-appointment-card { flex-direction: column; text-align: center; }
    .dev-appointment-card .appt-meta { justify-content: center; }
}
</style>

<?php if (empty($enrollments)): ?>
<div class="my-program-empty">
    <i class="fas fa-hockey-puck"></i>
    <h3>No Active Programs</h3>
    <p>You haven't enrolled in any development programs yet. Visit the <a href="?page=personal_development_programs" style="color:var(--primary);">Development Programs</a> tab to register.</p>
</div>
<?php else: ?>
    <?php foreach ($enrollments as $enrollment): ?>
    <div class="enrollment-section">
        <h3>
            <?php if ($enrollment['program_type'] === 'goalie_dev'): ?>
                <i class="fas fa-shield-alt" style="color:#3b82f6;"></i> Long Term Goalie Development
            <?php else: ?>
                <i class="fas fa-hockey-puck" style="color:#10b981;"></i> Long Term Player Development
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
            <p style="color:var(--text-dim);font-size:14px;">No drills assigned yet. Your coach will add drills to your program soon.</p>
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
                        <p style="color:#f59e0b;font-size:12px;"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($drill['coach_notes']) ?></p>
                    <?php endif; ?>
                    <div class="drill-card-footer">
                        <span class="btn-view-drill"><i class="fas fa-eye"></i> View Full Details</span>
                        <?php if ($drill['drill_video_url']): ?>
                        <span class="btn-view-drill" style="color:#3b82f6;"><i class="fas fa-play-circle"></i> Has Video</span>
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
                <p style="font-size:13px;color:var(--text-dim);margin-bottom:14px;">Record or upload a video for your coach to review. You can submit general development videos or videos specific to an assigned drill.</p>
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
            <div style="margin-top:16px;">
                <h4 style="font-size:14px;font-weight:600;color:var(--text-white);margin-bottom:10px;"><i class="fas fa-film"></i> Your Submitted Videos</h4>
                <div class="dev-video-list">
                    <?php foreach ($enrollment['videos'] as $vid): ?>
                    <div class="dev-video-item">
                        <div class="video-info">
                            <h5><?= htmlspecialchars($vid['title']) ?></h5>
                            <span><?= date('M j, Y g:ia', strtotime($vid['created_at'])) ?><?= $vid['drill_title'] ? ' &bull; ' . htmlspecialchars($vid['drill_title']) : '' ?></span>
                            <?php if ($vid['coach_feedback']): ?>
                            <p style="margin:4px 0 0;font-size:12px;color:#10b981;"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($vid['coach_feedback']) ?></p>
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
            <h4 style="font-size:14px;font-weight:600;color:var(--text-white);margin-bottom:12px;"><i class="fas fa-comments"></i> Program Chat</h4>
            <div class="chat-messages" id="chat-<?= (int)$enrollment['id'] ?>">
                <?php if (empty($enrollment['messages'])): ?>
                    <p style="color:var(--text-dim);font-size:13px;text-align:center;padding:20px;">No messages yet. Start a conversation with your coach.</p>
                <?php else: ?>
                    <?php foreach ($enrollment['messages'] as $msg): ?>
                    <div class="chat-msg <?= $msg['sender_id'] == $user_id ? 'from-me' : 'from-coach' ?>">
                        <div class="msg-meta"><?= htmlspecialchars($msg['sender_first'] . ' ' . $msg['sender_last']) ?> &bull; <?= date('M j, g:ia', strtotime($msg['created_at'])) ?></div>
                        <?= htmlspecialchars($msg['message']) ?>
                        <?php if ($msg['video_url']): ?>
                            <div style="margin-top:6px;"><a href="<?= htmlspecialchars($msg['video_url']) ?>" target="_blank" style="color:var(--primary);font-size:12px;"><i class="fas fa-video"></i> Video</a></div>
                        <?php endif; ?>
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
<?php endif; ?>

<script>
const devCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function devFetch(data) {
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
