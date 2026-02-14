<?php
/**
 * Game Plan - Video Review View
 * Full video review interface with drill review, coach review and upload tabs.
 * Merges ACVideoReview functionality into the Game Plan module.
 */

// ── Data loading ──────────────────────────────────────────────

// Assigned coach (for athletes)
$assigned_coach_id = null;
$assigned_coach_name = '';
if ($user_role === 'athlete' || $user_role === 'parent') {
    try {
        $coach_stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
        $coach_stmt->execute([$user_id]);
        $coach_row = $coach_stmt->fetch();
        $assigned_coach_id = $coach_row['assigned_coach_id'] ?? null;
        if ($assigned_coach_id) {
            $coach_name_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $coach_name_stmt->execute([$assigned_coach_id]);
            $coach_name_row = $coach_name_stmt->fetch(PDO::FETCH_ASSOC);
            if ($coach_name_row) {
                $coach_name_row = decryptUserRow($coach_name_row);
                $assigned_coach_name = trim(($coach_name_row['first_name'] ?? '') . ' ' . ($coach_name_row['last_name'] ?? ''));
            }
        }
    } catch (PDOException $e) { /* ignore */ }
}

// User teams
$user_teams = [];
try {
    $teams_stmt = $pdo->prepare("
        SELECT id, team_name, league, is_current
        FROM athlete_teams
        WHERE (user_id = ? OR athlete_id = ?) AND is_current = 1
        ORDER BY team_name
    ");
    $teams_stmt->execute([$user_id, $user_id]);
    $user_teams = $teams_stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Athletes list for coaches
$vr_athletes = [];
if ($isAnyCoach) {
    try {
        $aq = $pdo->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
            FROM users u WHERE u.assigned_coach_id = ? AND u.is_active = 1 AND u.role = 'athlete'
            ORDER BY u.last_name, u.first_name
        ");
        $aq->execute([$user_id]);
        $vr_athletes = $aq->fetchAll();
        $vr_athletes = decryptUserRows($vr_athletes);
        if (empty($vr_athletes)) {
            $aq2 = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email FROM users u WHERE u.is_active = 1 AND u.role = 'athlete' ORDER BY u.last_name, u.first_name");
            $vr_athletes = $aq2->fetchAll();
            $vr_athletes = decryptUserRows($vr_athletes);
        }
    } catch (PDOException $e) { /* ignore */ }
}

// Filter parameters
$vr_filter_athlete  = $_GET['filter_athlete'] ?? 'all';
$vr_filter_period   = $_GET['filter_period'] ?? 'all';
$vr_filter_category = $_GET['filter_category'] ?? 'all';
$vr_search          = $_GET['search'] ?? '';

// Build video query (coach review videos)
if ($isAnyCoach) {
    $vr_query = "
        SELECT v.*, 
               a.first_name as athlete_first_name, a.last_name as athlete_last_name,
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               d.title as drill_title, s.title as session_title, s.session_date
        FROM videos v
        LEFT JOIN users a ON v.athlete_id = a.id
        LEFT JOIN users c ON v.coach_id = c.id
        LEFT JOIN drills d ON v.drill_id = d.id
        LEFT JOIN sessions s ON v.session_id = s.id
        WHERE (v.coach_id = ? OR a.assigned_coach_id = ?)
    ";
    $vr_params = [$user_id, $user_id];
} else {
    $vr_query = "
        SELECT v.*, 
               a.first_name as athlete_first_name, a.last_name as athlete_last_name,
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               d.title as drill_title, s.title as session_title, s.session_date
        FROM videos v
        LEFT JOIN users a ON v.athlete_id = a.id
        LEFT JOIN users c ON v.coach_id = c.id
        LEFT JOIN drills d ON v.drill_id = d.id
        LEFT JOIN sessions s ON v.session_id = s.id
        WHERE v.athlete_id = ?
    ";
    $vr_params = [$user_id];
}

if ($vr_filter_athlete !== 'all' && $isAnyCoach) {
    $vr_query .= " AND v.athlete_id = ?";
    $vr_params[] = $vr_filter_athlete;
}
if ($vr_filter_category !== 'all') {
    $vr_query .= " AND v.video_category = ?";
    $vr_params[] = $vr_filter_category;
}
if ($vr_filter_period === 'today') {
    $vr_query .= " AND DATE(v.created_at) = CURDATE()";
} elseif ($vr_filter_period === 'week') {
    $vr_query .= " AND v.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($vr_filter_period === 'month') {
    $vr_query .= " AND v.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}
if (!empty($vr_search)) {
    $vr_query .= " AND (v.title LIKE ? OR v.description LIKE ?)";
    $vr_params[] = "%$vr_search%";
    $vr_params[] = "%$vr_search%";
}
$vr_query .= " ORDER BY v.created_at DESC LIMIT 50";

$vr_videos = [];
try {
    $vr_stmt = $pdo->prepare($vr_query);
    $vr_stmt->execute($vr_params);
    $vr_videos = $vr_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vr_videos as &$_v) {
        foreach (['athlete_first_name','athlete_last_name','coach_first_name','coach_last_name'] as $_f) {
            if (!empty($_v[$_f])) $_v[$_f] = FieldEncryption::decrypt($_v[$_f]);
        }
    }
    unset($_v);
} catch (PDOException $e) { /* ignore */ }

$vr_pending  = array_filter($vr_videos, function($v) { return ($v['status'] ?? '') === 'pending_review' || ($v['status'] ?? '') === 'pending'; });
$vr_reviewed = array_filter($vr_videos, function($v) { return ($v['status'] ?? '') === 'reviewed'; });
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-film"></i> Video Review</h1>
    <p class="gp-page-desc">
        <?php if ($isAnyCoach): ?>
            Review athlete videos, provide feedback, and manage uploads
        <?php else: ?>
            Upload videos for coach review and track feedback
        <?php endif; ?>
    </p>
</div>

<!-- Tabs -->
<div class="vr-tabs-bar">
    <div class="vr-tabs">
        <button class="vr-tab vr-tab-active" data-vr-tab="pending" type="button">
            <i class="fas fa-clock"></i> Pending (<?= count($vr_pending) ?>)
        </button>
        <button class="vr-tab" data-vr-tab="reviewed" type="button">
            <i class="fas fa-check-circle"></i> Reviewed (<?= count($vr_reviewed) ?>)
        </button>
        <button class="vr-tab" data-vr-tab="upload" type="button">
            <i class="fas fa-upload"></i> Upload
        </button>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="vr-filters">
        <input type="hidden" name="page" value="video_review">
        <div class="vr-search-wrap">
            <input type="text" name="search" class="vr-input" placeholder="Search videos…" value="<?= htmlspecialchars($vr_search) ?>">
            <button type="submit" class="vr-search-btn"><i class="fas fa-search"></i></button>
        </div>
        <?php if ($isAnyCoach && !empty($vr_athletes)): ?>
        <select name="filter_athlete" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="all">All Athletes</option>
            <?php foreach ($vr_athletes as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $vr_filter_athlete == $a['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="filter_category" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="all" <?= $vr_filter_category === 'all' ? 'selected' : '' ?>>All Types</option>
            <option value="drill" <?= $vr_filter_category === 'drill' ? 'selected' : '' ?>>Drill</option>
            <option value="game" <?= $vr_filter_category === 'game' ? 'selected' : '' ?>>Game</option>
        </select>
        <select name="filter_period" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="all" <?= $vr_filter_period === 'all' ? 'selected' : '' ?>>All Time</option>
            <option value="today" <?= $vr_filter_period === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= $vr_filter_period === 'week' ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= $vr_filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
        </select>
    </form>
</div>

<!-- ── Pending Tab ── -->
<div class="vr-panel vr-panel-visible" id="vr-panel-pending">
    <h3 class="vr-section-title">Pending Reviews (<?= count($vr_pending) ?>)</h3>
    <?php if (!empty($vr_pending)): ?>
        <?php foreach ($vr_pending as $video): ?>
        <div class="vr-video-row" data-video-id="<?= (int)$video['id'] ?>">
            <div class="vr-thumb"><i class="fas fa-play-circle"></i></div>
            <div class="vr-details">
                <h4><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></h4>
                <div class="vr-meta">
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars(trim(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? ''))) ?></span>
                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($video['created_at'])) ?></span>
                    <?php $cat = $video['video_category'] ?? 'drill'; ?>
                    <span class="vr-cat-badge vr-cat-<?= $cat ?>">
                        <i class="fas <?= $cat === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i> <?= ucfirst($cat) ?>
                    </span>
                </div>
            </div>
            <span class="vr-status-badge vr-badge-pending"><i class="fas fa-clock"></i> Pending</span>
            <div class="vr-actions">
                <?php if ($isAnyCoach): ?>
                <button type="button" class="vr-btn-icon vr-btn-review" title="Review"
                        data-vr-review="<?= (int)$video['id'] ?>"
                        data-vr-title="<?= htmlspecialchars($video['title'] ?? 'Untitled', ENT_QUOTES) ?>"
                        data-vr-athlete="<?= htmlspecialchars(trim(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')), ENT_QUOTES) ?>">
                    <i class="fas fa-clipboard-check"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="vr-btn-icon vr-btn-delete" title="Delete" data-vr-delete="<?= (int)$video['id'] ?>">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="gp-empty">
            <i class="fas fa-clock"></i>
            <p><?= $isAnyCoach ? 'No videos pending review.' : 'No pending reviews. Upload a video for your coach!' ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- ── Reviewed Tab ── -->
<div class="vr-panel" id="vr-panel-reviewed">
    <h3 class="vr-section-title">Reviewed Videos (<?= count($vr_reviewed) ?>)</h3>
    <?php if (!empty($vr_reviewed)): ?>
        <?php foreach ($vr_reviewed as $video): ?>
        <div class="vr-video-row" data-video-id="<?= (int)$video['id'] ?>">
            <div class="vr-thumb"><i class="fas fa-play-circle"></i></div>
            <div class="vr-details">
                <h4><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></h4>
                <div class="vr-meta">
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars(trim(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? ''))) ?></span>
                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($video['created_at'])) ?></span>
                    <?php $cat = $video['video_category'] ?? 'drill'; ?>
                    <span class="vr-cat-badge vr-cat-<?= $cat ?>">
                        <i class="fas <?= $cat === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i> <?= ucfirst($cat) ?>
                    </span>
                </div>
                <?php if (!empty($video['coach_notes'])): ?>
                <div class="vr-coach-notes">
                    <i class="fas fa-comment"></i> <?= htmlspecialchars(substr($video['coach_notes'], 0, 120)) ?><?= strlen($video['coach_notes'] ?? '') > 120 ? '…' : '' ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="vr-status-badge vr-badge-reviewed"><i class="fas fa-check-circle"></i> Reviewed</span>
            <div class="vr-actions">
                <button type="button" class="vr-btn-icon vr-btn-delete" title="Delete" data-vr-delete="<?= (int)$video['id'] ?>">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="gp-empty">
            <i class="fas fa-check-circle"></i>
            <p>No reviewed videos yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ── Upload Tab ── -->
<div class="vr-panel" id="vr-panel-upload">
    <div class="vr-upload-card">
        <h3><i class="fas fa-cloud-upload-alt"></i> Upload Video for Review</h3>

        <?php if (!$isAnyCoach && !$assigned_coach_id): ?>
        <div class="vr-alert vr-alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>You don't have an assigned coach yet. Please contact an administrator.</span>
        </div>
        <?php else: ?>
        <?php if (!$isAnyCoach && $assigned_coach_name): ?>
        <div class="vr-alert vr-alert-info">
            <i class="fas fa-user-tie"></i>
            <span>Your coach: <strong><?= htmlspecialchars($assigned_coach_name) ?></strong></span>
        </div>
        <?php endif; ?>

        <form class="vr-upload-form" method="POST" action="/process_video.php" enctype="multipart/form-data" id="vrUploadForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="athlete_upload_video">
            <?php if (!$isAnyCoach && $assigned_coach_id): ?>
            <input type="hidden" name="coach_id" value="<?= (int)$assigned_coach_id ?>">
            <?php endif; ?>
            <input type="hidden" name="athlete_id" value="<?= (int)$user_id ?>">

            <div class="vr-form-row">
                <div class="vr-form-group">
                    <label>Video Title <span class="vr-req">*</span></label>
                    <input type="text" name="title" class="vr-input" placeholder="e.g., Power Play Practice" required>
                </div>
                <div class="vr-form-group">
                    <label>Video Type <span class="vr-req">*</span></label>
                    <select name="video_category" class="vr-input" id="vrCategorySelect" required>
                        <option value="">-- Select Type --</option>
                        <option value="drill">Drill / Practice</option>
                        <option value="game">Game Footage</option>
                    </select>
                </div>
            </div>

            <div id="vrGameFields" style="display:none;">
                <div class="vr-form-row">
                    <div class="vr-form-group">
                        <label>Game Date <span class="vr-req">*</span></label>
                        <input type="date" name="game_date" class="vr-input" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="vr-form-group">
                        <label>Your Team <span class="vr-req">*</span></label>
                        <select name="team_played_on" class="vr-input">
                            <option value="">-- Select Team --</option>
                            <?php foreach ($user_teams as $team): ?>
                            <option value="<?= htmlspecialchars($team['team_name']) ?>">
                                <?= htmlspecialchars($team['team_name']) ?>
                                <?= !empty($team['league']) ? ' (' . htmlspecialchars($team['league']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="vr-form-group">
                    <label>Opponent Team <span class="vr-req">*</span></label>
                    <input type="text" name="opponent_team" class="vr-input" placeholder="e.g., Thunder Bay Kings">
                </div>
            </div>

            <div id="vrDrillFields">
                <div class="vr-form-row">
                    <div class="vr-form-group">
                        <label>Drill Type</label>
                        <select name="drill_type" class="vr-input">
                            <option value="">-- Select Drill Type --</option>
                            <option value="skating">Skating</option>
                            <option value="shooting">Shooting</option>
                            <option value="passing">Passing</option>
                            <option value="stickhandling">Stickhandling</option>
                            <option value="defensive">Defensive</option>
                            <option value="conditioning">Conditioning</option>
                            <option value="goaltending">Goaltending</option>
                            <option value="team_play">Team Play</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="vr-form-group">
                        <label>Practice Date</label>
                        <input type="date" name="session_date" class="vr-input" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>

            <div class="vr-form-group">
                <label>Video File <span class="vr-req">*</span></label>
                <div class="vr-file-area" id="vrFileArea">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Drag &amp; drop video file here or click to browse</p>
                    <span class="vr-file-hint">Supported: MP4, MOV, AVI, WebM (max 500 MB)</span>
                    <input type="file" name="video_file" accept="video/*" id="vrFileInput" style="display:none;" required>
                </div>
                <div class="vr-selected-file" id="vrSelectedFile" style="display:none;">
                    <i class="fas fa-file-video"></i>
                    <span id="vrFileName"></span>
                    <button type="button" class="vr-btn-remove" id="vrRemoveFile"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <div class="vr-form-group">
                <label>Notes for Coach</label>
                <textarea name="description" class="vr-input vr-textarea" rows="4" placeholder="Describe what you'd like feedback on…"></textarea>
            </div>

            <div class="vr-form-actions">
                <button type="submit" class="vr-btn-primary"><i class="fas fa-upload"></i> Upload for Review</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<?php if ($isAnyCoach): ?>
<div class="vr-modal-overlay" id="vrReviewModal">
    <div class="vr-modal-sheet">
        <div class="vr-modal-header">
            <span class="vr-modal-title">Review Video</span>
            <button type="button" class="vr-modal-close" id="vrCloseModal" aria-label="Close">&times;</button>
        </div>
        <div class="vr-modal-athlete" id="vrReviewInfo"></div>
        <form id="vrReviewForm" method="POST" action="/process_video.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="review_video">
            <input type="hidden" name="video_id" id="vrReviewVideoId" value="">
            <textarea class="vr-input vr-textarea" name="coach_notes" id="vrReviewNotes" rows="6" placeholder="Enter your coaching notes…" required></textarea>
            <button type="submit" class="vr-btn-primary vr-btn-full" id="vrReviewSubmit">
                <i class="fas fa-check"></i> Submit Review
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="vr-toast" id="vrToast"></div>

<style>
/* ── Video Review styles (scoped with vr- prefix) ── */
.vr-tabs-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.vr-tabs { display: flex; gap: 4px; background: rgba(10,10,15,.6); padding: 5px; border-radius: 10px; border: 1px solid rgba(45,45,63,.5); }
.vr-tab { padding: 10px 18px; background: transparent; border: none; color: var(--gp-text-dim); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: 7px; font-family: 'Inter', sans-serif; height: auto; }
.vr-tab:hover { color: var(--gp-text); background: rgba(107,70,193,.12); }
.vr-tab.vr-tab-active { color: #fff; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); }

.vr-filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.vr-search-wrap { display: flex; }
.vr-search-wrap .vr-input { border-radius: 8px 0 0 8px; min-width: 180px; }
.vr-search-btn { padding: 0 14px; height: 40px; background: var(--gp-primary); border: none; border-radius: 0 8px 8px 0; color: #fff; cursor: pointer; font-family: 'Inter', sans-serif; }
.vr-select { min-width: 130px; }

.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-textarea { height: auto; min-height: 100px; resize: vertical; }

.vr-panel { display: none; }
.vr-panel.vr-panel-visible { display: block; animation: vrFadeIn .35s ease-out; }
@keyframes vrFadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.vr-section-title { font-size: 16px; font-weight: 700; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--gp-border); display: flex; align-items: center; gap: 10px; color: var(--gp-text); }
.vr-section-title::before { content: ''; width: 4px; height: 20px; background: linear-gradient(180deg, var(--gp-primary), var(--gp-primary-light)); border-radius: 2px; }

.vr-video-row { display: grid; grid-template-columns: 80px 1fr auto auto; align-items: center; gap: 16px; padding: 14px 18px; background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; margin-bottom: 10px; transition: border-color .2s, transform .15s; }
.vr-video-row:hover { border-color: rgba(107,70,193,.4); transform: translateY(-2px); }

.vr-thumb { width: 80px; height: 56px; background: rgba(107,70,193,.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.2); }
.vr-details { min-width: 0; }
.vr-details h4 { font-size: 14px; font-weight: 700; color: var(--gp-text); margin: 0 0 6px; }
.vr-meta { display: flex; flex-wrap: wrap; gap: 14px; }
.vr-meta span { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--gp-text-muted); }
.vr-meta i { color: var(--gp-primary-light); font-size: 11px; }
.vr-cat-badge { padding: 3px 9px; border-radius: 16px; font-size: 10px; font-weight: 600; }
.vr-cat-drill { background: rgba(107,70,193,.12); color: var(--gp-primary-light); }
.vr-cat-game { background: rgba(16,185,129,.12); color: #10B981; }
.vr-coach-notes { margin-top: 8px; padding: 7px 10px; background: rgba(107,70,193,.08); border-radius: 7px; font-size: 12px; color: var(--gp-text-muted); display: flex; align-items: flex-start; gap: 7px; }
.vr-coach-notes i { color: var(--gp-primary-light); margin-top: 2px; }

.vr-status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 16px; font-size: 10px; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
.vr-badge-pending { background: rgba(245,158,11,.1); color: #F59E0B; border: 1px solid rgba(245,158,11,.2); }
.vr-badge-reviewed { background: rgba(16,185,129,.1); color: #10B981; border: 1px solid rgba(16,185,129,.2); }

.vr-actions { display: flex; gap: 6px; }
.vr-btn-icon { width: 36px; height: 36px; background: var(--gp-bg); border: 1px solid var(--gp-border); color: var(--gp-text-muted); border-radius: 8px; cursor: pointer; transition: all .2s; display: flex; align-items: center; justify-content: center; padding: 0; font-family: 'Inter', sans-serif; }
.vr-btn-icon:hover { transform: translateY(-1px); }
.vr-btn-review:hover { background: #10B981; border-color: #10B981; color: #fff; }
.vr-btn-delete:hover { background: #EF4444; border-color: #EF4444; color: #fff; }

/* Upload card */
.vr-upload-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 28px; }
.vr-upload-card h3 { font-size: 18px; font-weight: 700; margin: 0 0 24px; display: flex; align-items: center; gap: 10px; color: var(--gp-text); }
.vr-upload-card h3 i { color: var(--gp-primary-light); }

.vr-alert { padding: 14px 18px; border-radius: 10px; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 13px; }
.vr-alert-warning { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.25); color: #F59E0B; }
.vr-alert-info { background: rgba(59,130,246,.08); border: 1px solid rgba(59,130,246,.25); color: #3B82F6; }

.vr-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.vr-form-group { margin-bottom: 18px; }
.vr-form-group label { display: block; font-size: 11px; font-weight: 600; color: var(--gp-text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .5px; }
.vr-req { color: #EF4444; }

.vr-file-area { border: 2px dashed var(--gp-border); border-radius: 12px; padding: 40px 24px; text-align: center; cursor: pointer; transition: border-color .2s; }
.vr-file-area:hover { border-color: var(--gp-primary-light); }
.vr-file-area i { font-size: 40px; color: var(--gp-primary-light); opacity: .4; display: block; margin-bottom: 12px; }
.vr-file-area p { color: var(--gp-text-muted); font-size: 13px; margin: 0 0 6px; }
.vr-file-hint { font-size: 11px; color: var(--gp-text-dim); }

.vr-selected-file { display: flex; align-items: center; gap: 10px; padding: 14px; background: rgba(107,70,193,.08); border: 1px solid var(--gp-primary); border-radius: 10px; }
.vr-selected-file i { font-size: 20px; color: var(--gp-primary-light); }
.vr-selected-file span { flex: 1; color: var(--gp-text); font-weight: 500; font-size: 13px; }
.vr-btn-remove { background: transparent; border: none; color: var(--gp-text-dim); cursor: pointer; padding: 4px 6px; font-family: 'Inter', sans-serif; height: auto; }
.vr-btn-remove:hover { color: #EF4444; }

.vr-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--gp-border); margin-top: 24px; }
.vr-btn-primary { padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); border: none; color: #fff; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-family: 'Inter', sans-serif; transition: opacity .2s; height: auto; }
.vr-btn-primary:hover { opacity: .9; }
.vr-btn-full { width: 100%; justify-content: center; margin-top: 12px; }

/* Review Modal */
.vr-modal-overlay { display: none; position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,.65); align-items: center; justify-content: center; }
.vr-modal-overlay.vr-modal-open { display: flex; }
.vr-modal-sheet { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 16px; width: 90%; max-width: 520px; padding: 24px; animation: vrSlideIn .25s ease-out; }
@keyframes vrSlideIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.vr-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.vr-modal-title { font-size: 16px; font-weight: 700; color: var(--gp-text); }
.vr-modal-close { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--gp-border); background: transparent; color: var(--gp-text-muted); font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; font-family: 'Inter', sans-serif; }
.vr-modal-close:hover { background: var(--gp-primary); border-color: var(--gp-primary); color: #fff; }
.vr-modal-athlete { font-size: 13px; color: var(--gp-text-muted); margin-bottom: 12px; }

/* Toast */
.vr-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 10px 22px; border-radius: 10px; font-size: 13px; font-weight: 600; z-index: 300; opacity: 0; transition: opacity .3s; font-family: 'Inter', sans-serif; pointer-events: none; }
.vr-toast.vr-toast-show { opacity: 1; }
.vr-toast-success { background: rgba(16,185,129,.92); color: #fff; }
.vr-toast-error { background: rgba(239,68,68,.92); color: #fff; }

@media (max-width: 768px) {
    .vr-tabs-bar { flex-direction: column; align-items: stretch; padding: 14px; }
    .vr-filters { flex-direction: column; width: 100%; }
    .vr-search-wrap { width: 100%; }
    .vr-search-wrap .vr-input { width: 100%; }
    .vr-select { width: 100%; }
    .vr-video-row { grid-template-columns: 1fr; }
    .vr-thumb { display: none; }
    .vr-form-row { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    /* Tab switching */
    document.querySelectorAll('[data-vr-tab]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.vr-tab').forEach(function(t) { t.classList.remove('vr-tab-active'); });
            document.querySelectorAll('.vr-panel').forEach(function(p) { p.classList.remove('vr-panel-visible'); });
            btn.classList.add('vr-tab-active');
            var panel = document.getElementById('vr-panel-' + btn.dataset.vrTab);
            if (panel) panel.classList.add('vr-panel-visible');
        });
    });

    /* Toast helper */
    function vrToast(msg, type) {
        var t = document.getElementById('vrToast');
        t.textContent = msg;
        t.className = 'vr-toast vr-toast-show ' + (type === 'error' ? 'vr-toast-error' : 'vr-toast-success');
        setTimeout(function() { t.classList.remove('vr-toast-show'); }, 3000);
    }

    /* Category toggle */
    var catSelect = document.getElementById('vrCategorySelect');
    if (catSelect) {
        catSelect.addEventListener('change', function() {
            var gf = document.getElementById('vrGameFields');
            var df = document.getElementById('vrDrillFields');
            if (this.value === 'game') {
                gf.style.display = 'block';
                df.style.display = 'none';
            } else {
                gf.style.display = 'none';
                df.style.display = 'block';
            }
        });
    }

    /* File upload area */
    var fileArea = document.getElementById('vrFileArea');
    var fileInput = document.getElementById('vrFileInput');
    var selectedFile = document.getElementById('vrSelectedFile');
    var fileName = document.getElementById('vrFileName');
    var removeBtn = document.getElementById('vrRemoveFile');
    var MAX_FILE_SIZE = 500 * 1024 * 1024;

    if (fileArea && fileInput) {
        fileArea.addEventListener('click', function() { fileInput.click(); });
        fileArea.addEventListener('dragover', function(e) { e.preventDefault(); fileArea.style.borderColor = 'var(--gp-primary-light)'; });
        fileArea.addEventListener('dragleave', function(e) { e.preventDefault(); fileArea.style.borderColor = ''; });
        fileArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileArea.style.borderColor = '';
            if (e.dataTransfer.files.length > 0 && e.dataTransfer.files[0].type.startsWith('video/')) {
                if (e.dataTransfer.files[0].size <= MAX_FILE_SIZE) {
                    fileInput.files = e.dataTransfer.files;
                    showFile(e.dataTransfer.files[0]);
                } else {
                    vrToast('File exceeds 500 MB limit', 'error');
                }
            }
        });
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                if (this.files[0].size <= MAX_FILE_SIZE) {
                    showFile(this.files[0]);
                } else {
                    vrToast('File exceeds 500 MB limit', 'error');
                    this.value = '';
                }
            }
        });
    }
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            fileInput.value = '';
            selectedFile.style.display = 'none';
            fileArea.style.display = 'block';
        });
    }
    function showFile(file) {
        var mb = (file.size / (1024 * 1024)).toFixed(1);
        fileName.textContent = file.name + ' (' + mb + ' MB)';
        selectedFile.style.display = 'flex';
        fileArea.style.display = 'none';
    }

    /* Delete video */
    document.querySelectorAll('[data-vr-delete]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this video? This cannot be undone.')) return;
            var body = new FormData();
            body.append('action', 'delete_video');
            body.append('video_id', btn.dataset.vrDelete);
            body.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            fetch('/process_video.php', { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) { vrToast('Video deleted', 'success'); setTimeout(function() { location.reload(); }, 800); }
                    else { vrToast(d.error || 'Delete failed', 'error'); }
                })
                .catch(function() { vrToast('Network error', 'error'); });
        });
    });

    /* Review modal */
    var reviewModal = document.getElementById('vrReviewModal');
    if (reviewModal) {
        document.querySelectorAll('[data-vr-review]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('vrReviewVideoId').value = btn.dataset.vrReview;
                document.getElementById('vrReviewInfo').textContent =
                    (btn.dataset.vrAthlete ? btn.dataset.vrAthlete + ' — ' : '') + btn.dataset.vrTitle;
                document.getElementById('vrReviewNotes').value = '';
                reviewModal.classList.add('vr-modal-open');
            });
        });
        document.getElementById('vrCloseModal').addEventListener('click', function() {
            reviewModal.classList.remove('vr-modal-open');
        });
        reviewModal.addEventListener('click', function(e) {
            if (e.target === reviewModal) reviewModal.classList.remove('vr-modal-open');
        });
    }

    /* Keyboard close */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && reviewModal) reviewModal.classList.remove('vr-modal-open');
    });
});
</script>
