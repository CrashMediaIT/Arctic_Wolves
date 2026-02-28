<?php
/**
 * PWA Coach Video Reviews
 * Mobile-optimized two-tab interface for coaches:
 *   Tab 1 – Pending Reviews: card list of all pending videos; tap opens detail.
 *   Tab 2 – Reviewed: videos grouped by athlete; tap opens detail.
 * When a coach submits notes the video moves to Reviewed.
 * When an athlete replies on a reviewed video it moves back to Pending.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

$active_tab = $_GET['tab'] ?? 'pending';
if (!in_array($active_tab, ['pending', 'reviewed'])) $active_tab = 'pending';

// ── Athletes assigned to this coach ──────────────────────────────
$athletes = [];
$athletes_query = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
    FROM users u
    WHERE u.assigned_coach_id = ? AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
";
$athletes_stmt = $pdo->prepare($athletes_query);
$athletes_stmt->execute([$user_id]);
$athletes = $athletes_stmt->fetchAll();
$athletes = decryptUserRows($athletes);

if (empty($athletes)) {
    $athletes_query = "
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        WHERE u.is_active = 1 AND u.role = 'athlete'
        ORDER BY u.last_name, u.first_name
    ";
    $athletes_stmt = $pdo->query($athletes_query);
    $athletes = $athletes_stmt->fetchAll();
    $athletes = decryptUserRows($athletes);
}

// ── Pending videos ───────────────────────────────────────────────
$pending_query = "
    SELECT v.*,
           a.first_name as athlete_first_name, a.last_name as athlete_last_name
    FROM videos v
    LEFT JOIN users a ON v.athlete_id = a.id
    WHERE v.video_type = 'uploaded_by_athlete'
      AND v.status = 'pending_review'
      AND (v.coach_id = ? OR a.assigned_coach_id = ?)
    ORDER BY v.upload_date DESC
    LIMIT 200
";
$pending_stmt = $pdo->prepare($pending_query);
$pending_stmt->execute([$user_id, $user_id]);
$pending_videos = $pending_stmt->fetchAll();
foreach ($pending_videos as &$v) {
    foreach (['athlete_first_name', 'athlete_last_name'] as $f) {
        if (!empty($v[$f])) $v[$f] = FieldEncryption::decrypt($v[$f]);
    }
}
unset($v);

// ── Reviewed videos ──────────────────────────────────────────────
$reviewed_query = "
    SELECT v.*,
           a.first_name as athlete_first_name, a.last_name as athlete_last_name
    FROM videos v
    LEFT JOIN users a ON v.athlete_id = a.id
    WHERE v.video_type = 'uploaded_by_athlete'
      AND v.status = 'reviewed'
      AND (v.coach_id = ? OR a.assigned_coach_id = ?)
    ORDER BY v.reviewed_at DESC, v.upload_date DESC
    LIMIT 200
";
$reviewed_stmt = $pdo->prepare($reviewed_query);
$reviewed_stmt->execute([$user_id, $user_id]);
$reviewed_videos = $reviewed_stmt->fetchAll();
foreach ($reviewed_videos as &$v) {
    foreach (['athlete_first_name', 'athlete_last_name'] as $f) {
        if (!empty($v[$f])) $v[$f] = FieldEncryption::decrypt($v[$f]);
    }
}
unset($v);

// Group reviewed videos by athlete
$reviewed_by_athlete = [];
foreach ($reviewed_videos as $v) {
    $aid = (int)$v['athlete_id'];
    if (!isset($reviewed_by_athlete[$aid])) {
        $reviewed_by_athlete[$aid] = [
            'name' => trim(($v['athlete_first_name'] ?? '') . ' ' . ($v['athlete_last_name'] ?? '')),
            'videos' => [],
        ];
    }
    $reviewed_by_athlete[$aid]['videos'][] = $v;
}

$selected_athlete = $_GET['athlete_id'] ?? null;
?>
<style>
.m-cvr { padding: 0 0 100px 0; font-family: Inter, sans-serif; background: #0A0A0F; }
.m-cvr-header { padding: 16px; }
.m-cvr-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 2px 0; display: flex; align-items: center; gap: 8px; }
.m-cvr-subtitle { font-size: 12px; color: #6B6B7B; margin: 0; }
.m-cvr-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-cvr-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 13px; font-weight: 600;
    color: #6B6B7B; text-decoration: none;
    border-bottom: 2px solid transparent;
    min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.m-cvr-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-cvr-badge {
    font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px;
}
.m-cvr-badge-pending { background: rgba(245,158,11,0.2); color: #F59E0B; }
.m-cvr-badge-reviewed { background: rgba(16,185,129,0.2); color: #10B981; }
.m-cvr-content { padding: 16px; }
.m-cvr-section-title {
    font-size: 13px; font-weight: 700; color: #fff; margin: 0 0 12px 0;
    display: flex; align-items: center; gap: 8px;
}
.m-cvr-section-bar {
    width: 3px; height: 16px; border-radius: 2px; display: inline-block; flex-shrink: 0;
}
.m-cvr-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 10px; text-decoration: none; display: block;
    overflow: hidden;
}
.m-cvr-card-inner {
    display: flex; align-items: center; gap: 12px; padding: 12px;
    min-height: 44px;
}
.m-cvr-thumb {
    width: 56px; height: 56px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden; position: relative;
}
.m-cvr-thumb img { width: 100%; height: 100%; object-fit: cover; }
.m-cvr-thumb-placeholder {
    background: rgba(107,70,193,0.15); width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
}
.m-cvr-card-body { flex: 1; min-width: 0; }
.m-cvr-card-title {
    font-size: 14px; font-weight: 600; color: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin: 0 0 4px 0;
}
.m-cvr-card-meta { font-size: 12px; color: #A8A8B8; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.m-cvr-card-meta i { font-size: 10px; color: #8B5CF6; }
.m-cvr-card-desc {
    font-size: 11px; color: #6B6B7B; margin-top: 4px;
    overflow: hidden; text-overflow: ellipsis;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.m-cvr-card-status {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-cvr-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-cvr-status-reviewed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cvr-cat-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 6px; font-weight: 600;
}
.m-cvr-cat-game { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cvr-cat-drill { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-cvr-card-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
.m-cvr-chevron { color: #6B6B7B; font-size: 12px; }

/* Athlete group card */
.m-cvr-athlete-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 10px; text-decoration: none; display: flex;
    align-items: center; gap: 12px; padding: 14px;
    min-height: 44px;
}
.m-cvr-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; color: #fff; font-size: 16px;
}
.m-cvr-athlete-name { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 2px 0; }
.m-cvr-athlete-count { font-size: 12px; color: #A8A8B8; margin: 0; }

/* Back link */
.m-cvr-back {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; color: #A8A8B8; text-decoration: none;
    padding: 8px 0; min-height: 44px;
}

/* Empty state */
.m-cvr-empty { text-align: center; padding: 48px 20px; }
.m-cvr-empty i { font-size: 36px; color: #8B5CF6; opacity: 0.25; display: block; margin-bottom: 12px; }
.m-cvr-empty p { font-size: 13px; color: #6B6B7B; margin: 0; }
</style>

<div class="m-cvr">
    <div class="m-cvr-header">
        <h1 class="m-cvr-title"><i class="fas fa-video"></i> Video Reviews</h1>
        <p class="m-cvr-subtitle">Review athlete videos and provide feedback</p>
    </div>

    <!-- Sticky Tabs -->
    <div class="m-cvr-tabs">
        <a href="?page=coach_video_reviews&tab=pending"
           class="m-cvr-tab <?= $active_tab === 'pending' ? 'm-cvr-tab-active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
            <?php if (count($pending_videos) > 0): ?>
                <span class="m-cvr-badge m-cvr-badge-pending"><?= count($pending_videos) ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=coach_video_reviews&tab=reviewed"
           class="m-cvr-tab <?= $active_tab === 'reviewed' ? 'm-cvr-tab-active' : '' ?>">
            <i class="fas fa-check-circle"></i> Reviewed
            <?php if (count($reviewed_videos) > 0): ?>
                <span class="m-cvr-badge m-cvr-badge-reviewed"><?= count($reviewed_videos) ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div class="m-cvr-content">
    <?php if ($active_tab === 'pending'): ?>
    <!-- ═══════════════ PENDING REVIEWS TAB ═══════════════ -->
    <div class="m-cvr-section-title">
        <span class="m-cvr-section-bar" style="background:linear-gradient(180deg, #F59E0B, #D97706);"></span>
        Pending Reviews (<?= count($pending_videos) ?>)
    </div>

    <?php if (count($pending_videos) > 0): ?>
        <?php foreach ($pending_videos as $video): ?>
        <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coach_video_reviews"
           class="m-cvr-card">
            <div class="m-cvr-card-inner">
                <div class="m-cvr-thumb">
                    <?php if (!empty($video['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail">
                    <?php else: ?>
                        <div class="m-cvr-thumb-placeholder"><i class="fas fa-video" style="font-size:20px; color:#8B5CF6; opacity:0.5;"></i></div>
                    <?php endif; ?>
                </div>
                <div class="m-cvr-card-body">
                    <div class="m-cvr-card-title"><?= htmlspecialchars($video['title']) ?></div>
                    <div class="m-cvr-card-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('M d', strtotime($video['upload_date'])) ?></span>
                        <?php $cat = $video['video_category'] ?? 'drill'; ?>
                        <span class="m-cvr-cat-badge <?= $cat === 'game' ? 'm-cvr-cat-game' : 'm-cvr-cat-drill' ?>">
                            <i class="fas <?= $cat === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                            <?= ucfirst($cat) ?>
                        </span>
                    </div>
                    <?php if (!empty($video['description'])): ?>
                    <div class="m-cvr-card-desc"><?= htmlspecialchars($video['description']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="m-cvr-card-right">
                    <span class="m-cvr-card-status m-cvr-status-pending"><i class="fas fa-clock"></i> Pending</span>
                    <i class="fas fa-chevron-right m-cvr-chevron"></i>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="m-cvr-empty">
            <i class="fas fa-check-circle"></i>
            <p>No pending video reviews! All caught up.</p>
        </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ═══════════════ REVIEWED TAB ═══════════════ -->
    <?php if (!$selected_athlete): ?>
    <!-- Athlete list -->
    <div class="m-cvr-section-title">
        <span class="m-cvr-section-bar" style="background:linear-gradient(180deg, #10B981, #059669);"></span>
        Reviewed — Select an Athlete
    </div>

    <?php if (!empty($reviewed_by_athlete)): ?>
        <?php foreach ($reviewed_by_athlete as $aid => $group): ?>
        <a href="?page=coach_video_reviews&tab=reviewed&athlete_id=<?= $aid ?>"
           class="m-cvr-athlete-card">
            <div class="m-cvr-avatar"><i class="fas fa-user"></i></div>
            <div style="flex:1; min-width:0;">
                <div class="m-cvr-athlete-name"><?= htmlspecialchars($group['name']) ?></div>
                <div class="m-cvr-athlete-count"><?= count($group['videos']) ?> reviewed video<?= count($group['videos']) !== 1 ? 's' : '' ?></div>
            </div>
            <i class="fas fa-chevron-right m-cvr-chevron"></i>
        </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="m-cvr-empty">
            <i class="fas fa-folder-open"></i>
            <p>No reviewed videos yet.</p>
        </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Videos for selected athlete -->
    <?php
    $athlete_videos = $reviewed_by_athlete[(int)$selected_athlete]['videos'] ?? [];
    $athlete_name   = $reviewed_by_athlete[(int)$selected_athlete]['name'] ?? 'Unknown Athlete';
    ?>
    <a href="?page=coach_video_reviews&tab=reviewed" class="m-cvr-back">
        <i class="fas fa-arrow-left"></i> Back to Athletes
    </a>

    <div class="m-cvr-section-title">
        <span class="m-cvr-section-bar" style="background:linear-gradient(180deg, #10B981, #059669);"></span>
        <?= htmlspecialchars($athlete_name) ?>
    </div>

    <?php if (!empty($athlete_videos)): ?>
        <?php foreach ($athlete_videos as $video): ?>
        <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coach_video_reviews&tab=reviewed&athlete_id=<?= $selected_athlete ?>"
           class="m-cvr-card">
            <div class="m-cvr-card-inner">
                <div class="m-cvr-thumb">
                    <?php if (!empty($video['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail">
                    <?php else: ?>
                        <div class="m-cvr-thumb-placeholder"><i class="fas fa-video" style="font-size:20px; color:#10B981; opacity:0.5;"></i></div>
                    <?php endif; ?>
                </div>
                <div class="m-cvr-card-body">
                    <div class="m-cvr-card-title"><?= htmlspecialchars($video['title']) ?></div>
                    <div class="m-cvr-card-meta">
                        <span><i class="fas fa-calendar"></i> <?= date('M d', strtotime($video['upload_date'])) ?></span>
                        <?php $cat = $video['video_category'] ?? 'drill'; ?>
                        <span class="m-cvr-cat-badge <?= $cat === 'game' ? 'm-cvr-cat-game' : 'm-cvr-cat-drill' ?>">
                            <i class="fas <?= $cat === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                            <?= ucfirst($cat) ?>
                        </span>
                    </div>
                    <?php if (!empty($video['coach_notes'])): ?>
                    <div class="m-cvr-card-desc"><i class="fas fa-comment" style="margin-right:4px;"></i><?= htmlspecialchars($video['coach_notes']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="m-cvr-card-right">
                    <span class="m-cvr-card-status m-cvr-status-reviewed"><i class="fas fa-check-circle"></i> Reviewed</span>
                    <i class="fas fa-chevron-right m-cvr-chevron"></i>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="m-cvr-empty">
            <i class="fas fa-folder-open" style="color:#10B981;"></i>
            <p>No reviewed videos for this athlete.</p>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php endif; ?>
    </div>
</div>
