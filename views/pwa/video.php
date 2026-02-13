<?php
/**
 * PWA Video - Mobile-native video review interface
 * Purpose-built for mobile phones.
 */

// Drill Review videos
$drillVideos = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.filename, v.status, v.created_at,
               u.first_name, u.last_name
        FROM videos v
        LEFT JOIN users u ON u.id = v.athlete_id
        WHERE v.athlete_id = ? OR v.assigned_coach_id = ?
        ORDER BY v.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id]);
    $drillVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
} catch (PDOException $e) { $coachReviewVideos = []; }
?>
<style>
.m-video { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 13px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
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
</style>

<div class="m-video">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mVidTab('drills', this)" type="button">Drill Review</button>
        <button class="m-tab" onclick="mVidTab('coach', this)" type="button">Coach Review</button>
        <button class="m-tab" onclick="mVidTab('record', this)" type="button">Record</button>
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
            ?>
            <a href="?page=video&id=<?= (int)$v['id'] ?>" class="m-video-card">
                <div class="m-video-thumb"><i class="fas fa-play"></i></div>
                <div class="m-video-body">
                    <div class="m-video-title"><?= htmlspecialchars($v['title'] ?? 'Untitled Video') ?></div>
                    <div class="m-video-meta">
                        <?php if ($athleteName): ?><?= htmlspecialchars($athleteName) ?> · <?php endif; ?>
                        <?= date('M j', strtotime($v['created_at'])) ?>
                    </div>
                </div>
                <span class="m-video-badge m-video-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </a>
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
            <a href="?page=video&id=<?= (int)$v['id'] ?>" class="m-video-card">
                <div class="m-video-thumb"><i class="fas fa-play"></i></div>
                <div class="m-video-body">
                    <div class="m-video-title"><?= htmlspecialchars($v['title'] ?? 'Untitled Video') ?></div>
                    <div class="m-video-meta">
                        <?php if ($athleteName): ?><?= htmlspecialchars($athleteName) ?> · <?php endif; ?>
                        <?= date('M j', strtotime($v['created_at'])) ?>
                    </div>
                </div>
                <span class="m-video-badge m-video-badge-pending">Pending</span>
            </a>
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

<script>
function mVidTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}
</script>
