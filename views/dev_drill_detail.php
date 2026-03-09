<?php
/**
 * Development Program - Drill Detail View (Full Page)
 * Athletes can view full drill details, watch videos, update status, and record/upload videos.
 * Uses the same presigned URL video upload flow as the rest of the application.
 */

$user_id = $_SESSION['user_id'] ?? 0;
$drill_assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$enrollment_id = isset($_GET['enrollment_id']) ? (int)$_GET['enrollment_id'] : 0;

if (!$drill_assignment_id) {
    echo '<div style="text-align:center;padding:60px 20px;color:var(--text-dim,#94a3b8);"><i class="fas fa-exclamation-triangle" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i><h3 style="color:var(--text-white,#e2e8f0);">Drill Not Found</h3><p>The drill you are looking for could not be found.</p><a href="?page=personal_development_my_program" style="color:var(--primary,#6B46C1);">← Back to My Program</a></div>';
    return;
}

// Fetch drill assignment with full drill data
$stmt = $pdo->prepare("
    SELECT dpd.*, d.title as drill_title, d.description as drill_description,
           d.video_url as drill_video_url, d.custom_image as drill_image,
           d.setup as drill_setup, d.coaching_points as drill_coaching_points,
           d.progression as drill_progression,
           d.diagram_data,
           u.first_name as coach_first, u.last_name as coach_last,
           dpe.athlete_id, dpe.program_type
    FROM development_program_drills dpd
    JOIN drills d ON dpd.drill_id = d.id
    JOIN users u ON dpd.assigned_by = u.id
    JOIN development_program_enrollments dpe ON dpd.enrollment_id = dpe.id
    WHERE dpd.id = ? AND dpe.athlete_id = ?
");
$stmt->execute([$drill_assignment_id, $user_id]);
$drill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drill) {
    echo '<div style="text-align:center;padding:60px 20px;color:var(--text-dim,#94a3b8);"><i class="fas fa-lock" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i><h3 style="color:var(--text-white,#e2e8f0);">Access Denied</h3><p>You do not have access to this drill.</p><a href="?page=personal_development_my_program" style="color:var(--primary,#6B46C1);">← Back to My Program</a></div>';
    return;
}

if (function_exists('decryptUserRows')) {
    $drill = decryptUserRows([$drill])[0];
}

// Resolve enrollment_id from drill if not passed
if (!$enrollment_id) {
    $enrollment_id = (int)$drill['enrollment_id'];
}

// Get submitted videos for this drill
$videos_stmt = $pdo->prepare("
    SELECT dpv.*
    FROM development_program_videos dpv
    WHERE dpv.enrollment_id = ? AND dpv.athlete_id = ? AND dpv.drill_assignment_id = ?
    ORDER BY dpv.created_at DESC
");
$videos_stmt->execute([$enrollment_id, $user_id, $drill_assignment_id]);
$drill_videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all drills for this enrollment (for next/prev navigation)
$all_drills_stmt = $pdo->prepare("
    SELECT dpd.id, d.title as drill_title
    FROM development_program_drills dpd
    JOIN drills d ON dpd.drill_id = d.id
    WHERE dpd.enrollment_id = ?
    ORDER BY dpd.sort_order, dpd.created_at
");
$all_drills_stmt->execute([$enrollment_id]);
$all_drills = $all_drills_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine YouTube embed
$youtubeId = null;
$drillVideoUrl = $drill['drill_video_url'] ?? '';
if (!empty($drillVideoUrl) && preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $drillVideoUrl, $matches)) {
    $youtubeId = $matches[1];
}

// Resolve RustFS URLs
$drillImage = '';
if (!empty($drill['drill_image'])) {
    $drillImage = function_exists('resolveRustfsUrl') ? resolveRustfsUrl($pdo, $drill['drill_image']) : $drill['drill_image'];
}
?>

<style>
.dev-drill-page { max-width: 900px; }
.dev-drill-back {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--primary);
    text-decoration: none; margin-bottom: var(--space-4);
}
.dev-drill-back:hover { opacity: 0.8; }
.dev-drill-header {
    background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl); padding: var(--space-6); margin-bottom: var(--space-5);
}
.dev-drill-header h1 {
    font-size: var(--font-size-3xl); font-weight: var(--font-weight-black); color: var(--text-white); margin: 0 0 var(--space-3);
}
.dev-drill-header-meta { display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; }
.dev-drill-status {
    display: inline-flex; align-items: center; padding: 4px var(--space-3); border-radius: var(--radius-2xl);
    font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); text-transform: uppercase;
}
.dev-drill-status.assigned { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.dev-drill-status.in_progress { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.dev-drill-status.completed { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.dev-drill-coach { font-size: var(--font-size-sm); color: var(--text-dim); }
.dev-drill-coach i { margin-right: 4px; }
.dev-drill-actions { display: flex; gap: 10px; margin-top: var(--space-4); flex-wrap: wrap; }
.dev-drill-actions .btn {
    padding: 0 var(--space-5); height: 40px; border-radius: var(--radius-lg); font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);
    border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: all var(--transition-normal);
}
.dev-drill-actions .btn:hover { transform: translateY(-1px); }
.dev-drill-actions .btn-primary { background: var(--primary); color: var(--text-white); }
.dev-drill-actions .btn-primary:hover { background: var(--primary-hover); box-shadow: var(--shadow-primary); }
.dev-drill-actions .btn-secondary { background: rgba(59, 130, 246, 0.12); color: var(--info); border: 1px solid rgba(59, 130, 246, 0.25); }
.dev-drill-actions .btn-success { background: rgba(16, 185, 129, 0.12); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.25); }

.dev-drill-card {
    background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-2xl); padding: var(--space-6); margin-bottom: var(--space-5);
}
.dev-drill-card h3 {
    font-size: var(--font-size-md); font-weight: var(--font-weight-bold); color: var(--text-white);
    margin: 0 0 var(--space-4); display: flex; align-items: center; gap: var(--space-2);
}
.dev-drill-card h3 i { color: var(--primary); font-size: var(--font-size-base); }
.dev-drill-section { margin-bottom: var(--space-5); }
.dev-drill-section:last-child { margin-bottom: 0; }
.dev-drill-section h4 {
    font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--primary);
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--space-2);
}
.dev-drill-section p {
    font-size: var(--font-size-base); color: var(--text-dim); line-height: 1.7; margin: 0;
    white-space: pre-wrap;
}
.dev-drill-section .coach-note {
    color: var(--warning); padding: var(--space-3) var(--space-4); background: rgba(245, 158, 11, 0.06);
    border: 1px solid rgba(245, 158, 11, 0.15); border-radius: var(--radius-lg);
}

/* Video section */
.dev-drill-video-embed {
    margin: var(--space-3) 0; border-radius: var(--radius-xl); overflow: hidden;
    background: #000; aspect-ratio: 16/9;
}
.dev-drill-video-embed iframe { width: 100%; height: 100%; border: none; }
.dev-drill-video-link {
    display: inline-flex; align-items: center; gap: var(--space-2);
    padding: var(--space-3) var(--space-5); background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F); border-radius: var(--radius-lg);
    color: var(--primary); font-weight: var(--font-weight-semibold); font-size: var(--font-size-base);
    text-decoration: none; transition: border-color var(--transition-normal);
}
.dev-drill-video-link:hover { border-color: rgba(107, 70, 193, 0.4); }

/* Drill image */
.dev-drill-image { max-width: 100%; border-radius: var(--radius-lg); margin: var(--space-2) 0; }

/* Submitted videos list */
.dev-drill-video-list { display: flex; flex-direction: column; gap: 10px; }
.dev-drill-video-item {
    background: var(--bg-main, #0A0A0F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg); padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;
}
.dev-drill-video-item .video-info h5 { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0 0 4px; }
.dev-drill-video-item .video-info span { font-size: var(--font-size-sm); color: var(--text-dim); }
.dev-drill-video-item .video-status {
    padding: 4px var(--space-3); border-radius: var(--radius-2xl); font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); text-transform: uppercase;
}
.dev-drill-video-item .video-status.pending_review { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.dev-drill-video-item .video-status.reviewed { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.dev-drill-video-item .video-status.feedback_given { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.dev-drill-video-item .video-feedback { margin-top: 6px; font-size: var(--font-size-sm); color: var(--success); }

/* Upload section */
.dev-drill-upload {
    background: var(--bg-main, #0A0A0F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-xl); padding: var(--space-5);
}
.dev-drill-upload h4 { font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-white); margin: 0 0 var(--space-3); }
.dev-drill-upload label {
    display: block; font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-dim); margin-bottom: var(--space-1);
}
.dev-drill-upload input[type="text"],
.dev-drill-upload textarea {
    width: 100%; padding: 10px var(--space-3); background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F); border-radius: var(--radius-lg);
    color: var(--text-white); font-size: var(--font-size-sm); margin-bottom: 10px;
}
.dev-drill-upload textarea { min-height: 60px; resize: vertical; }
.dev-drill-upload .upload-progress-wrap {
    display: none; width: 100%; height: 8px; background: var(--border);
    border-radius: var(--radius-sm); margin: 10px 0; overflow: hidden;
}
.dev-drill-upload .upload-progress-bar {
    width: 0%; height: 100%; background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: var(--radius-sm); transition: width 0.2s;
}
.dev-drill-upload .upload-status {
    font-size: var(--font-size-sm); color: var(--text-dim); margin: 6px 0;
}
.dev-drill-upload .btn-upload {
    padding: 0 var(--space-5); height: 40px; background: var(--primary); color: var(--text-white);
    border: none; border-radius: var(--radius-lg); font-weight: var(--font-weight-semibold); font-size: var(--font-size-sm);
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: all var(--transition-normal);
}
.dev-drill-upload .btn-upload:hover { background: var(--primary-hover); }
.dev-drill-upload .btn-upload:disabled { opacity: 0.5; cursor: not-allowed; }
.dev-drill-file-input { margin-bottom: 10px; color: var(--text-dim); }

/* Navigation */
.dev-drill-nav { display: flex; justify-content: space-between; margin-top: var(--space-5); }
.dev-drill-nav a {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--primary);
    text-decoration: none; padding: var(--space-2) 14px;
    background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg); transition: border-color var(--transition-normal);
}
.dev-drill-nav a:hover { border-color: rgba(107, 70, 193, 0.4); }

@media (max-width: 600px) {
    .dev-drill-header h1 { font-size: var(--font-size-xl); }
    .dev-drill-actions { flex-direction: column; }
    .dev-drill-actions .btn { width: 100%; justify-content: center; }
}
</style>

<div class="dev-drill-page">
    <a href="?page=personal_development_my_program" class="dev-drill-back">
        <i class="fas fa-arrow-left"></i> Back to My Program
    </a>

    <!-- Header -->
    <div class="dev-drill-header">
        <h1><?= htmlspecialchars($drill['drill_title']) ?></h1>
        <div class="dev-drill-header-meta">
            <span class="dev-drill-status <?= htmlspecialchars($drill['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($drill['status'])) ?></span>
            <span class="dev-drill-coach"><i class="fas fa-user-tie"></i> Assigned by <?= htmlspecialchars(trim(($drill['coach_first'] ?? '') . ' ' . ($drill['coach_last'] ?? ''))) ?></span>
            <span class="dev-drill-coach"><i class="fas fa-hockey-puck"></i> <?= $drill['program_type'] === 'goalie_dev' ? 'Goalie Development' : 'Player Development' ?></span>
        </div>
        <div class="dev-drill-actions">
            <?php if ($drill['status'] === 'assigned'): ?>
            <button class="btn btn-secondary" onclick="updateDrillStatus(<?= (int)$drill['id'] ?>, 'in_progress')"><i class="fas fa-play"></i> Mark In Progress</button>
            <?php elseif ($drill['status'] === 'in_progress'): ?>
            <button class="btn btn-success" onclick="updateDrillStatus(<?= (int)$drill['id'] ?>, 'completed')"><i class="fas fa-check"></i> Mark Completed</button>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="document.getElementById('dev-drill-upload-section').scrollIntoView({behavior:'smooth'})"><i class="fas fa-video"></i> Upload Video</button>
        </div>
    </div>

    <!-- Drill Details -->
    <div class="dev-drill-card">
        <h3><i class="fas fa-info-circle"></i> Drill Details</h3>

        <?php if (!empty($drill['drill_description'])): ?>
        <div class="dev-drill-section">
            <h4>Description</h4>
            <p><?= nl2br(htmlspecialchars($drill['drill_description'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($drill['drill_setup'])): ?>
        <div class="dev-drill-section">
            <h4><i class="fas fa-cog"></i> Setup</h4>
            <p><?= nl2br(htmlspecialchars($drill['drill_setup'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($drill['drill_coaching_points'])): ?>
        <div class="dev-drill-section">
            <h4><i class="fas fa-bullseye"></i> Coaching Points</h4>
            <p><?= nl2br(htmlspecialchars($drill['drill_coaching_points'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($drill['drill_progression'])): ?>
        <div class="dev-drill-section">
            <h4><i class="fas fa-level-up-alt"></i> Progression</h4>
            <p><?= nl2br(htmlspecialchars($drill['drill_progression'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($drill['equipment_needed'])): ?>
        <div class="dev-drill-section">
            <h4><i class="fas fa-tools"></i> Equipment Needed</h4>
            <p><?= nl2br(htmlspecialchars($drill['equipment_needed'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($drill['drill_notes'])): ?>
        <div class="dev-drill-section">
            <h4><i class="fas fa-sticky-note"></i> Notes</h4>
            <p><?= nl2br(htmlspecialchars($drill['drill_notes'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($drill['coach_notes'])): ?>
        <div class="dev-drill-section">
            <h4><i class="fas fa-comment-dots"></i> Coach Notes</h4>
            <p class="coach-note"><?= nl2br(htmlspecialchars($drill['coach_notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Drill Video -->
    <?php if (!empty($drillVideoUrl)): ?>
    <div class="dev-drill-card">
        <h3><i class="fas fa-video"></i> Drill Video</h3>
        <?php if ($youtubeId): ?>
        <div class="dev-drill-video-embed">
            <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtubeId) ?>" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php else: ?>
        <a href="<?= htmlspecialchars($drillVideoUrl) ?>" target="_blank" class="dev-drill-video-link">
            <i class="fas fa-play-circle"></i> Watch Drill Video
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Drill Diagram/Image -->
    <?php if (!empty($drillImage)): ?>
    <div class="dev-drill-card">
        <h3><i class="fas fa-drafting-compass"></i> Drill Diagram</h3>
        <img src="<?= htmlspecialchars($drillImage) ?>" alt="Drill diagram" class="dev-drill-image">
    </div>
    <?php endif; ?>

    <!-- Submitted Videos -->
    <div class="dev-drill-card">
        <h3><i class="fas fa-film"></i> Your Submitted Videos (<?= count($drill_videos) ?>)</h3>
        <?php if (empty($drill_videos)): ?>
            <p style="color:var(--text-dim);font-size:14px;">No videos submitted for this drill yet. Use the upload section below to submit a video for your coach to review.</p>
        <?php else: ?>
        <div class="dev-drill-video-list">
            <?php foreach ($drill_videos as $vid): ?>
            <div class="dev-drill-video-item">
                <div class="video-info">
                    <h5><?= htmlspecialchars($vid['title']) ?></h5>
                    <span><?= date('M j, Y g:ia', strtotime($vid['created_at'])) ?></span>
                    <?php if (!empty($vid['description'])): ?>
                    <p style="margin:4px 0 0;font-size:13px;color:var(--text-dim);"><?= htmlspecialchars($vid['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($vid['coach_feedback'])): ?>
                    <div class="video-feedback"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($vid['coach_feedback']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="video-status <?= htmlspecialchars($vid['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($vid['status'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Upload Video -->
    <div class="dev-drill-card" id="dev-drill-upload-section">
        <h3><i class="fas fa-cloud-upload-alt"></i> Upload Video for this Drill</h3>
        <div class="dev-drill-upload">
            <label for="dev-drill-video-title">Title *</label>
            <input type="text" id="dev-drill-video-title" placeholder="e.g. Skating drill practice attempt #1">
            <label for="dev-drill-video-desc">Description</label>
            <textarea id="dev-drill-video-desc" placeholder="Notes for your coach about this video..."></textarea>
            <label for="dev-drill-video-file">Video File *</label>
            <input type="file" id="dev-drill-video-file" accept="video/*" capture="environment" class="dev-drill-file-input">
            <div class="upload-progress-wrap" id="dev-drill-progress-wrap">
                <div class="upload-progress-bar" id="dev-drill-progress-bar"></div>
            </div>
            <div class="upload-status" id="dev-drill-upload-status"></div>
            <button class="btn-upload" id="dev-drill-upload-btn" onclick="submitDrillVideo()">
                <i class="fas fa-cloud-upload-alt"></i> Submit Video
            </button>
        </div>
    </div>

    <!-- Navigation between drills -->
    <?php
    $current_idx = null;
    foreach ($all_drills as $i => $ad) {
        if ((int)$ad['id'] === $drill_assignment_id) { $current_idx = $i; break; }
    }
    $prev_drill = ($current_idx !== null && $current_idx > 0) ? $all_drills[$current_idx - 1] : null;
    $next_drill = ($current_idx !== null && $current_idx < count($all_drills) - 1) ? $all_drills[$current_idx + 1] : null;
    ?>
    <div class="dev-drill-nav">
        <?php if ($prev_drill): ?>
        <a href="?page=dev_drill_detail&id=<?= (int)$prev_drill['id'] ?>&enrollment_id=<?= $enrollment_id ?>">
            <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($prev_drill['drill_title']) ?>
        </a>
        <?php else: ?>
        <span></span>
        <?php endif; ?>
        <?php if ($next_drill): ?>
        <a href="?page=dev_drill_detail&id=<?= (int)$next_drill['id'] ?>&enrollment_id=<?= $enrollment_id ?>">
            <?= htmlspecialchars($next_drill['drill_title']) ?> <i class="fas fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>

<script>
var devDrillCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

function updateDrillStatus(drillAssignmentId, status) {
    fetch('process_development_programs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': devDrillCsrf },
        body: JSON.stringify({ action: 'update_drill_status', drill_assignment_id: drillAssignmentId, status: status })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) location.reload();
        else alert(data.error || 'Failed to update status.');
    }).catch(function() { alert('An error occurred.'); });
}

/** Shared XHR upload with progress tracking */
function xhrUploadWithProgress(method, url, headers, body, progressBar, statusEl, prefix) {
    return new Promise(function(resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        for (var h in headers) { if (headers.hasOwnProperty(h)) xhr.setRequestHeader(h, headers[h]); }
        var uploadStarted = false;
        var connTimer = setTimeout(function() {
            if (!uploadStarted) { xhr.abort(); reject(new Error('Upload connection timed out')); }
        }, 30000);
        xhr.upload.onprogress = function(ev) {
            if (!uploadStarted && ev.loaded > 0) { uploadStarted = true; clearTimeout(connTimer); }
            if (ev.lengthComputable) {
                var pct = Math.round((ev.loaded / ev.total) * 100);
                progressBar.style.width = pct + '%';
                statusEl.textContent = pct < 100 ? (prefix || 'Uploading... ') + pct + '%' : 'Finalizing...';
            }
        };
        xhr.onload = function() {
            clearTimeout(connTimer);
            if (xhr.status >= 200 && xhr.status < 300) resolve(xhr);
            else reject(new Error('Upload failed (HTTP ' + xhr.status + ')'));
        };
        xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error')); };
        xhr.send(body);
    });
}

function resetUploadBtn(btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Submit Video';
}

/**
 * Submit drill video using the application's standard presigned URL upload flow:
 * 1. POST to process_video.php with action=get_video_upload_url to get a presigned URL
 * 2. PUT file directly to RustFS/S3 via presigned URL with XHR progress tracking
 * 3. POST to process_video.php with action=confirm_video_upload to finalize in DB
 * Falls back to legacy direct upload if presigned URL flow is unavailable.
 */
function submitDrillVideo() {
    var title = document.getElementById('dev-drill-video-title').value.trim();
    var desc = document.getElementById('dev-drill-video-desc').value.trim();
    var fileInput = document.getElementById('dev-drill-video-file');
    var btn = document.getElementById('dev-drill-upload-btn');
    var progressWrap = document.getElementById('dev-drill-progress-wrap');
    var progressBar = document.getElementById('dev-drill-progress-bar');
    var statusEl = document.getElementById('dev-drill-upload-status');

    if (!title) { alert('Please enter a title for your video.'); return; }
    if (!fileInput.files || !fileInput.files.length) { alert('Please select or record a video file.'); return; }

    var videoFile = fileInput.files[0];
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    progressWrap.style.display = 'block';
    progressBar.style.width = '0%';
    statusEl.textContent = 'Requesting upload URL...';

    // Phase 1: Get presigned URL from process_video.php
    var formMeta = new FormData();
    formMeta.append('action', 'get_video_upload_url');
    formMeta.append('upload_type', 'dev_video');
    formMeta.append('csrf_token', devDrillCsrf);
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
            if (!presignedUrl) throw new Error('No presigned URL returned — falling back to legacy upload');

            statusEl.textContent = 'Uploading to cloud storage...';

            // Phase 2: PUT directly to RustFS using shared progress helper
            return xhrUploadWithProgress('PUT', presignedUrl, { 'Content-Type': contentType }, videoFile, progressBar, statusEl, 'Uploading... ');
        })
        .then(function() {
            // Phase 3: Confirm upload
            statusEl.textContent = 'Confirming upload...';
            var confirmForm = new FormData();
            confirmForm.append('action', 'confirm_dev_video_upload');
            confirmForm.append('csrf_token', devDrillCsrf);
            confirmForm.append('upload_nonce', uploadNonce);
            confirmForm.append('enrollment_id', '<?= $enrollment_id ?>');
            confirmForm.append('drill_assignment_id', '<?= $drill_assignment_id ?>');
            confirmForm.append('title', title);
            confirmForm.append('description', desc);
            return fetch('process_development_programs.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: confirmForm
            }).then(function(r) { return r.json(); });
        })
        .then(function(data) {
            if (data.success) {
                statusEl.textContent = 'Upload complete!';
                progressBar.style.width = '100%';
                alert('Video submitted successfully! Your coach will be notified.');
                location.reload();
            } else {
                throw new Error(data.error || 'Failed to confirm upload');
            }
        })
        .catch(function(err) {
            console.warn('[Dev Upload] Presigned URL flow failed:', err.message, '— falling back to legacy upload');
            statusEl.textContent = 'Falling back to legacy upload...';
            progressBar.style.width = '0%';

            // Legacy fallback: direct file upload to process_development_programs.php
            var formData = new FormData();
            formData.append('action', 'upload_dev_video');
            formData.append('enrollment_id', '<?= $enrollment_id ?>');
            formData.append('title', title);
            formData.append('description', desc);
            formData.append('drill_assignment_id', '<?= $drill_assignment_id ?>');
            formData.append('video_file', videoFile);

            xhrUploadWithProgress('POST', 'process_development_programs.php', {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': devDrillCsrf
            }, formData, progressBar, statusEl, 'Uploading... ')
            .then(function(xhr) {
                var d = JSON.parse(xhr.responseText);
                if (d.success) {
                    statusEl.textContent = 'Upload complete!';
                    progressBar.style.width = '100%';
                    alert('Video submitted successfully! Your coach will be notified.');
                    location.reload();
                } else {
                    statusEl.textContent = 'Upload failed: ' + (d.error || 'Unknown error');
                    resetUploadBtn(btn);
                }
            })
            .catch(function() {
                statusEl.textContent = 'Upload failed. Please try again.';
                resetUploadBtn(btn);
            });
        });
}
</script>
