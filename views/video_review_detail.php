<?php
/**
 * Video Review Detail Page
 * Full page view for reviewing a video.
 * - Shows video player, metadata, and a conversation thread (coach notes + athlete replies).
 * - Coach submitting notes marks the video as "reviewed" (moves to Reviewed tab).
 * - Athlete replying on a reviewed video moves it back to "pending_review".
 */
require_once __DIR__ . '/../lib/image_helper.php';

$video_id = filter_input(INPUT_GET, 'video_id', FILTER_VALIDATE_INT);

// Build the back link – honour the "from" query param so we return to the right page/tab
$back_page = $_GET['from'] ?? 'coach_video_reviews';
$back_tab  = $_GET['tab'] ?? '';
$back_athlete = $_GET['athlete_id'] ?? '';
$back_url  = '?page=' . urlencode($back_page);
if ($back_tab) $back_url .= '&tab=' . urlencode($back_tab);
if ($back_athlete) $back_url .= '&athlete_id=' . urlencode($back_athlete);
$back_label = ($back_page === 'coaches_reviews') ? 'Coach Review' : 'Video Reviews';

if (!$video_id) {
    echo '<div class="page-header"><h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> Video Not Found</h1></div>';
    echo '<div style="max-width:800px; margin:24px auto; padding:40px; text-align:center; color:var(--text-dim);">';
    echo '<p>Invalid video ID. <a href="' . htmlspecialchars($back_url) . '" style="color:var(--primary);">Return to ' . htmlspecialchars($back_label) . '</a></p>';
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
    echo '<p>Video not found or you don\'t have permission to view it. <a href="' . htmlspecialchars($back_url) . '" style="color:var(--primary);">Return to ' . htmlspecialchars($back_label) . '</a></p>';
    echo '</div>';
    return;
}

// Decrypt names
foreach (['athlete_first_name', 'athlete_last_name', 'coach_first_name', 'coach_last_name'] as $f) {
    if (!empty($video[$f])) $video[$f] = FieldEncryption::decrypt($video[$f]);
}

$video_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($video)) ?? '';
$thumbnail_url = resolveRustfsUrl($pdo, $video['thumbnail_url'] ?? '') ?? '';
$video_type = preg_match('/\.m3u8(\?|&|$)/i', $video_url) ? 'application/vnd.apple.mpegurl' : 'video/mp4';

// Compute a fallback URL for JS error recovery (bidirectional).
// When the primary URL is the HLS manifest (transcode initiated), the
// fallback is the original file (in case the manifest isn't uploaded yet).
// When the primary is the original file, the fallback is the HLS manifest.
$hls_fallback_url = '';
if (preg_match('/\.m3u8(\?|&|$)/i', $video_url)) {
    // Primary is HLS → fallback to original video file
    $orig = resolveRustfsUrl($pdo, $video['video_url'] ?? $video['file_path'] ?? '') ?? '';
    if ($orig && $orig !== $video_url) $hls_fallback_url = $orig;
} else {
    // Primary is original → fallback to HLS manifest
    if (!empty($video['hls_url'])) {
        $hls = resolveRustfsUrl($pdo, $video['hls_url']) ?? '';
        if ($hls && $hls !== $video_url) $hls_fallback_url = $hls;
    }
    if (empty($hls_fallback_url)) $hls_fallback_url = deriveHlsFallbackUrl($video_url);
}

$csrf_token = $_SESSION['csrf_token'] ?? '';
$is_coach = $isAnyCoach;
$is_owner = (int)$video['athlete_id'] === (int)$user_id;
$athlete_name = trim(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? ''));
$coach_name   = trim(($video['coach_first_name'] ?? '') . ' ' . ($video['coach_last_name'] ?? ''));
?>

<div class="page-header" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
    <a href="<?= htmlspecialchars($back_url) ?>" style="color:var(--text-dim); text-decoration:none; font-size:14px; display:flex; align-items:center; gap:6px;">
        <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($back_label) ?>
    </a>
</div>

<div style="max-width: 1000px; margin: 0 auto; padding: 0 16px;">
    <!-- Video Player -->
    <div style="position: relative; background: #000; border-radius: 12px; overflow: hidden; margin-bottom: 24px; border: 1px solid var(--border); aspect-ratio: 16 / 9;">
        <video id="detailVideoPlayer" controls<?= $thumbnail_url ? ' poster="' . htmlspecialchars($thumbnail_url) . '"' : '' ?><?= $hls_fallback_url ? ' data-hls-url="' . htmlspecialchars($hls_fallback_url) . '"' : '' ?> style="width: 100%; height: 100%; display: block; object-fit: contain;">
            <source src="<?= htmlspecialchars($video_url) ?>" type="<?= $video_type ?>">
            Your browser does not support the video tag.
        </video>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 380px; gap: 24px;">
        <!-- Left: Video Info & Conversation -->
        <div>
            <!-- Title & Description -->
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                    <h2 id="videoTitle" style="margin:0; font-size:20px; font-weight:700; color:var(--text-white);"><?= htmlspecialchars($video['title']) ?></h2>
                    <span id="statusBadge" style="padding:6px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; <?= $video['status'] === 'reviewed' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(245,158,11,0.15); color:#F59E0B;' ?>">
                        <i class="fas <?= $video['status'] === 'reviewed' ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                        <?= ucfirst(str_replace('_', ' ', $video['status'])) ?>
                    </span>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px; font-size:13px; color:var(--text-dim);">
                    <span><i class="fas fa-user" style="color:var(--primary);"></i> <?= htmlspecialchars($athlete_name) ?></span>
                    <span><i class="fas fa-calendar" style="color:var(--primary);"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                    <span style="padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; <?= ($video['video_category'] ?? 'drill') === 'game' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(107,70,193,0.15); color:var(--primary);' ?>">
                        <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                        <?= ucfirst($video['video_category'] ?? 'drill') ?>
                    </span>
                    <?php if (!empty($coach_name)): ?>
                    <span><i class="fas fa-user-tie" style="color:var(--primary);"></i> <?= htmlspecialchars($coach_name) ?></span>
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
                    <div style="display:flex; justify-content:flex-end;">
                        <button type="button" id="saveMetaBtn" class="btn-primary" style="padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600; display:none;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <?php if (!empty($video['description'])): ?>
                    <p style="color:var(--text-dim); font-size:14px; line-height:1.6;"><?= htmlspecialchars($video['description']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Conversation Thread (Coach Notes + Athlete Replies) -->
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0 0 16px 0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-comments" style="color:var(--primary);"></i> Review Conversation
                </h3>

                <!-- Existing conversation entries -->
                <div id="conversationThread" style="display:flex; flex-direction:column; gap:12px; margin-bottom:16px;">
                    <?php if (!empty($video['coach_notes'])): ?>
                    <div style="padding:14px 16px; background:rgba(107,70,193,0.08); border:1px solid rgba(107,70,193,0.2); border-radius:10px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <i class="fas fa-user-tie" style="color:var(--primary); font-size:13px;"></i>
                            <span style="font-size:13px; font-weight:600; color:var(--primary);">Coach<?php if ($coach_name): ?> — <?= htmlspecialchars($coach_name) ?><?php endif; ?></span>
                        </div>
                        <p id="coachNotesText" style="margin:0; font-size:14px; color:var(--text-dim); line-height:1.6; white-space:pre-wrap;"><?= htmlspecialchars($video['coach_notes']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($video['athlete_notes'])): ?>
                    <div style="padding:14px 16px; background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.2); border-radius:10px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <i class="fas fa-user" style="color:#10B981; font-size:13px;"></i>
                            <span style="font-size:13px; font-weight:600; color:#10B981;">Athlete<?php if ($athlete_name): ?> — <?= htmlspecialchars($athlete_name) ?><?php endif; ?></span>
                        </div>
                        <p id="athleteNotesText" style="margin:0; font-size:14px; color:var(--text-dim); line-height:1.6; white-space:pre-wrap;"><?= htmlspecialchars($video['athlete_notes']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($video['coach_notes']) && empty($video['athlete_notes'])): ?>
                    <div id="noConversation" style="text-align:center; padding:24px; color:var(--text-dim); opacity:0.6;">
                        <i class="fas fa-comment-slash" style="font-size:28px; display:block; margin-bottom:8px;"></i>
                        <p style="margin:0; font-size:13px;">No notes yet. <?= $is_coach ? 'Add your review notes below.' : 'Your coach hasn\'t reviewed this video yet.' ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Reply / Add Notes area -->
                <?php if ($is_coach): ?>
                <div style="border-top:1px solid var(--border); padding-top:16px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-dim); margin-bottom:6px; text-transform:uppercase;">
                        <?= $video['status'] === 'pending_review' ? 'Review Notes (submitting marks video as reviewed)' : 'Update Coach Notes' ?>
                    </label>
                    <textarea id="detailCoachNotes" rows="5" class="detail-input" 
                              placeholder="Add your review notes..."><?= htmlspecialchars($video['coach_notes'] ?? '') ?></textarea>
                    <div style="margin-top:12px; display:flex; gap:12px; justify-content:flex-end;">
                        <button type="button" id="saveDetailBtn" class="btn-primary" style="padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600;">
                            <i class="fas fa-paper-plane"></i> <?= $video['status'] === 'pending_review' ? 'Submit Review' : 'Update Notes' ?>
                        </button>
                    </div>
                </div>
                <?php elseif ($is_owner): ?>
                <div style="border-top:1px solid var(--border); padding-top:16px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-dim); margin-bottom:6px; text-transform:uppercase;">
                        Reply to Coach
                    </label>
                    <textarea id="detailAthleteNotes" rows="4" class="detail-input" 
                              placeholder="Add your notes or reply to coach feedback..."><?= htmlspecialchars($video['athlete_notes'] ?? '') ?></textarea>
                    <div style="margin-top:12px; display:flex; gap:12px; justify-content:flex-end;">
                        <button type="button" id="saveDetailBtn" class="btn-primary" style="padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600;">
                            <i class="fas fa-reply"></i> Send Reply
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Quick Info Panel -->
        <div>
            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; position: sticky; top: 24px;">
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0 0 16px 0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i> Video Info
                </h3>
                <div style="display:flex; flex-direction:column; gap:12px; font-size:14px; color:var(--text-dim);">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Athlete</span>
                        <span style="color:var(--text-white); font-weight:600;"><?= htmlspecialchars($athlete_name) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Uploaded</span>
                        <span style="color:var(--text-white); font-weight:600;"><?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Type</span>
                        <span style="color:var(--text-white); font-weight:600;"><?= ucfirst($video['video_category'] ?? 'drill') ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Status</span>
                        <span id="infoStatus" style="font-weight:600; <?= $video['status'] === 'reviewed' ? 'color:#10B981;' : 'color:#F59E0B;' ?>">
                            <?= ucfirst(str_replace('_', ' ', $video['status'])) ?>
                        </span>
                    </div>
                    <?php if (!empty($coach_name)): ?>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Coach</span>
                        <span style="color:var(--text-white); font-weight:600;"><?= htmlspecialchars($coach_name) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($video['reviewed_at'])): ?>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Reviewed</span>
                        <span style="color:var(--text-white); font-weight:600;"><?= date('M d, Y', strtotime($video['reviewed_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
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

    // Fallback: if the primary video source fails (e.g. 502 because the
    // companion deleted the original MP4 after HLS transcode but the
    // callback didn't update hls_status), retry with the HLS URL.
    if (detailPlayer) {
        var _hlsFallbackTried = false;
        detailPlayer.addEventListener('error', function() {
            var hlsUrl = detailPlayer.dataset.hlsUrl;
            if (hlsUrl && !_hlsFallbackTried) {
                _hlsFallbackTried = true;
                if (typeof window.awInitHlsPlayer === 'function') {
                    window.awInitHlsPlayer(detailPlayer, hlsUrl);
                }
            }
        }, true);
    }

    // Show the "Save Changes" button when title or description is modified
    var saveMetaBtn = document.getElementById('saveMetaBtn');
    var titleInput = document.getElementById('detailTitle');
    var descInput = document.getElementById('detailDescription');
    if (saveMetaBtn && titleInput && descInput) {
        var origTitle = titleInput.value;
        var origDesc = descInput.value;
        function checkMetaChanged() {
            if (titleInput.value !== origTitle || descInput.value !== origDesc) {
                saveMetaBtn.style.display = '';
            } else {
                saveMetaBtn.style.display = 'none';
            }
        }
        titleInput.addEventListener('input', checkMetaChanged);
        descInput.addEventListener('input', checkMetaChanged);

        saveMetaBtn.addEventListener('click', function() {
            var videoId = <?= (int)$video_id ?>;
            var csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>';

            saveMetaBtn.disabled = true;
            saveMetaBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            var formData = new FormData();
            formData.append('video_id', videoId);
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'update_video');
            formData.append('title', titleInput.value.trim());
            formData.append('description', descInput.value.trim());

            fetch('process_video.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (typeof showToast === 'function') showToast(data.message || 'Saved successfully.', 'success');
                        origTitle = titleInput.value;
                        origDesc = descInput.value;
                        saveMetaBtn.style.display = 'none';
                        // Update the displayed title heading
                        var h2 = document.getElementById('videoTitle');
                        if (h2) h2.textContent = titleInput.value.trim();
                    } else {
                        if (typeof showToast === 'function') showToast('Save failed: ' + (data.error || data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(function(err) {
                    if (typeof showToast === 'function') showToast('Save failed: ' + err.message, 'error');
                })
                .finally(function() {
                    saveMetaBtn.disabled = false;
                    saveMetaBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                });
        });
    }

    var saveBtn = document.getElementById('saveDetailBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var videoId = <?= (int)$video_id ?>;
            var csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>';
            var isCoach = <?= $is_coach ? 'true' : 'false' ?>;
            var title = document.getElementById('detailTitle');
            var description = document.getElementById('detailDescription');
            var athleteNotes = document.getElementById('detailAthleteNotes');
            var coachNotes = document.getElementById('detailCoachNotes');

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            var formData = new FormData();
            formData.append('video_id', videoId);
            formData.append('csrf_token', csrfToken);

            if (isCoach && coachNotes) {
                // Coach submitting notes – use review_video action to mark as reviewed
                formData.append('action', 'review_video');
                formData.append('coach_notes', coachNotes.value);
            } else {
                // Athlete saving reply
                formData.append('action', 'update_video');
                if (athleteNotes) formData.append('athlete_notes', athleteNotes.value);
            }

            if (title) formData.append('title', title.value.trim());
            if (description) formData.append('description', description.value.trim());

            fetch('process_video.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (typeof showToast === 'function') showToast(data.message || 'Saved successfully.', 'success');
                        // Reload to show updated status and conversation
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        if (typeof showToast === 'function') showToast('Save failed: ' + (data.error || data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(function(err) {
                    if (typeof showToast === 'function') showToast('Save failed: ' + err.message, 'error');
                })
                .finally(function() {
                    saveBtn.disabled = false;
                    if (isCoach) {
                        var isReviewed = <?= $video['status'] === 'reviewed' ? 'true' : 'false' ?>;
                        saveBtn.innerHTML = isReviewed
                            ? '<i class="fas fa-paper-plane"></i> Update Notes'
                            : '<i class="fas fa-paper-plane"></i> Submit Review';
                    } else {
                        saveBtn.innerHTML = '<i class="fas fa-reply"></i> Send Reply';
                    }
                });
        });
    }
});
</script>
