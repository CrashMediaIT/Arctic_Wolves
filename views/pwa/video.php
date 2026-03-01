<?php
/**
 * PWA Video - Mobile-native video review interface
 * Purpose-built for mobile phones.
 */

// Drill Review videos
$drillVideos = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.filename, v.status, v.created_at, v.athlete_id,
               u.first_name, u.last_name
        FROM videos v
        LEFT JOIN users u ON u.id = v.athlete_id
        WHERE v.athlete_id = ? OR v.assigned_coach_id = ?
        ORDER BY v.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id]);
    $drillVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $drillVideos = decryptUserRows($drillVideos);
} catch (PDOException $e) { $drillVideos = []; }

// Coach Review videos
$coachReviewVideos = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.filename, v.status, v.review_status, v.created_at,
               u.first_name, u.last_name
        FROM videos v
        LEFT JOIN users u ON u.id = v.athlete_id
        WHERE v.assigned_coach_id = ? AND v.review_status = 'pending'
        ORDER BY v.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $coachReviewVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $coachReviewVideos = decryptUserRows($coachReviewVideos);
} catch (PDOException $e) { $coachReviewVideos = []; }
?>
<style>
.m-video { padding: 0; font-family: Inter, sans-serif; }
.m-view-selector {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #1E1E2E;
    border: 1px solid #2D2D3F;
    border-radius: 12px;
    padding: 8px 12px;
    margin-bottom: 16px;
}
.m-view-label {
    font-size: 12px;
    font-weight: 600;
    color: #8B5CF6;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}
.m-view-select {
    flex: 1;
    background: #16161F;
    border: 1px solid #2D2D3F;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    padding: 10px 12px;
    min-height: 44px;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238B5CF6' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
}
.m-view-select:focus {
    outline: none;
    border-color: #6B46C1;
    box-shadow: 0 0 0 3px rgba(107,70,193,0.2);
}
.m-view-select option {
    background: #16161F;
    color: #fff;
    padding: 8px;
}
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-video-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 12px;
    text-decoration: none; min-height: 44px;
}
.m-video-thumb {
    width: 48px; height: 48px; border-radius: 10px;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #8B5CF6; flex-shrink: 0;
}
.m-video-body { flex: 1; min-width: 0; }
.m-video-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-video-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; }
.m-video-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-video-badge-uploaded { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-video-badge-reviewed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-video-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-video-badge-processing { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-record-wrap { text-align: center; padding: 40px 20px; }
.m-record-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 32px; color: #fff; margin-bottom: 16px;
}
.m-record-text { font-size: 15px; color: #fff; font-weight: 600; margin: 0 0 8px; }
.m-record-sub { font-size: 13px; color: #A8A8B8; margin: 0 0 20px; }
.m-record-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 14px 28px; border-radius: 12px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    text-decoration: none; min-height: 44px;
    font-family: Inter, sans-serif; border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-upload-bar {
    padding: 12px 16px; background: #16161F; border-bottom: 1px solid #2D2D3F;
    display: flex; justify-content: flex-end;
}
.m-upload-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 13px; font-weight: 600;
    text-decoration: none; min-height: 44px; border: none; cursor: pointer;
    font-family: Inter, sans-serif;
}
.m-video-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.m-btn-icon {
    width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 14px; min-height: 44px; min-width: 44px;
    font-family: Inter, sans-serif;
}
.m-btn-delete { background: rgba(239,68,68,0.12); color: #EF4444; }
.m-btn-review { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-btn-reviewed { background: rgba(16,185,129,0.15); color: #10B981; }
/* Slide-up review modal */
.m-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.6); align-items: flex-end; justify-content: center;
}
.m-modal-overlay.m-modal-open { display: flex; }
.m-modal-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 480px;
    padding: 20px 16px calc(20px + env(safe-area-inset-bottom)); border: 1px solid #2D2D3F;
    border-bottom: none; animation: mSlideUp 0.25s ease-out;
}
@keyframes mSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-modal-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
}
.m-modal-title { font-size: 16px; font-weight: 700; color: #fff; }
.m-modal-close {
    width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer;
    background: rgba(168,168,184,0.1); color: #A8A8B8; font-size: 16px;
    display: flex; align-items: center; justify-content: center; min-height: 44px;
}
.m-modal-athlete { font-size: 13px; color: #A8A8B8; margin-bottom: 12px; }
.m-modal-textarea {
    width: 100%; min-height: 120px; border-radius: 10px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #fff; padding: 12px; font-size: 14px; resize: vertical;
    font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-modal-textarea::placeholder { color: #6B6B7B; }
.m-modal-submit {
    width: 100%; padding: 14px; border-radius: 10px; border: none; cursor: pointer;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    margin-top: 12px; min-height: 44px; font-family: Inter, sans-serif;
}
.m-modal-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.m-toast {
    position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    z-index: 200; opacity: 0; transition: opacity 0.3s;
    font-family: Inter, sans-serif;
}
.m-toast.m-toast-show { opacity: 1; }
.m-toast-success { background: rgba(16,185,129,0.9); color: #fff; }
.m-toast-error { background: rgba(239,68,68,0.9); color: #fff; }
</style>

<div class="m-video">
    <div class="m-upload-bar">
        <a href="?page=record_video" class="m-upload-btn"><i class="fas fa-cloud-upload-alt"></i> Upload Video</a>
    </div>
    <div class="m-view-selector" style="margin:16px 16px 0;">
        <label class="m-view-label" for="videoViewSelect">
            <i class="fas fa-layer-group"></i> View
        </label>
        <select class="m-view-select" id="videoViewSelect" aria-label="Select video view">
            <option value="drills" selected>📋 Drill Review</option>
            <option value="coach">📊 Coach Review</option>
            <option value="record">🎬 Record</option>
        </select>
    </div>

    <!-- Drill Review Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-drills">
        <?php if (empty($drillVideos)): ?>
            <div class="m-empty-state">
                <i class="fas fa-video-slash"></i>
                <p>No drill videos yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($drillVideos as $v):
                $status = $v['status'] ?? 'uploaded';
                $badgeClass = match($status) {
                    'reviewed' => 'reviewed',
                    'pending' => 'pending',
                    'processing' => 'processing',
                    default => 'uploaded',
                };
                $athleteName = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                $canDelete = $isAdmin || ((int)($v['athlete_id'] ?? 0) === (int)$user_id) || $isAnyCoach;
            ?>
            <div class="m-video-card">
                <a href="?page=video&id=<?= (int)$v['id'] ?>" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">
                    <div class="m-video-thumb"><i class="fas fa-play"></i></div>
                    <div class="m-video-body">
                        <div class="m-video-title"><?= htmlspecialchars($v['title'] ?? 'Untitled Video') ?></div>
                        <div class="m-video-meta">
                            <?php if ($athleteName): ?><?= htmlspecialchars($athleteName) ?> · <?php endif; ?>
                            <?= date('M j', strtotime($v['created_at'])) ?>
                        </div>
                    </div>
                </a>
                <div class="m-video-actions">
                    <span class="m-video-badge m-video-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                    <?php if ($canDelete): ?>
                    <button type="button" class="m-btn-icon m-btn-delete" onclick="mVidDelete(<?= (int)$v['id'] ?>)" title="Delete video" aria-label="Delete video"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Coach Review Tab -->
    <div class="m-tab-panel" id="m-panel-coach">
        <?php if (empty($coachReviewVideos)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No videos pending review</p>
            </div>
        <?php else: ?>
            <?php foreach ($coachReviewVideos as $v):
                $athleteName = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
            ?>
            <div class="m-video-card">
                <a href="?page=video&id=<?= (int)$v['id'] ?>" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">
                    <div class="m-video-thumb"><i class="fas fa-play"></i></div>
                    <div class="m-video-body">
                        <div class="m-video-title"><?= htmlspecialchars($v['title'] ?? 'Untitled Video') ?></div>
                        <div class="m-video-meta">
                            <?php if ($athleteName): ?><?= htmlspecialchars($athleteName) ?> · <?php endif; ?>
                            <?= date('M j', strtotime($v['created_at'])) ?>
                        </div>
                    </div>
                </a>
                <div class="m-video-actions">
                    <span class="m-video-badge m-video-badge-pending">Pending</span>
                    <button type="button" class="m-btn-icon m-btn-review" onclick="mVidOpenReview(<?= (int)$v['id'] ?>, <?= htmlspecialchars(json_encode($v['title'] ?? 'Untitled'), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($athleteName), ENT_QUOTES) ?>)" title="Review video" aria-label="Review video"><i class="fas fa-clipboard-check"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Record Tab -->
    <div class="m-tab-panel" id="m-panel-record">
        <div class="m-record-wrap">
            <div class="m-record-icon"><i class="fas fa-video"></i></div>
            <p class="m-record-text">Record a New Video</p>
            <p class="m-record-sub">Capture your drills or practice for coach review</p>
            <a href="?page=record_video" class="m-record-btn">
                <i class="fas fa-circle" style="color:#EF4444;"></i> Start Recording
            </a>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="m-modal-overlay" id="mReviewModal">
    <div class="m-modal-sheet">
        <div class="m-modal-header">
            <span class="m-modal-title">Review Video</span>
            <button type="button" class="m-modal-close" onclick="mVidCloseReview()" aria-label="Close">&times;</button>
        </div>
        <div class="m-modal-athlete" id="mReviewInfo"></div>
        <textarea class="m-modal-textarea" id="mReviewNotes" placeholder="Enter your coaching notes…" rows="5"></textarea>
        <button type="button" class="m-modal-submit" id="mReviewSubmit" onclick="mVidSubmitReview()">Submit Review</button>
        <input type="hidden" id="mReviewVideoId" value="">
    </div>
</div>
<div class="m-toast" id="mToast"></div>

<script>
var mCsrfToken = <?= json_encode(CSRFProtection::generateToken()) ?>;

document.getElementById('videoViewSelect').addEventListener('change', function() {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    var target = document.getElementById('m-panel-' + this.value);
    if (target) target.classList.add('m-tab-visible');
});

function mVidShowToast(msg, type) {
    var t = document.getElementById('mToast');
    t.textContent = msg;
    t.className = 'm-toast m-toast-show ' + (type === 'error' ? 'm-toast-error' : 'm-toast-success');
    setTimeout(function() { t.classList.remove('m-toast-show'); }, 3000);
}

async function mVidDelete(videoId) {
    if (!await showConfirmModal('Delete this video? This cannot be undone.')) return;
    var body = new FormData();
    body.append('action', 'delete_video');
    body.append('video_id', videoId);
    body.append('csrf_token', mCsrfToken);
    fetch('process_video.php', { method: 'POST', body: body })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                persistToast('Video deleted', 'success');
                location.reload();
            } else {
                mVidShowToast(d.error || 'Delete failed', 'error');
            }
        })
        .catch(function() { mVidShowToast('Network error', 'error'); });
}

function mVidOpenReview(videoId, title, athlete) {
    document.getElementById('mReviewVideoId').value = videoId;
    document.getElementById('mReviewInfo').textContent = (athlete ? athlete + ' — ' : '') + title;
    document.getElementById('mReviewNotes').value = '';
    document.getElementById('mReviewSubmit').disabled = false;
    document.getElementById('mReviewModal').classList.add('m-modal-open');
}

function mVidCloseReview() {
    document.getElementById('mReviewModal').classList.remove('m-modal-open');
}

function mVidSubmitReview() {
    var videoId = document.getElementById('mReviewVideoId').value;
    var notes = document.getElementById('mReviewNotes').value.trim();
    if (!notes) { mVidShowToast('Please enter review notes', 'error'); return; }
    var btn = document.getElementById('mReviewSubmit');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
    var body = new FormData();
    body.append('action', 'review_video');
    body.append('video_id', videoId);
    body.append('coach_notes', notes);
    body.append('csrf_token', mCsrfToken);
    fetch('process_video.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                mVidCloseReview();
                persistToast('Review submitted', 'success');
                location.reload();
            } else {
                mVidShowToast(d.error || 'Review failed', 'error');
                btn.disabled = false;
                btn.textContent = 'Submit Review';
            }
        })
        .catch(function() {
            mVidShowToast('Network error', 'error');
            btn.disabled = false;
            btn.textContent = 'Submit Review';
        });
}

// Close modal on overlay click
document.getElementById('mReviewModal').addEventListener('click', function(e) {
    if (e.target === this) mVidCloseReview();
});
</script>
