<?php
/**
 * Coach Pending Video Reviews
 * Shows all pending video reviews for athletes in the coach's roster.
 * Available in Coaches Corner for coaches and admins.
 */
require_once __DIR__ . '/../lib/image_helper.php';

// Get filter parameters
$filter_athlete = $_GET['filter_athlete'] ?? 'all';
$filter_category = $_GET['filter_category'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Get athletes assigned to this coach
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
    // Fallback: show all athletes if none directly assigned
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

// Build query for pending videos from roster athletes
$video_query = "
    SELECT v.*, 
           a.first_name as athlete_first_name, a.last_name as athlete_last_name,
           a.email as athlete_email
    FROM videos v
    LEFT JOIN users a ON v.athlete_id = a.id
    WHERE v.video_type = 'uploaded_by_athlete'
    AND v.status = 'pending_review'
    AND (v.coach_id = ? OR a.assigned_coach_id = ?)
";
$params = [$user_id, $user_id];

if ($filter_athlete !== 'all') {
    $video_query .= " AND v.athlete_id = ?";
    $params[] = $filter_athlete;
}

if ($filter_category !== 'all') {
    $video_query .= " AND v.video_category = ?";
    $params[] = $filter_category;
}

if (!empty($search_query)) {
    $video_query .= " AND (v.title LIKE ? OR v.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$video_query .= " ORDER BY v.upload_date DESC LIMIT 100";

$video_stmt = $pdo->prepare($video_query);
$video_stmt->execute($params);
$pending_videos = $video_stmt->fetchAll();
foreach ($pending_videos as &$v) {
    foreach (['athlete_first_name', 'athlete_last_name'] as $f) {
        if (!empty($v[$f])) $v[$f] = FieldEncryption::decrypt($v[$f]);
    }
}
unset($v);
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-clipboard-check"></i> Pending Video Reviews</h1>
    <p class="page-description">Videos uploaded by your athletes awaiting review</p>
</div>

<div class="coach-pending-content" style="max-width: 1400px; margin: 0 auto; padding: 0 16px;">
    <!-- Filters -->
    <form method="GET" action="" class="filter-bar" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <input type="hidden" name="page" value="coach_pending_reviews">
        
        <div class="search-wrapper" style="display:flex;">
            <input type="text" name="search" class="form-input-small" 
                   placeholder="Search videos..." 
                   value="<?= htmlspecialchars($search_query) ?>"
                   style="min-width:200px; height:42px; background:var(--bg-main); border:1px solid var(--border); border-radius:8px 0 0 8px; color:var(--text-white); font-size:14px; padding:0 12px;">
            <button type="submit" style="padding:0 16px; height:42px; background:var(--primary); border:none; border-radius:0 8px 8px 0; color:white; cursor:pointer;"><i class="fas fa-search"></i></button>
        </div>
        
        <?php if (!empty($athletes)): ?>
        <select name="filter_athlete" onchange="this.form.submit()" style="min-width:160px; height:42px; background:var(--bg-main); border:1px solid var(--border); border-radius:8px; color:var(--text-white); font-size:14px; padding:0 12px;">
            <option value="all">All Athletes</option>
            <?php foreach ($athletes as $athlete): ?>
                <option value="<?= $athlete['id'] ?>" <?= $filter_athlete == $athlete['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <select name="filter_category" onchange="this.form.submit()" style="min-width:120px; height:42px; background:var(--bg-main); border:1px solid var(--border); border-radius:8px; color:var(--text-white); font-size:14px; padding:0 12px;">
            <option value="all" <?= $filter_category === 'all' ? 'selected' : '' ?>>All Types</option>
            <option value="drill" <?= $filter_category === 'drill' ? 'selected' : '' ?>>Drill</option>
            <option value="game" <?= $filter_category === 'game' ? 'selected' : '' ?>>Game</option>
        </select>
    </form>

    <!-- Pending Videos List -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 28px;">
        <h3 style="font-size:18px; font-weight:700; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; color:var(--text-white);">
            <span style="width:4px; height:24px; background:linear-gradient(180deg, var(--primary), var(--accent, #8B5CF6)); border-radius:2px; display:inline-block;"></span>
            Pending Reviews (<?= count($pending_videos) ?>)
        </h3>
        
        <?php if (count($pending_videos) > 0): ?>
            <?php foreach ($pending_videos as $video): ?>
            <div class="video-list-item" style="display:grid; grid-template-columns:120px 1fr auto auto; align-items:center; gap:20px; padding:16px 20px; background:linear-gradient(135deg, var(--bg-main) 0%, rgba(22,22,31,0.8) 100%); border:1px solid var(--border); border-radius:12px; margin-bottom:12px; transition:all 0.3s ease;">
                <div style="width:120px; height:80px; background:linear-gradient(135deg, rgba(107,70,193,0.15), rgba(139,92,246,0.1)); border-radius:10px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(107,70,193,0.2);">
                    <?php if (!empty($video['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                    <?php else: ?>
                        <i class="fas fa-video" style="font-size:28px; color:var(--primary); opacity:0.5;"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <h4 style="font-size:15px; font-weight:700; color:var(--text-white); margin-bottom:8px;"><?= htmlspecialchars($video['title']) ?></h4>
                    <div style="display:flex; flex-wrap:wrap; gap:16px;">
                        <span style="display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--text-dim);">
                            <i class="fas fa-user" style="color:var(--primary); font-size:12px;"></i> <?= htmlspecialchars(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')) ?>
                        </span>
                        <span style="display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--text-dim);">
                            <i class="fas fa-calendar" style="color:var(--primary); font-size:12px;"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?>
                        </span>
                        <span style="padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; <?= ($video['video_category'] ?? 'drill') === 'game' ? 'background:rgba(16,185,129,0.15); color:#10B981;' : 'background:rgba(107,70,193,0.15); color:var(--primary);' ?>">
                            <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                            <?= ucfirst($video['video_category'] ?? 'drill') ?>
                        </span>
                    </div>
                    <?php if (!empty($video['description'])): ?>
                    <p style="margin-top:8px; font-size:13px; color:var(--text-dim); opacity:0.7;"><?= htmlspecialchars(mb_strimwidth($video['description'], 0, 120, '...')) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <span style="display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; background:rgba(245,158,11,0.12); color:#F59E0B; border:1px solid rgba(245,158,11,0.25);">
                        <i class="fas fa-clock"></i> Pending
                    </span>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="?page=coaches_reviews&filter_athlete=<?= $video['athlete_id'] ?>" 
                       style="width:38px; height:38px; background:var(--primary); border:1px solid var(--primary); color:white; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all 0.25s ease;"
                       title="Review">
                        <i class="fas fa-check"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px 24px;">
                <i class="fas fa-check-circle" style="font-size:64px; color:var(--primary); opacity:0.25; display:block; margin-bottom:20px;"></i>
                <p style="font-size:15px; color:var(--text-dim); max-width:400px; margin:0 auto;">
                    No pending video reviews! All caught up.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
