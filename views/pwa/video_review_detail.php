<?php
/**
 * PWA Video Review Detail Page
 * Mobile-optimized version of the desktop video_review_detail.php.
 * - Video player at top, full width
 * - Video info card below
 * - Conversation thread below
 * - Quick info panel at bottom (not sidebar)
 * - Touch-friendly form elements
 */
require_once __DIR__ . '/../../lib/image_helper.php';

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
    echo '<div class="m-vrd" style="padding:16px;">';
    echo '<a href="' . htmlspecialchars($back_url) . '" class="m-vrd-back"><i class="fas fa-arrow-left"></i> Back to ' . htmlspecialchars($back_label) . '</a>';
    echo '<div class="m-vrd-empty"><i class="fas fa-exclamation-triangle"></i><p>Invalid video ID.</p></div>';
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
    echo '<div class="m-vrd" style="padding:16px;">';
    echo '<a href="' . htmlspecialchars($back_url) . '" class="m-vrd-back"><i class="fas fa-arrow-left"></i> Back to ' . htmlspecialchars($back_label) . '</a>';
    echo '<div class="m-vrd-empty"><i class="fas fa-lock"></i><p>Video not found or you don\'t have permission to view it.</p></div>';
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
$athlete_name = trim(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? ''));
$coach_name   = trim(($video['coach_first_name'] ?? '') . ' ' . ($video['coach_last_name'] ?? ''));
?>

<style>
.m-vrd { padding: 0 0 100px 0; font-family: Inter, sans-serif; background: #0A0A0F; }
.m-vrd-back {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; color: #A8A8B8; text-decoration: none;
    padding: 12px 16px; min-height: 44px;
}
.m-vrd-player {
    background: #000; overflow: hidden; margin-bottom: 0;
    border-bottom: 1px solid #2D2D3F;
}
.m-vrd-player video { width: 100%; max-height: 280px; display: block; }
.m-vrd-content { padding: 16px; }
.m-vrd-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 12px;
}
.m-vrd-card-title {
    font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 10px 0;
    display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
}
.m-vrd-card-title-text {
    flex: 1; min-width: 0; overflow-wrap: break-word;
}
.m-vrd-status {
    padding: 5px 10px; border-radius: 20px; font-size: 10px; font-weight: 700;
    text-transform: uppercase; white-space: nowrap; flex-shrink: 0;
}
.m-vrd-status-reviewed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-vrd-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-vrd-meta {
    display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: #A8A8B8;
    margin-bottom: 12px; align-items: center;
}
.m-vrd-meta i { font-size: 10px; color: #8B5CF6; }
.m-vrd-cat-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
}
.m-vrd-cat-game { background: rgba(16,185,129,0.15); color: #10B981; }
.m-vrd-cat-drill { background: rgba(107,70,193,0.15); color: #8B5CF6; }

/* Form elements – keep .detail-input class for JS compatibility */
.m-vrd .detail-input {
    width: 100%;
    background: #0F0F14;
    border: 1px solid #2D2D3F;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    padding: 12px 14px;
    font-family: inherit;
    box-sizing: border-box;
    min-height: 44px;
}
.m-vrd .detail-input:focus { border-color: #8B5CF6; outline: none; }
.m-vrd .detail-input[readonly] { opacity: 0.7; cursor: default; }

.m-vrd-label {
    display: block; font-size: 11px; font-weight: 600; color: #6B6B7B;
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px;
}
.m-vrd-field { margin-bottom: 14px; }

/* Conversation */
.m-vrd-section-heading {
    font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 14px 0;
    display: flex; align-items: center; gap: 8px;
}
.m-vrd-section-heading i { color: #8B5CF6; font-size: 14px; }
.m-vrd-msg {
    padding: 12px 14px; border-radius: 10px; margin-bottom: 10px;
}
.m-vrd-msg-coach { background: rgba(107,70,193,0.08); border: 1px solid rgba(107,70,193,0.2); }
.m-vrd-msg-athlete { background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.2); }
.m-vrd-msg-author {
    display: flex; align-items: center; gap: 6px; margin-bottom: 6px;
    font-size: 12px; font-weight: 600;
}
.m-vrd-msg-author-coach { color: #8B5CF6; }
.m-vrd-msg-author-athlete { color: #10B981; }
.m-vrd-msg-text { margin: 0; font-size: 13px; color: #A8A8B8; line-height: 1.6; white-space: pre-wrap; }
.m-vrd-divider { border: none; border-top: 1px solid #2D2D3F; margin: 14px 0; }

/* Button */
.m-vrd-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; min-height: 48px; padding: 14px 24px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; border: none; border-radius: 10px;
    font-size: 14px; font-weight: 600; font-family: inherit;
    cursor: pointer; margin-top: 10px;
}
.m-vrd-btn:active { opacity: 0.85; }
.m-vrd-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Info panel */
.m-vrd-info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; font-size: 13px;
    border-bottom: 1px solid rgba(45,45,63,0.5);
}
.m-vrd-info-row:last-child { border-bottom: none; }
.m-vrd-info-label { color: #6B6B7B; }
.m-vrd-info-value { color: #fff; font-weight: 600; text-align: right; }

/* Empty state */
.m-vrd-empty { text-align: center; padding: 48px 20px; }
.m-vrd-empty i { font-size: 36px; color: #8B5CF6; opacity: 0.25; display: block; margin-bottom: 12px; }
.m-vrd-empty p { font-size: 13px; color: #6B6B7B; margin: 0; }
.m-vrd-no-convo { text-align: center; padding: 20px; color: #6B6B7B; opacity: 0.6; }
.m-vrd-no-convo i { font-size: 24px; display: block; margin-bottom: 6px; }
.m-vrd-no-convo p { margin: 0; font-size: 12px; }
</style>

<div class="m-vrd">
    <!-- Back link -->
    <a href="<?= htmlspecialchars($back_url) ?>" class="m-vrd-back">
        <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($back_label) ?>
    </a>

    <!-- Video Player – full width -->
    <div class="m-vrd-player">
        <video id="detailVideoPlayer" controls playsinline>
            <source src="<?= htmlspecialchars($video_url) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <div class="m-vrd-content">
        <!-- Video Info Card -->
        <div class="m-vrd-card">
            <div class="m-vrd-card-title">
                <span id="videoTitle" class="m-vrd-card-title-text"><?= htmlspecialchars($video['title']) ?></span>
                <span id="statusBadge" class="m-vrd-status <?= $video['status'] === 'reviewed' ? 'm-vrd-status-reviewed' : 'm-vrd-status-pending' ?>">
                    <i class="fas <?= $video['status'] === 'reviewed' ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                    <?= ucfirst(str_replace('_', ' ', $video['status'])) ?>
                </span>
            </div>

            <div class="m-vrd-meta">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($athlete_name) ?></span>
                <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                <?php $cat = $video['video_category'] ?? 'drill'; ?>
                <span class="m-vrd-cat-badge <?= $cat === 'game' ? 'm-vrd-cat-game' : 'm-vrd-cat-drill' ?>">
                    <i class="fas <?= $cat === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                    <?= ucfirst($cat) ?>
                </span>
                <?php if (!empty($coach_name)): ?>
                <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($coach_name) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($is_owner || $is_coach): ?>
            <form id="detailForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="update_video">
                <input type="hidden" name="video_id" value="<?= $video_id ?>">

                <div class="m-vrd-field">
                    <label class="m-vrd-label">Title</label>
                    <input type="text" name="title" id="detailTitle" value="<?= htmlspecialchars($video['title']) ?>"
                           class="detail-input" placeholder="Video title" <?= (!$is_owner && !$is_coach) ? 'readonly' : '' ?>>
                </div>
                <div class="m-vrd-field">
                    <label class="m-vrd-label">Description</label>
                    <textarea name="description" id="detailDescription" rows="3"
                              class="detail-input" placeholder="Video description..." <?= (!$is_owner && !$is_coach) ? 'readonly' : '' ?>><?= htmlspecialchars($video['description'] ?? '') ?></textarea>
                </div>
            </form>
            <?php else: ?>
                <?php if (!empty($video['description'])): ?>
                <p style="color:#A8A8B8; font-size:13px; line-height:1.6; margin:0;"><?= htmlspecialchars($video['description']) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Conversation Thread -->
        <div class="m-vrd-card">
            <h3 class="m-vrd-section-heading">
                <i class="fas fa-comments"></i> Review Conversation
            </h3>

            <div id="conversationThread">
                <?php if (!empty($video['coach_notes'])): ?>
                <div class="m-vrd-msg m-vrd-msg-coach">
                    <div class="m-vrd-msg-author m-vrd-msg-author-coach">
                        <i class="fas fa-user-tie"></i>
                        Coach<?php if ($coach_name): ?> — <?= htmlspecialchars($coach_name) ?><?php endif; ?>
                    </div>
                    <p id="coachNotesText" class="m-vrd-msg-text"><?= htmlspecialchars($video['coach_notes']) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($video['athlete_notes'])): ?>
                <div class="m-vrd-msg m-vrd-msg-athlete">
                    <div class="m-vrd-msg-author m-vrd-msg-author-athlete">
                        <i class="fas fa-user"></i>
                        Athlete<?php if ($athlete_name): ?> — <?= htmlspecialchars($athlete_name) ?><?php endif; ?>
                    </div>
                    <p id="athleteNotesText" class="m-vrd-msg-text"><?= htmlspecialchars($video['athlete_notes']) ?></p>
                </div>
                <?php endif; ?>

                <?php if (empty($video['coach_notes']) && empty($video['athlete_notes'])): ?>
                <div id="noConversation" class="m-vrd-no-convo">
                    <i class="fas fa-comment-slash"></i>
                    <p><?= $is_coach ? 'No notes yet. Add your review notes below.' : 'Your coach hasn\'t reviewed this video yet.' ?></p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_coach): ?>
            <hr class="m-vrd-divider">
            <label class="m-vrd-label">
                <?= $video['status'] === 'pending_review' ? 'Review Notes (submitting marks video as reviewed)' : 'Update Coach Notes' ?>
            </label>
            <textarea id="detailCoachNotes" rows="4" class="detail-input"
                      placeholder="Add your review notes..."><?= htmlspecialchars($video['coach_notes'] ?? '') ?></textarea>
            <button type="button" id="saveDetailBtn" class="m-vrd-btn">
                <i class="fas fa-paper-plane"></i> <?= $video['status'] === 'pending_review' ? 'Submit Review' : 'Update Notes' ?>
            </button>
            <?php elseif ($is_owner): ?>
            <hr class="m-vrd-divider">
            <label class="m-vrd-label">Reply to Coach</label>
            <textarea id="detailAthleteNotes" rows="3" class="detail-input"
                      placeholder="Add your notes or reply to coach feedback..."><?= htmlspecialchars($video['athlete_notes'] ?? '') ?></textarea>
            <button type="button" id="saveDetailBtn" class="m-vrd-btn">
                <i class="fas fa-reply"></i> Send Reply
            </button>
            <?php endif; ?>
        </div>

        <!-- Quick Info Panel -->
        <div class="m-vrd-card">
            <h3 class="m-vrd-section-heading">
                <i class="fas fa-info-circle"></i> Video Info
            </h3>
            <div class="m-vrd-info-row">
                <span class="m-vrd-info-label">Athlete</span>
                <span class="m-vrd-info-value"><?= htmlspecialchars($athlete_name) ?></span>
            </div>
            <div class="m-vrd-info-row">
                <span class="m-vrd-info-label">Uploaded</span>
                <span class="m-vrd-info-value"><?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
            </div>
            <div class="m-vrd-info-row">
                <span class="m-vrd-info-label">Type</span>
                <span class="m-vrd-info-value"><?= ucfirst($video['video_category'] ?? 'drill') ?></span>
            </div>
            <div class="m-vrd-info-row">
                <span class="m-vrd-info-label">Status</span>
                <span id="infoStatus" class="m-vrd-info-value" style="<?= $video['status'] === 'reviewed' ? 'color:#10B981;' : 'color:#F59E0B;' ?>">
                    <?= ucfirst(str_replace('_', ' ', $video['status'])) ?>
                </span>
            </div>
            <?php if (!empty($coach_name)): ?>
            <div class="m-vrd-info-row">
                <span class="m-vrd-info-label">Coach</span>
                <span class="m-vrd-info-value"><?= htmlspecialchars($coach_name) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($video['reviewed_at'])): ?>
            <div class="m-vrd-info-row">
                <span class="m-vrd-info-label">Reviewed</span>
                <span class="m-vrd-info-value"><?= date('M d, Y', strtotime($video['reviewed_at'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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
                formData.append('action', 'review_video');
                formData.append('coach_notes', coachNotes.value);
            } else {
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
