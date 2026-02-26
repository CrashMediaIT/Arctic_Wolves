<?php
/**
 * Video Review Detail Page
 * Full page view for athletes to see video details, coach notes, and respond.
 * Coaches can also use this page to review and add notes.
 */
require_once __DIR__ . '/../lib/image_helper.php';

$video_id = filter_input(INPUT_GET, 'video_id', FILTER_VALIDATE_INT);

if (!$video_id) {
    echo '<div class="page-header"><h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> Video Not Found</h1></div>';
    echo '<div style="max-width:800px; margin:24px auto; padding:40px; text-align:center; color:var(--text-dim);">';
    echo '<p>Invalid video ID. <a href="?page=coaches_reviews" style="color:var(--primary);">Return to Coach Review</a></p>';
    echo '</div>';
    return;
}

// Fetch video with related data
$stmt = $pdo->prepare("
    SELECT v.*, 
           a.first_name as athlete_first_name, a.last_name as athlete_last_name,
           c.first_name as coach_first_name, c.last_name as coach_last_name
    FROM videos v
    LEFT JOIN users a ON v.athlete_id = a.id
    LEFT JOIN users c ON v.coach_id = c.id
    WHERE v.id = ? AND (v.athlete_id = ? OR v.coach_id = ? OR a.assigned_coach_id = ?)
");
$stmt->execute([$video_id, $user_id, $user_id, $user_id]);
$video = $stmt->fetch();

if (!$video) {
    echo '<div class="page-header"><h1 class="page-title"><i class="fas fa-lock"></i> Access Denied</h1></div>';
    echo '<div style="max-width:800px; margin:24px auto; padding:40px; text-align:center; color:var(--text-dim);">';
    echo '<p>Video not found or you don\'t have permission to view it. <a href="?page=coaches_reviews" style="color:var(--primary);">Return to Coach Review</a></p>';
    echo '</div>';
    return;
}

// Decrypt names
foreach (['athlete_first_name', 'athlete_last_name', 'coach_first_name', 'coach_last_name'] as $f) {
    if (!empty($video[$f])) $video[$f] = FieldEncryption::decrypt($video[$f]);
}

$video_url = resolveRustfsUrl($pdo, $video['video_url'] ?? '') ?? '';
$csrf_token = $_SESSION['csrf_token'] ?? '';
$is_coach = $isAnyCoach;
$is_owner = (int)$video['athlete_id'] === (int)$user_id;
?>

<div class="page-header" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
    <a href="?page=coaches_reviews" style="color:var(--text-dim); text-decoration:none; font-size:14px; display:flex; align-items:center; gap:6px;">
        <i class="fas fa-arrow-left"></i> Back to Coach Review
    </a>
</div>

<div style="max-width: 1000px; margin: 0 auto; padding: 0 16px;">
    <!-- Video Player -->
    <div style="background: #000; border-radius: 12px; overflow: hidden; margin-bottom: 24px; border: 1px solid var(--border);">
        <video id="detailVideoPlayer" controls style="width: 100%; max-height: 500px; display: block;">
            <source src="<?= htmlspecialchars($video_url) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 380px; gap: 24px;">
        <!-- Left: Video Info & Edit -->
        <div>
            <!-- Title & Description (editable for owner/coach) -->
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                    <h2 id="videoTitle" style="margin:0; font-size:20px; font-weight:700; color:var(--text-white);"><?= htmlspecialchars($video['title']) ?></h2>
                    <span style="padding:6px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; <?= $video['status'] === 'reviewed' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(245,158,11,0.15); color:#F59E0B;' ?>">
                        <i class="fas <?= $video['status'] === 'reviewed' ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                        <?= ucfirst(str_replace('_', ' ', $video['status'])) ?>
                    </span>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px; font-size:13px; color:var(--text-dim);">
                    <span><i class="fas fa-user" style="color:var(--primary);"></i> <?= htmlspecialchars(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                    <span><i class="fas fa-calendar" style="color:var(--primary);"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                    <span style="padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; <?= ($video['video_category'] ?? 'drill') === 'game' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(107,70,193,0.15); color:var(--primary);' ?>">
                        <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                        <?= ucfirst($video['video_category'] ?? 'drill') ?>
                    </span>
                    <?php if (!empty($video['coach_first_name'])): ?>
                    <span><i class="fas fa-user-tie" style="color:var(--primary);"></i> <?= htmlspecialchars($video['coach_first_name'] . ' ' . ($video['coach_last_name'] ?? '')) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($is_owner || $is_coach): ?>
                <form id="detailForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="update_video">
                    <input type="hidden" name="video_id" value="<?= $video_id ?>">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-dim); margin-bottom:6px; text-transform:uppercase;">Title</label>
                        <input type="text" name="title" id="detailTitle" value="<?= htmlspecialchars($video['title']) ?>" 
                               class="detail-input" placeholder="Video title" <?= (!$is_owner && !$is_coach) ? 'readonly' : '' ?>>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-dim); margin-bottom:6px; text-transform:uppercase;">Description</label>
                        <textarea name="description" id="detailDescription" rows="3" 
                                  class="detail-input" placeholder="Video description..." <?= (!$is_owner && !$is_coach) ? 'readonly' : '' ?>><?= htmlspecialchars($video['description'] ?? '') ?></textarea>
                    </div>
                </form>
                <?php else: ?>
                    <?php if (!empty($video['description'])): ?>
                    <p style="color:var(--text-dim); font-size:14px; line-height:1.6;"><?= htmlspecialchars($video['description']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Athlete Notes / Reply -->
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0 0 16px 0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-user" style="color:var(--primary);"></i> 
                    <?= $is_coach ? 'Athlete Notes' : 'My Notes / Reply to Coach' ?>
                </h3>
                <textarea id="detailAthleteNotes" rows="4" class="detail-input" 
                          placeholder="<?= $is_coach ? 'Athlete notes will appear here...' : 'Add your notes or reply to coach feedback...' ?>" 
                          <?= $is_coach ? 'readonly' : '' ?>><?= htmlspecialchars($video['athlete_notes'] ?? '') ?></textarea>
                
                <?php if ($is_owner || $is_coach): ?>
                <div style="margin-top:16px; display:flex; gap:12px; justify-content:flex-end;">
                    <button type="button" id="saveDetailBtn" class="btn-primary" style="padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Coach Notes -->
        <div>
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; position: sticky; top: 24px;">
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0 0 16px 0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-user-tie" style="color:var(--primary);"></i> Coach Notes
                </h3>
                
                <!-- Coach notes display (readonly for athletes) -->
                <div id="coachNotesDisplay" style="padding:16px; background:rgba(107,70,193,0.08); border:1px solid rgba(107,70,193,0.2); border-radius:8px; color:var(--text-dim); font-size:14px; line-height:1.6; white-space:pre-wrap; min-height:60px; margin-bottom:16px;">
                    <?php if (!empty($video['coach_notes'])): ?>
                        <?= htmlspecialchars($video['coach_notes']) ?>
                    <?php else: ?>
                        <em style="opacity:0.5;">No coach notes yet.</em>
                    <?php endif; ?>
                </div>

                <?php if ($is_coach): ?>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-dim); margin-bottom:6px; text-transform:uppercase;">Add/Update Notes</label>
                    <textarea id="detailCoachNotes" rows="6" class="detail-input" 
                              placeholder="Add your review notes..."><?= htmlspecialchars($video['coach_notes'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.detail-input {
    width: 100%;
    background: var(--bg-main, #0F0F14);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: var(--text-white);
    font-size: 14px;
    padding: 12px 16px;
    font-family: inherit;
    box-sizing: border-box;
}
.detail-input:focus {
    border-color: var(--primary, #6B46C1);
    outline: none;
}
.detail-input[readonly] {
    opacity: 0.7;
    cursor: default;
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 380px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize HLS player if available
    var detailPlayer = document.getElementById('detailVideoPlayer');
    if (detailPlayer && typeof window.awInitHlsPlayer === 'function') {
        var src = detailPlayer.querySelector('source');
        if (src && src.src) {
            window.awInitHlsPlayer(detailPlayer, src.src);
        }
    }

    var saveBtn = document.getElementById('saveDetailBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var videoId = <?= (int)$video_id ?>;
            var csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>';
            var title = document.getElementById('detailTitle');
            var description = document.getElementById('detailDescription');
            var athleteNotes = document.getElementById('detailAthleteNotes');
            var coachNotes = document.getElementById('detailCoachNotes');

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            var formData = new FormData();
            formData.append('action', 'update_video');
            formData.append('video_id', videoId);
            formData.append('csrf_token', csrfToken);
            if (title) formData.append('title', title.value.trim());
            if (description) formData.append('description', description.value.trim());
            if (coachNotes) formData.append('coach_notes', coachNotes.value);
            if (athleteNotes && !athleteNotes.readOnly) formData.append('athlete_notes', athleteNotes.value);

            fetch('process_video.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (typeof showToast === 'function') showToast('Changes saved successfully.', 'success');
                        // Update the coach notes display if coach updated
                        if (coachNotes) {
                            var display = document.getElementById('coachNotesDisplay');
                            if (display) {
                                display.textContent = coachNotes.value || '';
                                if (!coachNotes.value) display.innerHTML = '<em style="opacity:0.5;">No coach notes yet.</em>';
                            }
                        }
                    } else {
                        if (typeof showToast === 'function') showToast('Save failed: ' + (data.error || data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(function(err) {
                    if (typeof showToast === 'function') showToast('Save failed: ' + err.message, 'error');
                })
                .finally(function() {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                });
        });
    }
});
</script>
