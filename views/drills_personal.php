<?php
/**
 * Personal Drills - Create drills with video, title, and description
 * These drills are added directly to the drill library for use in development programs
 */
require_once __DIR__ . '/../lib/image_helper.php';

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Get personal drills created by this user (or all for admin)
$is_admin = ($user_role === 'admin');
if ($is_admin) {
    $personal_drills = $pdo->query("
        SELECT pd.*, u.first_name, u.last_name
        FROM personal_drills pd
        JOIN users u ON pd.created_by = u.id
        ORDER BY pd.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT pd.*, u.first_name, u.last_name
        FROM personal_drills pd
        JOIN users u ON pd.created_by = u.id
        WHERE pd.created_by = ?
        ORDER BY pd.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $personal_drills = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (function_exists('decryptUserRows')) {
    $personal_drills = decryptUserRows($personal_drills);
}
?>

<style>
.personal-drills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 16px;
}
.personal-drill-card {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.2s;
}
.personal-drill-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}
.personal-drill-card .drill-thumbnail {
    width: 100%;
    height: 140px;
    background: var(--bg-main, #0d1117);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.personal-drill-card .drill-thumbnail video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.personal-drill-card .drill-thumbnail .drill-thumbnail-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.personal-drill-card .drill-thumbnail .video-play-indicator {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 36px;
    color: rgba(255, 255, 255, 0.8);
    pointer-events: none;
    text-shadow: 0 2px 8px rgba(0,0,0,0.4);
}
.personal-drill-card .drill-thumbnail .position-icon {
    font-size: 48px;
    opacity: 0.4;
}
.personal-drill-card .drill-thumbnail .position-icon.player { color: var(--success, #10b981); }
.personal-drill-card .drill-thumbnail .position-icon.goalie { color: var(--info, #3b82f6); }
.personal-drill-card .drill-thumbnail .position-label {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.personal-drill-card .drill-thumbnail .position-label .position-badge-icon {
    font-size: 13px;
}
.personal-drill-card .drill-thumbnail .position-label.player { background: rgba(16, 185, 129, 0.15); color: var(--success, #10b981); }
.personal-drill-card .drill-thumbnail .position-label.goalie { background: rgba(59, 130, 246, 0.15); color: var(--info, #3b82f6); }
.personal-drill-card-body { padding: 16px 20px 20px; }
.personal-drill-card-body h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 8px;
}
.personal-drill-card-body p {
    font-size: 13px;
    color: var(--text-dim, #94a3b8);
    line-height: 1.5;
    margin-bottom: 12px;
}
.personal-drill-card-body .drill-meta {
    font-size: 11px;
    color: var(--text-dim, #94a3b8);
    border-top: 1px solid var(--border, #2d2d44);
    padding-top: 10px;
}
.personal-drill-card-actions {
    display: flex;
    gap: 6px;
    padding: 0 20px 16px;
}
.personal-drill-card-actions button {
    padding: 6px 12px;
    border: 1px solid var(--border, #2d2d44);
    border-radius: 6px;
    background: var(--bg-main, #0d1117);
    color: var(--text-white, #e2e8f0);
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.personal-drill-card-actions button:hover { border-color: var(--primary, #6B46C1); color: var(--primary, #6B46C1); }
.personal-drill-card-actions button.btn-delete-drill:hover { border-color: var(--error, #EF4444); color: var(--error, #EF4444); }
.create-personal-drill-form {
    background: var(--bg-card, #1a1a2e);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.create-personal-drill-form h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 16px;
}
.create-personal-drill-form .form-row {
    display: block;
    grid-template-columns: none;
    margin-bottom: 14px;
}
.create-personal-drill-form .form-row label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 6px;
}
.create-personal-drill-form .form-row input,
.create-personal-drill-form .form-row textarea,
.create-personal-drill-form .form-row select {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 8px;
    color: var(--text-white, #e2e8f0);
    font-size: 13px;
    font-family: inherit;
}
.create-personal-drill-form .form-row textarea {
    min-height: 80px;
    resize: vertical;
}
.btn-create-drill {
    padding: 10px 24px;
    background: var(--primary, #6B46C1);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: opacity 0.2s;
}
.btn-create-drill:hover { opacity: 0.9; }
</style>

<!-- Create Personal Drill Form -->
<div class="create-personal-drill-form">
    <h3><i class="fas fa-plus-circle"></i> Create Personal Drill</h3>
    <form id="personal-drill-form" enctype="multipart/form-data">
        <div class="form-row">
            <label for="pd-title">Title *</label>
            <input type="text" id="pd-title" name="title" required placeholder="Enter drill title">
        </div>
        <div class="form-row">
            <label for="pd-description">Description</label>
            <textarea id="pd-description" name="description" placeholder="Describe the drill, key points, and objectives"></textarea>
        </div>
        <div class="form-row">
            <label for="pd-position">Position</label>
            <select id="pd-position" name="position">
                <option value="player">Player (Skater)</option>
                <option value="goalie">Goalie</option>
            </select>
        </div>
        <div class="form-row">
            <label for="pd-video">Upload Video</label>
            <input type="file" id="pd-video" name="video_file" accept="video/mp4,video/webm,video/ogg,video/x-matroska,video/quicktime,video/x-msvideo">
            <p style="font-size:11px;color:var(--text-dim,#94a3b8);margin-top:4px;"><i class="fas fa-info-circle"></i> Supported: MP4, WebM, MOV, AVI, MKV. Max 10GB.</p>
        </div>
        <div id="pd-upload-progress" style="display:none;margin-bottom:14px;">
            <div style="background:var(--bg-main,#0d1117);border-radius:8px;overflow:hidden;height:8px;">
                <div id="pd-progress-bar" style="height:100%;background:var(--primary,#6B46C1);width:0%;transition:width 0.3s;"></div>
            </div>
            <p id="pd-progress-text" style="font-size:11px;color:var(--text-dim,#94a3b8);margin-top:4px;">Uploading...</p>
        </div>
        <button type="submit" class="btn-create-drill"><i class="fas fa-save"></i> Create Drill & Add to Library</button>
    </form>
</div>

<!-- Existing Personal Drills -->
<h3 style="font-size:15px;font-weight:700;color:var(--text-white);margin-bottom:4px;">Personal Drills (<?= count($personal_drills) ?>)</h3>
<p style="font-size:13px;color:var(--text-dim);margin-bottom:16px;">These drills are added to the drill library and can be assigned to development programs.</p>

<?php if (empty($personal_drills)): ?>
<div style="text-align:center;padding:40px 20px;color:var(--text-dim);">
    <i class="fas fa-plus-circle" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.5;"></i>
    <p>No personal drills created yet. Use the form above to create your first drill.</p>
</div>
<?php else: ?>
<div class="personal-drills-grid">
    <?php foreach ($personal_drills as $pd):
        $pdPosition = $pd['position'] ?? 'player';
    ?>
    <div class="personal-drill-card">
        <div class="drill-thumbnail">
            <?php if (!empty($pd['thumbnail_path'])): ?>
                <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $pd['thumbnail_path'])) ?>" alt="<?= htmlspecialchars($pd['title']) ?> thumbnail" class="drill-thumbnail-img">
            <?php elseif (!empty($pd['video_upload_path'])):
                $videoPath = $pd['video_upload_path'];
                $videoExt = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
                $videoMimeTypes = [
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'ogg' => 'video/ogg',
                    'ogv' => 'video/ogg'
                ];
                $videoMimeType = $videoMimeTypes[$videoExt] ?? 'video/mp4';
            ?>
                <video preload="metadata" muted aria-label="<?= htmlspecialchars($pd['title']) ?> video preview">
                    <source src="<?= htmlspecialchars($videoPath) ?>#t=0.5" type="<?= $videoMimeType ?>">
                </video>
                <div class="video-play-indicator"><i class="fas fa-play-circle"></i></div>
            <?php else: ?>
                <?php if ($pdPosition === 'goalie'): ?>
                    <span class="icon-hockey-goalie position-icon goalie"></span>
                <?php else: ?>
                    <span class="icon-hockey-player position-icon player"></span>
                <?php endif; ?>
            <?php endif; ?>
            <span class="position-label <?= htmlspecialchars($pdPosition) ?>"><span class="<?= $pdPosition === 'goalie' ? 'icon-hockey-goalie' : 'icon-hockey-player' ?> position-badge-icon"></span> <?= $pdPosition === 'goalie' ? 'Goalie' : 'Player' ?></span>
        </div>
        <div class="personal-drill-card-body">
            <h4><?= htmlspecialchars($pd['title']) ?></h4>
            <?php if ($pd['description']): ?>
                <p><?= htmlspecialchars(substr($pd['description'], 0, 200)) ?><?= strlen($pd['description']) > 200 ? '...' : '' ?></p>
            <?php endif; ?>
            <div class="drill-meta">
                Created by <?= htmlspecialchars($pd['first_name'] . ' ' . $pd['last_name']) ?> &bull; <?= date('M j, Y', strtotime($pd['created_at'])) ?>
            </div>
        </div>
        <div class="personal-drill-card-actions">
            <button type="button" onclick="editPersonalDrill(<?= (int)$pd['id'] ?>, <?= htmlspecialchars(json_encode($pd['title']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($pd['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($pdPosition), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-edit"></i> Edit</button>
            <button type="button" class="btn-delete-drill" onclick="deletePersonalDrill(<?= (int)$pd['id'] ?>, <?= htmlspecialchars(json_encode($pd['title']), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Edit Personal Drill Modal -->
<div id="edit-personal-drill-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div class="create-personal-drill-form" style="max-width:500px;width:90%;margin:0;max-height:90vh;overflow-y:auto;">
        <h3><i class="fas fa-edit"></i> Edit Personal Drill</h3>
        <form id="edit-personal-drill-form" enctype="multipart/form-data">
            <input type="hidden" id="edit-pd-id" name="drill_id">
            <div class="form-row">
                <label for="edit-pd-title">Title *</label>
                <input type="text" id="edit-pd-title" name="title" required placeholder="Enter drill title">
            </div>
            <div class="form-row">
                <label for="edit-pd-description">Description</label>
                <textarea id="edit-pd-description" name="description" placeholder="Describe the drill, key points, and objectives"></textarea>
            </div>
            <div class="form-row">
                <label for="edit-pd-position">Position</label>
                <select id="edit-pd-position" name="position">
                    <option value="player">Player (Skater)</option>
                    <option value="goalie">Goalie</option>
                </select>
            </div>
            <div class="form-row">
                <label for="edit-pd-video">Replace Video (optional)</label>
                <input type="file" id="edit-pd-video" name="video_file" accept="video/mp4,video/webm,video/ogg,video/x-matroska,video/quicktime,video/x-msvideo">
                <p style="font-size:11px;color:var(--text-dim,#94a3b8);margin-top:4px;"><i class="fas fa-info-circle"></i> Leave empty to keep current video.</p>
            </div>
            <div id="edit-pd-upload-progress" style="display:none;margin-bottom:14px;">
                <div style="background:var(--bg-main,#0d1117);border-radius:8px;overflow:hidden;height:8px;">
                    <div id="edit-pd-progress-bar" style="height:100%;background:var(--primary,#6B46C1);width:0%;transition:width 0.3s;"></div>
                </div>
                <p id="edit-pd-progress-text" style="font-size:11px;color:var(--text-dim,#94a3b8);margin-top:4px;">Uploading...</p>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn-create-drill"><i class="fas fa-save"></i> Save Changes</button>
                <button type="button" class="btn-create-drill" style="background:var(--bg-main,#0d1117);border:1px solid var(--border,#2d2d44);" onclick="closeEditModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('personal-drill-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const title = document.getElementById('pd-title').value.trim();
    if (!title) { alert('Title is required.'); return; }
    
    const videoInput = document.getElementById('pd-video');
    const videoFile = videoInput.files ? videoInput.files[0] : null;
    
    // Validate video file size (10GB max)
    if (videoFile && videoFile.size > 10 * 1024 * 1024 * 1024) {
        alert('Video file is too large. Maximum size is 10GB.');
        return;
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    const formData = new FormData();
    formData.append('action', 'create_personal_drill');
    formData.append('title', title);
    formData.append('description', document.getElementById('pd-description').value.trim());
    formData.append('position', document.getElementById('pd-position').value);
    if (videoFile) {
        formData.append('video_file', videoFile);
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    
    const progressDiv = document.getElementById('pd-upload-progress');
    const progressBar = document.getElementById('pd-progress-bar');
    const progressText = document.getElementById('pd-progress-text');
    
    if (videoFile) {
        progressDiv.style.display = 'block';
    }
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'process_development_programs.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = pct + '%';
            progressText.textContent = pct < 100 ? 'Uploading... ' + pct + '%' : 'Processing...';
        }
    });
    
    xhr.onload = function() {
        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to create drill.');
            }
        } catch (err) {
            alert('An error occurred.');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Create Drill & Add to Library';
        progressDiv.style.display = 'none';
        progressBar.style.width = '0%';
    };
    
    xhr.onerror = function() {
        alert('An error occurred.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Create Drill & Add to Library';
        progressDiv.style.display = 'none';
        progressBar.style.width = '0%';
    };
    
    xhr.send(formData);
});

// Edit personal drill - open modal with pre-populated data
function editPersonalDrill(id, title, description, position) {
    document.getElementById('edit-pd-id').value = id;
    document.getElementById('edit-pd-title').value = title;
    document.getElementById('edit-pd-description').value = description || '';
    document.getElementById('edit-pd-position').value = position || 'player';
    document.getElementById('edit-pd-video').value = '';
    const modal = document.getElementById('edit-personal-drill-modal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('edit-personal-drill-modal').style.display = 'none';
}

// Close modal on background click
document.getElementById('edit-personal-drill-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// Edit form submission
document.getElementById('edit-personal-drill-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const drillId = document.getElementById('edit-pd-id').value;
    const title = document.getElementById('edit-pd-title').value.trim();
    if (!title) { alert('Title is required.'); return; }

    const videoInput = document.getElementById('edit-pd-video');
    const videoFile = videoInput.files ? videoInput.files[0] : null;

    if (videoFile && videoFile.size > 10 * 1024 * 1024 * 1024) {
        alert('Video file is too large. Maximum size is 10GB.');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const formData = new FormData();
    formData.append('action', 'update_personal_drill');
    formData.append('drill_id', drillId);
    formData.append('title', title);
    formData.append('description', document.getElementById('edit-pd-description').value.trim());
    formData.append('position', document.getElementById('edit-pd-position').value);
    if (videoFile) {
        formData.append('video_file', videoFile);
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const progressDiv = document.getElementById('edit-pd-upload-progress');
    const progressBar = document.getElementById('edit-pd-progress-bar');
    const progressText = document.getElementById('edit-pd-progress-text');

    if (videoFile) {
        progressDiv.style.display = 'block';
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'process_development_programs.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = pct + '%';
            progressText.textContent = pct < 100 ? 'Uploading... ' + pct + '%' : 'Processing...';
        }
    });

    xhr.onload = function() {
        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to update drill.');
            }
        } catch (err) {
            alert('An error occurred.');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        progressDiv.style.display = 'none';
        progressBar.style.width = '0%';
    };

    xhr.onerror = function() {
        alert('An error occurred.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        progressDiv.style.display = 'none';
        progressBar.style.width = '0%';
    };

    xhr.send(formData);
});

// Delete personal drill with confirmation
function deletePersonalDrill(id, title) {
    if (!confirm('Are you sure you want to delete "' + title + '"? This cannot be undone.')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const formData = new FormData();
    formData.append('action', 'delete_personal_drill');
    formData.append('drill_id', id);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'process_development_programs.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);

    xhr.onload = function() {
        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to delete drill.');
            }
        } catch (err) {
            alert('An error occurred.');
        }
    };

    xhr.onerror = function() {
        alert('An error occurred.');
    };

    xhr.send(formData);
}
</script>
