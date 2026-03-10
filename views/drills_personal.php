<?php
/**
 * Personal Drills - Create drills with video, title, and description
 * These drills are added directly to the drill library for use in development programs
 */

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
.form-row {
    margin-bottom: 14px;
}
.form-row label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-white, #e2e8f0);
    margin-bottom: 6px;
}
.form-row input, .form-row textarea {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main, #0d1117);
    border: 1px solid var(--border, #2d2d44);
    border-radius: 8px;
    color: var(--text-white, #e2e8f0);
    font-size: 13px;
    font-family: inherit;
}
.form-row textarea {
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
            <select id="pd-position" name="position" style="width:100%;padding:10px 14px;background:var(--bg-main,#0d1117);border:1px solid var(--border,#2d2d44);border-radius:8px;color:var(--text-white,#e2e8f0);font-size:13px;">
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
            <?php if (!empty($pd['video_upload_path'])):
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
            <?php else: ?>
                <?php if ($pdPosition === 'goalie'): ?>
                    <span class="icon-hockey-goalie position-icon goalie"></span>
                <?php else: ?>
                    <span class="icon-hockey-player position-icon player"></span>
                <?php endif; ?>
            <?php endif; ?>
            <span class="position-label <?= htmlspecialchars($pdPosition) ?>"><?= $pdPosition === 'goalie' ? 'Goalie' : 'Player' ?></span>
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
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

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
</script>
