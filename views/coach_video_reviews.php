<?php
/**
 * Coach Video Reviews
 * Two-tab interface for coaches:
 *   Tab 1 – Pending Reviews: cards of all pending videos; click opens full-page detail.
 *   Tab 2 – Reviewed: videos grouped by athlete; click opens full-page detail.
 * When a coach submits notes the video moves to Reviewed.
 * When an athlete replies on a reviewed video it moves back to Pending.
 */
require_once __DIR__ . '/../lib/image_helper.php';

$active_tab = $_GET['tab'] ?? 'pending';
if (!in_array($active_tab, ['pending', 'reviewed'])) $active_tab = 'pending';

try {
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
} catch (PDOException $e) {
    error_log("Coach Video Reviews error: " . $e->getMessage());
    $athletes = $athletes ?? [];
    $pending_videos = $pending_videos ?? [];
    $reviewed_videos = $reviewed_videos ?? [];
    $reviewed_by_athlete = $reviewed_by_athlete ?? [];
    $selected_athlete = $selected_athlete ?? null;
}
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-video"></i> Video Reviews</h1>
    <p class="page-description">Review athlete videos and provide feedback</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs-wrapper">
    <div class="page-tabs">
        <a href="?page=coach_video_reviews&tab=pending"
           class="page-tab <?= $active_tab === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending Reviews
            <?php if (count($pending_videos) > 0): ?>
                <span style="background:rgba(245,158,11,0.2); color:#F59E0B; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; margin-left:6px;"><?= count($pending_videos) ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=coach_video_reviews&tab=reviewed"
           class="page-tab <?= $active_tab === 'reviewed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Reviewed
            <?php if (count($reviewed_videos) > 0): ?>
                <span style="background:rgba(16,185,129,0.2); color:#10B981; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; margin-left:6px;"><?= count($reviewed_videos) ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="page-tab-content">

<?php if ($active_tab === 'pending'): ?>
<!-- ═══════════════ PENDING REVIEWS TAB ═══════════════ -->
<div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px;">
    <h3 style="font-size:18px; font-weight:700; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; color:var(--text-white);">
        <span style="width:4px; height:24px; background:linear-gradient(180deg, #F59E0B, #D97706); border-radius:2px; display:inline-block;"></span>
        Pending Reviews (<?= count($pending_videos) ?>)
    </h3>

    <?php if (count($pending_videos) > 0): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php foreach ($pending_videos as $video): ?>
        <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coach_video_reviews"
           style="text-decoration:none; display:block; background:linear-gradient(135deg, var(--bg-main) 0%, rgba(22,22,31,0.8) 100%); border:1px solid var(--border); border-radius:12px; overflow:hidden; transition:all 0.3s ease;">
            <div style="width:100%; height:160px; background:linear-gradient(135deg, rgba(107,70,193,0.15), rgba(139,92,246,0.1)); display:flex; align-items:center; justify-content:center; position:relative;">
                <?php if (!empty($video['thumbnail_url'])): ?>
                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-video" style="font-size:36px; color:var(--primary); opacity:0.4;"></i>
                <?php endif; ?>
                <span style="position:absolute; top:10px; right:10px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; background:rgba(245,158,11,0.15); color:#F59E0B; border:1px solid rgba(245,158,11,0.25);">
                    <i class="fas fa-clock"></i> Pending
                </span>
            </div>
            <div style="padding:16px;">
                <h4 style="font-size:15px; font-weight:700; color:var(--text-white); margin-bottom:8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($video['title']) ?>
                </h4>
                <div style="display:flex; flex-wrap:wrap; gap:12px; font-size:13px; color:var(--text-dim);">
                    <span style="display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-user" style="color:var(--primary); font-size:11px;"></i>
                        <?= htmlspecialchars(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')) ?>
                    </span>
                    <span style="display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-calendar" style="color:var(--primary); font-size:11px;"></i>
                        <?= date('M d, Y', strtotime($video['upload_date'])) ?>
                    </span>
                </div>
                <div style="margin-top:8px;">
                    <span style="padding:3px 8px; border-radius:20px; font-size:11px; font-weight:600; <?= ($video['video_category'] ?? 'drill') === 'game' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(107,70,193,0.15); color:var(--primary);' ?>">
                        <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                        <?= ucfirst($video['video_category'] ?? 'drill') ?>
                    </span>
                </div>
                <?php if (!empty($video['description'])): ?>
                <p style="margin-top:8px; font-size:12px; color:var(--text-dim); opacity:0.7; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                    <?= htmlspecialchars($video['description']) ?>
                </p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:60px 24px;">
        <i class="fas fa-check-circle" style="font-size:64px; color:var(--primary); opacity:0.25; display:block; margin-bottom:20px;"></i>
        <p style="font-size:15px; color:var(--text-dim); max-width:400px; margin:0 auto;">No pending video reviews! All caught up.</p>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ═══════════════ REVIEWED TAB ═══════════════ -->
<?php if (!$selected_athlete): ?>
<!-- Athlete list -->
<div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px;">
    <h3 style="font-size:18px; font-weight:700; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; color:var(--text-white);">
        <span style="width:4px; height:24px; background:linear-gradient(180deg, #10B981, #059669); border-radius:2px; display:inline-block;"></span>
        Reviewed — Select an Athlete
    </h3>

    <?php if (!empty($reviewed_by_athlete)): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:16px;">
        <?php foreach ($reviewed_by_athlete as $aid => $group): ?>
        <a href="?page=coach_video_reviews&tab=reviewed&athlete_id=<?= $aid ?>"
           style="text-decoration:none; display:flex; align-items:center; gap:16px; padding:20px; background:linear-gradient(135deg, var(--bg-main) 0%, rgba(22,22,31,0.8) 100%); border:1px solid var(--border); border-radius:12px; transition:all 0.3s ease;">
            <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg, var(--primary), #8B5CF6); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-user" style="color:white; font-size:18px;"></i>
            </div>
            <div style="flex:1; min-width:0;">
                <h4 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0 0 4px 0;">
                    <?= htmlspecialchars($group['name']) ?>
                </h4>
                <span style="font-size:13px; color:var(--text-dim);">
                    <?= count($group['videos']) ?> reviewed video<?= count($group['videos']) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <i class="fas fa-chevron-right" style="color:var(--text-dim); font-size:14px;"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:60px 24px;">
        <i class="fas fa-folder-open" style="font-size:64px; color:var(--primary); opacity:0.25; display:block; margin-bottom:20px;"></i>
        <p style="font-size:15px; color:var(--text-dim); max-width:400px; margin:0 auto;">No reviewed videos yet.</p>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Videos for selected athlete -->
<?php
$athlete_videos = $reviewed_by_athlete[(int)$selected_athlete]['videos'] ?? [];
$athlete_name   = $reviewed_by_athlete[(int)$selected_athlete]['name'] ?? 'Unknown Athlete';
?>
<div style="margin-bottom:16px;">
    <a href="?page=coach_video_reviews&tab=reviewed" style="color:var(--text-dim); text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px;">
        <i class="fas fa-arrow-left"></i> Back to Athletes
    </a>
</div>

<div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:28px;">
    <h3 style="font-size:18px; font-weight:700; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; color:var(--text-white);">
        <span style="width:4px; height:24px; background:linear-gradient(180deg, #10B981, #059669); border-radius:2px; display:inline-block;"></span>
        Reviewed Videos — <?= htmlspecialchars($athlete_name) ?>
    </h3>

    <?php if (!empty($athlete_videos)): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php foreach ($athlete_videos as $video): ?>
        <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coach_video_reviews&tab=reviewed&athlete_id=<?= $selected_athlete ?>"
           style="text-decoration:none; display:block; background:linear-gradient(135deg, var(--bg-main) 0%, rgba(22,22,31,0.8) 100%); border:1px solid var(--border); border-radius:12px; overflow:hidden; transition:all 0.3s ease;">
            <div style="width:100%; height:160px; background:linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.08)); display:flex; align-items:center; justify-content:center; position:relative;">
                <?php if (!empty($video['thumbnail_url'])): ?>
                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-video" style="font-size:36px; color:#10B981; opacity:0.4;"></i>
                <?php endif; ?>
                <span style="position:absolute; top:10px; right:10px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; background:rgba(16,185,129,0.15); color:#10B981; border:1px solid rgba(16,185,129,0.25);">
                    <i class="fas fa-check-circle"></i> Reviewed
                </span>
            </div>
            <div style="padding:16px;">
                <h4 style="font-size:15px; font-weight:700; color:var(--text-white); margin-bottom:8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($video['title']) ?>
                </h4>
                <div style="display:flex; flex-wrap:wrap; gap:12px; font-size:13px; color:var(--text-dim);">
                    <span style="display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-calendar" style="color:var(--primary); font-size:11px;"></i>
                        <?= date('M d, Y', strtotime($video['upload_date'])) ?>
                    </span>
                    <span style="padding:3px 8px; border-radius:20px; font-size:11px; font-weight:600; <?= ($video['video_category'] ?? 'drill') === 'game' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(107,70,193,0.15); color:var(--primary);' ?>">
                        <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                        <?= ucfirst($video['video_category'] ?? 'drill') ?>
                    </span>
                </div>
                <?php if (!empty($video['coach_notes'])): ?>
                <p style="margin-top:8px; font-size:12px; color:var(--text-dim); opacity:0.7; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                    <i class="fas fa-comment" style="margin-right:4px;"></i><?= htmlspecialchars($video['coach_notes']) ?>
                </p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:60px 24px;">
        <i class="fas fa-folder-open" style="font-size:64px; color:#10B981; opacity:0.25; display:block; margin-bottom:20px;"></i>
        <p style="font-size:15px; color:var(--text-dim);">No reviewed videos for this athlete.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
