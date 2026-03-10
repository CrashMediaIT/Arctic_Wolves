<?php
/**
 * Coach Review Tab - Video Upload and Review
 * Athletes can upload videos (game or drill) for coach review
 * Coaches can review, add notes, and mark videos as reviewed
 */
require_once __DIR__ . '/../lib/image_helper.php';

// Get the current user's assigned coach
$assigned_coach_id = null;
$assigned_coach_name = '';
if ($user_role === 'athlete') {
    $coach_stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
    $coach_stmt->execute([$user_id]);
    $coach_row = $coach_stmt->fetch();
    $assigned_coach_id = $coach_row['assigned_coach_id'] ?? null;
    
    if ($assigned_coach_id) {
        $coach_name_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $coach_name_stmt->execute([$assigned_coach_id]);
        $coach_name_row = $coach_name_stmt->fetch();
        if ($coach_name_row) {
            $coach_name_row = decryptUserRow($coach_name_row);
            $assigned_coach_name = $coach_name_row['first_name'] . ' ' . $coach_name_row['last_name'];
        }
    }
}

// Get user's teams from profile settings
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
} catch (PDOException $e) {
    error_log("Failed to load user teams: " . $e->getMessage());
}

// Get athletes for coach (if user is a coach)
$athletes = [];
if ($isAnyCoach) {
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
}

// Get filter parameters
$filter_athlete = $_GET['filter_athlete'] ?? 'all';
$filter_period = $_GET['filter_period'] ?? 'all';
$filter_category = $_GET['filter_category'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query for videos based on user role
if ($isAnyCoach) {
    $video_query = "
        SELECT v.*, 
               a.first_name as athlete_first_name, a.last_name as athlete_last_name,
               a.email as athlete_email,
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               d.title as drill_title,
               s.title as session_title,
               s.session_date
        FROM videos v
        LEFT JOIN users a ON v.athlete_id = a.id
        LEFT JOIN users c ON v.coach_id = c.id
        LEFT JOIN drills d ON v.drill_id = d.id
        LEFT JOIN sessions s ON v.session_id = s.id
        WHERE v.video_type = 'uploaded_by_athlete'
        AND (v.coach_id = ? OR a.assigned_coach_id = ? OR v.athlete_id = ?)
    ";
    $params = [$user_id, $user_id, $user_id];
} else {
    $video_query = "
        SELECT v.*, 
               a.first_name as athlete_first_name, a.last_name as athlete_last_name,
               a.email as athlete_email,
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               d.title as drill_title,
               s.title as session_title,
               s.session_date
        FROM videos v
        LEFT JOIN users a ON v.athlete_id = a.id
        LEFT JOIN users c ON v.coach_id = c.id
        LEFT JOIN drills d ON v.drill_id = d.id
        LEFT JOIN sessions s ON v.session_id = s.id
        WHERE v.athlete_id = ? AND v.video_type = 'uploaded_by_athlete'
    ";
    $params = [$user_id];
}

if ($filter_athlete !== 'all' && $isAnyCoach) {
    $video_query .= " AND v.athlete_id = ?";
    $params[] = $filter_athlete;
}

if ($filter_category !== 'all') {
    $video_query .= " AND v.video_category = ?";
    $params[] = $filter_category;
}

if ($filter_period === 'today') {
    $video_query .= " AND DATE(v.upload_date) = CURDATE()";
} elseif ($filter_period === 'week') {
    $video_query .= " AND v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($filter_period === 'month') {
    $video_query .= " AND v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}

if (!empty($search_query)) {
    $video_query .= " AND (v.title LIKE ? OR v.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$video_query .= " ORDER BY v.upload_date DESC LIMIT 50";

$video_stmt = $pdo->prepare($video_query);
$video_stmt->execute($params);
$videos = $video_stmt->fetchAll();
// Decrypt PII fields from joined user tables
foreach ($videos as &$v) {
    foreach (['athlete_first_name', 'athlete_last_name', 'coach_first_name', 'coach_last_name'] as $f) {
        if (!empty($v[$f])) $v[$f] = FieldEncryption::decrypt($v[$f]);
    }
}
unset($v);

$pending_videos = array_filter($videos, function($v) { 
    return $v['status'] === 'pending_review'; 
});
$reviewed_videos = array_filter($videos, function($v) { 
    return $v['status'] === 'reviewed'; 
});
?>

<div class="coach-video-content">
    <div class="section-intro">
        <h2 class="section-intro-title">
            <i class="fas fa-comments"></i> Coach Review
        </h2>
        <p class="section-intro-desc">
            <?php if ($isAnyCoach): ?>
                Review videos uploaded by athletes and provide feedback
            <?php else: ?>
                Upload videos for your coach to review and receive feedback
            <?php endif; ?>
        </p>
    </div>
    
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" data-action="switch-tab" data-tab="pending">
                <i class="fas fa-clock"></i> <span>Pending (<?= count($pending_videos) ?>)</span>
            </button>
            <button class="tab-btn" data-action="switch-tab" data-tab="reviewed">
                <i class="fas fa-check-circle"></i> <span>Completed (<?= count($reviewed_videos) ?>)</span>
            </button>
            <button class="tab-btn" data-action="switch-tab" data-tab="upload">
                <i class="fas fa-upload"></i> <span>Upload</span>
            </button>
            <button class="tab-btn" data-action="switch-tab" data-tab="ingest">
                <i class="fas fa-hard-drive"></i> <span>Ingest Device</span>
            </button>
        </div>
        
        <form method="GET" action="" class="filter-group tabs-filters">
            <input type="hidden" name="page" value="coaches_reviews">
            
            <div class="search-wrapper">
                <input type="text" name="search" class="form-input-small search-input" 
                       placeholder="Search by video name..." 
                       value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </div>
            
            <?php if ($isAnyCoach && !empty($athletes)): ?>
            <select name="filter_athlete" class="form-input-small" onchange="this.form.submit()">
                <option value="all">All Athletes</option>
                <?php foreach ($athletes as $athlete): ?>
                    <option value="<?= $athlete['id'] ?>" <?= $filter_athlete == $athlete['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <select name="filter_category" class="form-input-small" onchange="this.form.submit()">
                <option value="all" <?= $filter_category === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="drill" <?= $filter_category === 'drill' ? 'selected' : '' ?>>Drill</option>
                <option value="game" <?= $filter_category === 'game' ? 'selected' : '' ?>>Game</option>
            </select>
            
            <select name="filter_period" class="form-input-small" onchange="this.form.submit()">
                <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="today" <?= $filter_period === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
            </select>
        </form>
    </div>

    <div class="tab-content active" id="pending-tab">
        <div class="videos-list">
            <h3 class="section-title">Pending Reviews (<?= count($pending_videos) ?>)</h3>
            
            <?php if (count($pending_videos) > 0): ?>
                <?php foreach ($pending_videos as $video): ?>
                <div class="video-list-item" data-video-id="<?= $video['id'] ?>" data-detail-url="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews">
                    <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews" class="video-thumbnail-small" style="text-decoration:none;">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-video"></i>
                        <?php endif; ?>
                        <span class="play-overlay"><i class="fas fa-play"></i></span>
                    </a>
                    <div class="video-details">
                        <h4><a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews" style="color:inherit; text-decoration:none;"><?= htmlspecialchars($video['title']) ?></a></h4>
                        <?php if (!empty($video['description'])): ?>
                            <div class="athlete-notes-preview">
                                <i class="fas fa-comment-alt"></i> <?= htmlspecialchars(mb_strimwidth($video['description'], 0, 150, '...')) ?>
                            </div>
                        <?php endif; ?>
                        <div class="video-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                            <span class="video-category-badge <?= ($video['video_category'] ?? 'drill') === 'game' ? 'badge-game' : 'badge-drill' ?>">
                                <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                                <?= ucfirst($video['video_category'] ?? 'drill') ?>
                            </span>
                            <?php if (($video['video_category'] ?? '') === 'game' && !empty($video['opponent_team'])): ?>
                                <span><i class="fas fa-users"></i> vs <?= htmlspecialchars($video['opponent_team']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="video-status-badge">
                        <span class="badge-warning"><i class="fas fa-clock"></i> Pending</span>
                    </div>
                    <div class="video-actions-inline">
                        <?php
                            $vcr_video_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($video)) ?? '';
                            $vcr_hls_url = '';
                            if (preg_match('/\.m3u8(\?|&|$)/i', $vcr_video_url)) {
                                $orig = resolveRustfsUrl($pdo, $video['video_url'] ?? $video['file_path'] ?? '') ?? '';
                                if ($orig && $orig !== $vcr_video_url) $vcr_hls_url = $orig;
                            } else {
                                if (!empty($video['hls_url'])) {
                                    $hls = resolveRustfsUrl($pdo, $video['hls_url']) ?? '';
                                    if ($hls && $hls !== $vcr_video_url) $vcr_hls_url = $hls;
                                }
                                if (empty($vcr_hls_url)) $vcr_hls_url = deriveFallbackUrl($vcr_video_url);
                            }
                            $vcr_thumb_url = resolveRustfsUrl($pdo, $video['thumbnail_url'] ?? '') ?? '';
                            $vcr_dash_url = getDashUrl($video);
                            if ($vcr_dash_url) $vcr_dash_url = resolveRustfsUrl($pdo, $vcr_dash_url) ?? '';
                        ?>
                        <button class="btn-icon" title="Watch Video"
                            data-action="view-video"
                            data-video-id="<?= $video['id'] ?>"
                            data-video-url="<?= htmlspecialchars($vcr_video_url) ?>"
                            <?php if (!empty($vcr_hls_url)): ?>data-fallback-url="<?= htmlspecialchars($vcr_hls_url) ?>"<?php endif; ?>
                            <?php if (!empty($vcr_dash_url)): ?>data-dash-url="<?= htmlspecialchars($vcr_dash_url) ?>"<?php endif; ?>
                            data-thumbnail-url="<?= htmlspecialchars($vcr_thumb_url) ?>"><i class="fas fa-play"></i></button>
                        <?php if ($isAnyCoach): ?>
                        <button class="btn-icon btn-review" title="Review" data-action="review-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-check"></i></button>
                        <?php endif; ?>
                        <button class="btn-icon" title="Delete" data-action="delete-video" data-video-id="<?= $video['id'] ?>" data-video-title="<?= htmlspecialchars($video['title']) ?>"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="placeholder-container">
                    <i class="fas fa-clock placeholder-icon"></i>
                    <p class="placeholder-text">
                        <?php if ($isAnyCoach): ?>
                            No videos pending review.
                        <?php else: ?>
                            No pending reviews. Upload a video for your coach to review!
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-content" id="reviewed-tab">
        <div class="videos-list">
            <h3 class="section-title">Reviewed Videos (<?= count($reviewed_videos) ?>)</h3>
            
            <?php if (count($reviewed_videos) > 0): ?>
                <?php foreach ($reviewed_videos as $video): ?>
                <div class="video-list-item" data-video-id="<?= $video['id'] ?>" data-detail-url="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews">
                    <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews" class="video-thumbnail-small" style="text-decoration:none;">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $video['thumbnail_url']) ?? '') ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-video"></i>
                        <?php endif; ?>
                        <span class="play-overlay"><i class="fas fa-play"></i></span>
                    </a>
                    <div class="video-details">
                        <h4><a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews" style="color:inherit; text-decoration:none;"><?= htmlspecialchars($video['title']) ?></a></h4>
                        <?php if (!empty($video['description'])): ?>
                            <div class="athlete-notes-preview">
                                <i class="fas fa-comment-alt"></i> <?= htmlspecialchars(mb_strimwidth($video['description'], 0, 150, '...')) ?>
                            </div>
                        <?php endif; ?>
                        <div class="video-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars(($video['athlete_first_name'] ?? '') . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                            <span class="video-category-badge <?= ($video['video_category'] ?? 'drill') === 'game' ? 'badge-game' : 'badge-drill' ?>">
                                <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                                <?= ucfirst($video['video_category'] ?? 'drill') ?>
                            </span>
                            <?php if (!empty($video['coach_first_name'])): ?>
                                <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($video['coach_first_name'] . ' ' . ($video['coach_last_name'] ?? '')) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($video['coach_notes'])): ?>
                            <div class="coach-notes-preview">
                                <i class="fas fa-comment"></i> <?= htmlspecialchars(substr($video['coach_notes'], 0, 100)) ?><?= strlen($video['coach_notes']) > 100 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="video-status-badge">
                        <span class="badge-success"><i class="fas fa-check-circle"></i> Reviewed</span>
                    </div>
                    <div class="video-actions-inline">
                        <?php
                            $vcr_video_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($video)) ?? '';
                            $vcr_hls_url = '';
                            if (preg_match('/\.m3u8(\?|&|$)/i', $vcr_video_url)) {
                                $orig = resolveRustfsUrl($pdo, $video['video_url'] ?? $video['file_path'] ?? '') ?? '';
                                if ($orig && $orig !== $vcr_video_url) $vcr_hls_url = $orig;
                            } else {
                                if (!empty($video['hls_url'])) {
                                    $hls = resolveRustfsUrl($pdo, $video['hls_url']) ?? '';
                                    if ($hls && $hls !== $vcr_video_url) $vcr_hls_url = $hls;
                                }
                                if (empty($vcr_hls_url)) $vcr_hls_url = deriveFallbackUrl($vcr_video_url);
                            }
                            $vcr_thumb_url = resolveRustfsUrl($pdo, $video['thumbnail_url'] ?? '') ?? '';
                            $vcr_dash_url = getDashUrl($video);
                            if ($vcr_dash_url) $vcr_dash_url = resolveRustfsUrl($pdo, $vcr_dash_url) ?? '';
                        ?>
                        <button class="btn-icon" title="Watch Video"
                            data-action="view-video"
                            data-video-id="<?= $video['id'] ?>"
                            data-video-url="<?= htmlspecialchars($vcr_video_url) ?>"
                            <?php if (!empty($vcr_hls_url)): ?>data-fallback-url="<?= htmlspecialchars($vcr_hls_url) ?>"<?php endif; ?>
                            <?php if (!empty($vcr_dash_url)): ?>data-dash-url="<?= htmlspecialchars($vcr_dash_url) ?>"<?php endif; ?>
                            data-thumbnail-url="<?= htmlspecialchars($vcr_thumb_url) ?>"><i class="fas fa-play"></i></button>
                        <a href="?page=video_review_detail&video_id=<?= $video['id'] ?>&from=coaches_reviews" class="btn-icon" title="View Details"><i class="fas fa-comments"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="placeholder-container">
                    <i class="fas fa-check-circle placeholder-icon"></i>
                    <p class="placeholder-text">No reviewed videos yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-content" id="upload-tab">
        <div class="upload-card">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload Video for Coach Review</h3>
            
            <?php if (!$isAnyCoach && !$assigned_coach_id): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>You don't have an assigned coach yet. Videos will be uploaded and can be assigned for review later.</span>
            </div>
            <?php endif; ?>
            
            <form class="upload-form" method="POST" action="process_video.php" enctype="multipart/form-data" data-form="video-upload">
                <?= csrfTokenInput() ?>
                <?php if ($assigned_coach_id): ?>
                <input type="hidden" name="coach_id" value="<?= $assigned_coach_id ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Video Title *</label>
                        <input type="text" name="title" class="form-input" placeholder="e.g., Power Play Practice" required>
                    </div>
                    <div class="form-group">
                        <label>Video Type *</label>
                        <select name="video_category" class="form-input" required id="videoCategorySelect">
                            <option value="">-- Select Type --</option>
                            <option value="drill">Drill / Practice</option>
                            <option value="game">Game Footage</option>
                        </select>
                    </div>
                </div>

                <div id="gameFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Game Date *</label>
                            <input type="date" name="game_date" class="form-input" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Your Team *</label>
                            <select name="team_played_on" class="form-input">
                                <option value="">-- Select Your Team --</option>
                                <?php if (!empty($user_teams)): ?>
                                    <?php foreach ($user_teams as $team): ?>
                                        <option value="<?= htmlspecialchars($team['team_name']) ?>">
                                            <?= htmlspecialchars($team['team_name']) ?>
                                            <?= !empty($team['league']) ? ' (' . htmlspecialchars($team['league']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No teams - add in profile</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Opponent Team *</label>
                        <input type="text" name="opponent_team" class="form-input" placeholder="e.g., Thunder Bay Kings">
                    </div>
                </div>

                <div id="drillFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Drill Type</label>
                            <select name="drill_type" class="form-input">
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
                        <div class="form-group">
                            <label>Practice Date</label>
                            <input type="date" name="session_date" class="form-input" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Auto-assign video to current user (no athlete selector needed) -->
                <input type="hidden" name="athlete_id" value="<?= $user_id ?>">

                <div class="form-group">
                    <label>Video File *</label>
                    <div class="file-upload-area" data-component="FileUpload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop video file here or click to browse</p>
                        <p class="file-hint">Supported: MP4, MKV, MOV, AVI, WebM (Max 10GB)</p>
                        <p class="file-name" style="display: none;"></p>
                        <input type="file" name="video_file" accept="video/*" style="display: none;" required data-field="video-file">
                        <button type="button" class="btn-secondary" data-action="trigger-file-input">Choose File</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes for Coach *</label>
                    <textarea name="description" class="form-textarea" rows="4" required placeholder="Describe what you'd like feedback on..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" data-action="cancel">Cancel</button>
                    <button type="submit" class="btn-primary" id="uploadSubmitBtn"><i class="fas fa-upload"></i> Upload for Review</button>
                </div>

                <!-- Upload Progress Overlay -->
                <div id="uploadProgressOverlay" class="upload-progress-overlay" style="display: none;">
                    <div class="upload-progress-card">
                        <div class="spinner" id="crUploadSpinner"></div>
                        <h4 id="crUploadTitle">Uploading Video...</h4>
                        <p class="upload-progress-text" id="crUploadSubtext">Uploading your video for coach review. Please do not close this page.</p>
                        <div class="upload-progress-bar-container">
                            <div class="upload-progress-bar" id="uploadProgressBar"></div>
                        </div>
                        <span class="upload-progress-percent" id="uploadProgressPercent">0%</span>
                        <span class="upload-progress-status" id="uploadProgressStatus">Preparing upload...</span>
                        <!-- Upload Log Dropdown -->
                        <details id="crUploadLogDetails" style="width:100%;margin-top:12px;text-align:left;">
                            <summary style="cursor:pointer;font-weight:600;font-size:13px;color:var(--text-dim,#6b7280);user-select:none;">
                                <i class="fas fa-terminal"></i> Upload Log
                            </summary>
                            <pre id="crUploadLogPre" style="margin-top:6px;max-height:200px;overflow:auto;background:var(--bg-main,#0a0a0f);color:#cdd6f4;padding:10px;border-radius:6px;font-size:11px;white-space:pre-wrap;line-height:1.5;"></pre>
                        </details>
                        <button type="button" class="btn btn-danger" id="crCancelUploadBtn" style="margin-top: 16px;">
                            <i class="fas fa-times"></i> Cancel Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Ingest Device Tab -->
    <div class="tab-content" id="ingest-tab">
        <div class="upload-card">
            <h3><i class="fas fa-hard-drive"></i> Ingest Videos from Device</h3>
            <p class="ingest-description">Import videos recorded offline from an SD card, USB drive, or other external storage device. Videos are automatically assigned to the correct area based on the recording metadata.</p>

            <div id="ingestFsaNotSupported" style="display:none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Your browser does not support the File System Access API. Please use Chrome, Edge, or Opera to ingest videos from external devices. You can also use the standard Upload tab to add video files manually.</span>
                </div>
            </div>

            <div id="ingestFsaSupported">
                <div class="ingest-step" id="ingestStep1">
                    <div class="ingest-step-number">1</div>
                    <div class="ingest-step-content">
                        <h4>Select Device / Folder</h4>
                        <p>Connect your SD card or external drive, then select the folder containing the recordings.</p>
                        <button type="button" class="btn btn-primary" id="ingestSelectDirBtn">
                            <i class="fas fa-folder-open"></i> Select Recording Folder
                        </button>
                    </div>
                </div>

                <div class="ingest-step" id="ingestStep2" style="display:none;">
                    <div class="ingest-step-number">2</div>
                    <div class="ingest-step-content">
                        <h4>Review Discovered Videos</h4>
                        <p id="ingestScanStatus">Scanning for videos…</p>
                        <div class="ingest-video-list" id="ingestVideoList"></div>
                        <div class="ingest-actions" id="ingestActions" style="display:none;">
                            <label class="ingest-checkbox-label">
                                <input type="checkbox" id="ingestDeleteAfter" checked>
                                <span>Remove files from device after import</span>
                            </label>
                            <div class="ingest-action-buttons">
                                <button type="button" class="btn btn-secondary" id="ingestCancelBtn">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-primary" id="ingestStartBtn">
                                    <i class="fas fa-download"></i> Import &amp; Upload All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ingest-step" id="ingestStep3" style="display:none;">
                    <div class="ingest-step-number">3</div>
                    <div class="ingest-step-content">
                        <h4>Import &amp; Upload Progress</h4>
                        <div class="ingest-progress-header">
                            <span id="ingestProgressTitle">Importing…</span>
                            <span id="ingestProgressCount">0 / 0</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="ingestProgressFill"></div>
                        </div>
                        <p id="ingestProgressStatus" class="ingest-progress-status"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isAnyCoach): ?>
<div class="modal" id="reviewModal" style="display: none;">
    <div class="modal-overlay" data-action="close-modal"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> Review Video</h3>
            <button class="modal-close" aria-label="Close modal" data-action="close-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="reviewForm" method="POST" action="process_video.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="review_video">
                <input type="hidden" name="video_id" id="reviewVideoId">
                
                <div class="form-group">
                    <label>Review Notes *</label>
                    <textarea name="coach_notes" class="form-textarea" rows="6" required
                              placeholder="Provide detailed feedback..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Video Player Modal -->
<div class="modal" id="coachVideoPlayerModal" style="display: none;">
    <div class="modal-overlay" data-action="close-video-modal"></div>
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header">
            <h3 id="coachVideoModalTitle"><i class="fas fa-play-circle"></i> Video Player</h3>
            <button class="modal-close" aria-label="Close" data-action="close-video-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="position: relative; background: #000; border-radius: 8px; overflow: hidden; aspect-ratio: 16 / 9;">
                <video id="coachVideoPlayer" controls preload="none" style="width: 100%; height: 100%; display: block; object-fit: contain;">
                    <source src="" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal" style="display: none;">
    <div class="modal-overlay" data-action="close-modal"></div>
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3><i class="fas fa-trash" style="color: #EF4444;"></i> Delete Video</h3>
            <button class="modal-close" aria-label="Close modal" data-action="close-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-dim); margin-bottom: 16px;">Are you sure you want to delete <strong id="deleteVideoTitle" style="color: var(--text-white);"></strong>? This action cannot be undone.</p>
            <input type="hidden" id="deleteVideoId">
            <div class="form-actions">
                <button type="button" class="btn-secondary" data-action="close-modal">Cancel</button>
                <button type="button" class="btn-primary" id="confirmDeleteBtn" style="background: #EF4444;"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>
</div>

<style>
.tab-content { display: none; }
.tab-content.active { display: block; animation: fadeInUp 0.4s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

.coach-video-content { max-width: 1400px; margin: 0 auto; padding: 0 16px; }

.section-intro { margin-bottom: 24px; }
.section-intro-title { font-size: 20px; font-weight: 700; color: var(--text-white); display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.section-intro-title i { color: var(--primary); }
.section-intro-desc { color: var(--text-dim); font-size: 14px; }

.tabs-container { background: linear-gradient(135deg, var(--bg-card) 0%, rgba(107, 70, 193, 0.08) 100%); border: 1px solid var(--border); border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
.tabs-nav { display: flex; gap: 4px; flex-wrap: wrap; background: rgba(10, 10, 15, 0.6); padding: 6px; border-radius: 12px; border: 1px solid rgba(45, 45, 63, 0.5); }
.tab-btn { padding: 12px 20px; background: transparent; border: none; color: var(--text-dim); border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.25s ease; display: flex; align-items: center; gap: 8px; }
.tab-btn:hover { color: var(--text-white); background: rgba(107, 70, 193, 0.15); }
.tab-btn.active { color: white; background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6)); }

.tabs-filters { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.search-wrapper { display: flex; }
.search-input { border-radius: 8px 0 0 8px !important; min-width: 200px; }
.search-btn { padding: 0 16px; height: 42px; background: var(--primary); border: none; border-radius: 0 8px 8px 0; color: white; cursor: pointer; }
.form-input-small { min-width: 140px; height: 42px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white); font-size: 14px; padding: 0 12px; }

.videos-list { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; }
.section-title { font-size: 18px; font-weight: 700; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; color: var(--text-white); }
.section-title::before { content: ''; width: 4px; height: 24px; background: linear-gradient(180deg, var(--primary), var(--accent)); border-radius: 2px; }

.video-list-item { display: grid; grid-template-columns: 120px 1fr auto auto; align-items: center; gap: 20px; padding: 16px 20px; background: linear-gradient(135deg, var(--bg-main) 0%, rgba(22, 22, 31, 0.8) 100%); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; transition: all 0.3s ease; cursor: pointer; }
.video-list-item:hover { border-color: var(--primary); transform: translateY(-2px); }

.video-thumbnail-small { width: 120px; height: 80px; background: linear-gradient(135deg, rgba(107, 70, 193, 0.15), rgba(139, 92, 246, 0.1)); border-radius: 10px; display: flex; align-items: center; justify-content: center; position: relative; border: 1px solid rgba(107, 70, 193, 0.2); }
.video-thumbnail-small i { font-size: 28px; color: var(--primary); opacity: 0.5; }
.video-thumbnail-small img { width: 100%; height: 100%; object-fit: cover; }
.video-thumbnail-small .play-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 36px; height: 36px; background: rgba(107, 70, 193, 0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0; transition: all 0.3s ease; }
.video-thumbnail-small .play-overlay i { font-size: 14px; color: white; opacity: 1; margin-left: 2px; }
.video-list-item:hover .video-thumbnail-small .play-overlay { opacity: 1; }

.video-details { flex: 1; min-width: 0; }
.video-details h4 { font-size: 15px; font-weight: 700; color: var(--text-white); margin-bottom: 8px; }
.video-meta { display: flex; flex-wrap: wrap; gap: 16px; }
.video-meta span { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-dim); }
.video-meta i { color: var(--primary); font-size: 12px; }

.video-category-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-drill { background: rgba(107, 70, 193, 0.15); color: var(--primary); }
.badge-game { background: rgba(16, 185, 129, 0.15); color: #10B981; }

.coach-notes-preview { margin-top: 10px; padding: 8px 12px; background: rgba(107, 70, 193, 0.1); border-radius: 8px; font-size: 13px; color: var(--text-dim); display: flex; align-items: flex-start; gap: 8px; }
.athlete-notes-preview { margin: 6px 0 8px; padding: 8px 12px; background: rgba(59, 130, 246, 0.08); border-left: 3px solid rgba(59, 130, 246, 0.4); border-radius: 4px; font-size: 13px; color: var(--text-dim); display: flex; align-items: flex-start; gap: 8px; line-height: 1.5; }
.athlete-notes-preview i { color: #3b82f6; margin-top: 2px; flex-shrink: 0; }
.coach-notes-preview i { color: var(--primary); margin-top: 2px; }

.badge-success, .badge-warning { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-success { background: rgba(16, 185, 129, 0.12); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.25); }
.badge-warning { background: rgba(245, 158, 11, 0.12); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.25); }

.video-actions-inline { display: flex; gap: 8px; }
.btn-icon { width: 38px; height: 38px; background: rgba(22, 22, 31, 0.8); border: 1px solid var(--border); color: var(--text-dim); border-radius: 8px; cursor: pointer; transition: all 0.25s ease; display: flex; align-items: center; justify-content: center; }
.btn-icon:hover { background: var(--primary); border-color: var(--primary); color: white; }
.btn-icon.btn-review:hover { background: #10B981; border-color: #10B981; }
.btn-icon[title="Delete"]:hover { background: #EF4444; border-color: #EF4444; }

.upload-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; }
.upload-card h3 { font-size: 20px; font-weight: 700; margin-bottom: 28px; display: flex; align-items: center; gap: 12px; color: var(--text-white); }
.upload-card h3 i { color: var(--primary); }

.alert { padding: 16px 20px; border-radius: 12px; display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
.alert-warning { background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.25); color: #F59E0B; }

.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 20px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-dim); margin-bottom: 8px; text-transform: uppercase; }
.form-input, .form-textarea { width: 100%; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white); font-size: 14px; padding: 12px 16px; }
.form-input:focus, .form-textarea:focus { border-color: var(--primary); outline: none; }

.file-upload-area { border: 2px dashed var(--border); border-radius: 12px; padding: 48px 32px; text-align: center; background: linear-gradient(135deg, var(--bg-main) 0%, rgba(107, 70, 193, 0.03) 100%); transition: all 0.3s ease; cursor: pointer; }
.file-upload-area:hover { border-color: var(--primary); }
.file-upload-area i { font-size: 52px; color: var(--primary); opacity: 0.5; display: block; margin-bottom: 16px; }
.file-upload-area p { color: var(--text-dim); margin-bottom: 8px; }
.file-hint { font-size: 12px; opacity: 0.7; }

.form-actions { display: flex; justify-content: flex-end; gap: 12px; padding-top: 24px; border-top: 1px solid var(--border); margin-top: 28px; }
.btn-primary, .btn-secondary { padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.25s ease; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6)); border: none; color: white; }
.btn-secondary { background: transparent; border: 1px solid var(--border); color: var(--text-white); }

.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; display: flex; align-items: center; justify-content: center; }
.modal-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); }
.modal-content { position: relative; width: 90%; max-width: 600px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; color: var(--text-white); }
.modal-header h3 i { color: var(--primary); }
.modal-close { width: 40px; height: 40px; background: transparent; border: 1px solid var(--border); color: var(--text-white); border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { background: var(--primary); border-color: var(--primary); }
.modal-body { padding: 24px; max-height: calc(90vh - 80px); overflow-y: auto; }

.placeholder-container { text-align: center; padding: 60px 24px; }
.placeholder-icon { font-size: 64px; color: var(--primary); opacity: 0.25; margin-bottom: 20px; display: block; }
.placeholder-text { font-size: 15px; color: var(--text-dim); max-width: 400px; margin: 0 auto; }

@media (max-width: 992px) {
    .video-list-item { grid-template-columns: 100px 1fr; }
    .video-thumbnail-small { width: 100px; height: 68px; }
}

@media (max-width: 768px) {
    .tabs-container { flex-direction: column; align-items: stretch; padding: 16px; }
    .tabs-filters { flex-direction: column; width: 100%; }
    .search-wrapper { width: 100%; }
    .form-input-small { width: 100%; }
    .video-list-item { grid-template-columns: 1fr; text-align: center; }
    .video-thumbnail-small { width: 100%; height: 120px; margin-bottom: 12px; }
}

@media (max-width: 480px) {
    .tab-btn span { display: none; }
}

/* Upload Progress Overlay */
.upload-progress-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.upload-progress-card {
    background: var(--bg-card, #0d1117);
    border: 1px solid var(--border, #1e293b);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    max-width: 420px;
    width: 90%;
}

.upload-progress-card .spinner {
    width: 36px;
    height: 36px;
    margin: 0 auto 16px;
    border: 3px solid var(--border, #1e293b);
    border-top-color: var(--primary, #7c3aed);
    border-radius: 50%;
    animation: upload-spin 0.8s linear infinite;
}

@keyframes upload-spin {
    to { transform: rotate(360deg); }
}

.upload-progress-card h4 {
    color: var(--text-white, #fff);
    font-size: 18px;
    margin-bottom: 8px;
}

.upload-progress-text {
    color: var(--text-dim, #64748b);
    font-size: 13px;
    margin-bottom: 20px;
}

.upload-progress-bar-container {
    width: 100%;
    height: 8px;
    background: var(--bg-main, #06080b);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.upload-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--primary, #7c3aed), #a78bfa);
    border-radius: 4px;
    transition: width 0.4s ease;
}

.upload-progress-percent {
    display: block;
    color: var(--text-white, #fff);
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 4px;
}

.upload-progress-status {
    color: var(--text-dim, #64748b);
    font-size: 12px;
}

/* Ingest Device Tab Styles */
.ingest-description { color: var(--text-dim); font-size: 14px; margin-bottom: 24px; line-height: 1.6; }
.ingest-step { display: flex; gap: 16px; margin-bottom: 24px; padding: 20px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; }
.ingest-step-number { width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: white; flex-shrink: 0; }
.ingest-step-content { flex: 1; }
.ingest-step-content h4 { font-size: 15px; font-weight: 700; color: var(--text-white); margin-bottom: 6px; }
.ingest-step-content > p { color: var(--text-dim); font-size: 13px; margin-bottom: 12px; }
.ingest-video-list { max-height: 300px; overflow-y: auto; margin: 12px 0; }
.ingest-video-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 6px; background: var(--bg-card); }
.ingest-video-item i { color: var(--primary); font-size: 18px; }
.ingest-video-item .ingest-video-info { flex: 1; }
.ingest-video-item .ingest-video-info strong { display: block; color: var(--text-white); font-size: 13px; }
.ingest-video-item .ingest-video-info small { color: var(--text-dim); font-size: 11px; }
.ingest-checkbox-label { display: flex; align-items: center; gap: 8px; color: var(--text-dim); font-size: 13px; margin-bottom: 12px; cursor: pointer; }
.ingest-action-buttons { display: flex; gap: 8px; justify-content: flex-end; }
.ingest-progress-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; color: var(--text-white); }
.ingest-progress-status { color: var(--text-dim); font-size: 12px; margin-top: 8px; }
.offline-upload-banner { background: linear-gradient(135deg, rgba(107, 70, 193, 0.12), rgba(59, 130, 246, 0.08)); border: 1px solid rgba(107, 70, 193, 0.3); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
.offline-upload-banner-content { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.offline-upload-banner-icon { font-size: 24px; color: var(--primary); }
.offline-upload-banner-text { flex: 1; }
.offline-upload-banner-text strong { display: block; color: var(--text-white); font-size: 14px; }
.offline-upload-banner-text span { color: var(--text-dim); font-size: 13px; }
.offline-upload-banner-actions { display: flex; gap: 8px; }
</style>

<script src="js/offline-upload-queue.js"></script>
<script src="js/video-thumbnail.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make entire video-list-item cards clickable to navigate to detail view.
    // Clicks on buttons, links, or other interactive elements inside the card
    // are left alone so actions like play, review, and delete still work.
    document.querySelectorAll('.video-list-item[data-detail-url]').forEach(function(card) {
        card.addEventListener('click', function(e) {
            if (e.target.closest('a, button, [data-action]')) return;
            window.location.href = card.dataset.detailUrl;
        });
    });

    // Video player modal
    var vpModal = document.getElementById('coachVideoPlayerModal');
    var vpVideo = document.getElementById('coachVideoPlayer');
    var vpTitle = document.getElementById('coachVideoModalTitle');
    var vpHls = null;
    var vpFallbackUrl = '';
    var vpFallbackTried = false;
    var vpPrimaryUrl = '';   // stash primary URL for reload-on-no-fallback

    function cleanupCoachVideoPlayer() {
        if (vpHls) { vpHls.destroy(); vpHls = null; }
        if (vpVideo && vpVideo._awDash) { try { vpVideo._awDash.reset(); } catch(e){} vpVideo._awDash = null; }
        vpFallbackUrl = '';
        vpFallbackTried = false;
        vpPrimaryUrl = '';
        if (vpVideo) {
            var container = vpVideo.parentElement;
            if (container) {
                var ctrl = container.querySelector('.aw-player-controls');
                if (ctrl) { if (ctrl._cleanup) ctrl._cleanup(); ctrl.remove(); }
                var grad = container.querySelector('.aw-controls-gradient');
                if (grad) grad.remove();
                var bp = container.querySelector('.aw-big-play');
                if (bp) bp.remove();
                var tzl = container.querySelector('.aw-touch-zone-left');
                if (tzl) tzl.remove();
                var tzr = container.querySelector('.aw-touch-zone-right');
                if (tzr) tzr.remove();
                var tzc = container.querySelector('.aw-touch-zone-center');
                if (tzc) tzc.remove();
            }
            vpVideo.pause();
            vpVideo.removeAttribute('src');
            vpVideo.removeAttribute('poster');
            vpVideo.setAttribute('controls', '');
        }
    }

    // Fallback: if primary video source fails, try the alternate URL.
    // After transcoding, HLS is the primary stream and the original is
    // deleted.  If HLS itself fails, there may be no fallback available.
    // When no fallback exists, try reloading the primary HLS URL once
    // (the failure may have been transient, e.g. a temporary buffer error).
    var vpReloadTried = false;
    if (vpVideo) {
        vpVideo.addEventListener('error', function(e) {
            // Ignore errors from <source> child elements — when HLS.js manages
            // playback via MSE the native <source> fires a spurious error because
            // the browser cannot play .m3u8 natively.
            if (e && e.target !== vpVideo) return;
            var diagState = { primaryUrl: vpPrimaryUrl, fallbackUrl: vpFallbackUrl, fallbackTried: vpFallbackTried, reloadTried: vpReloadTried };
            if (vpFallbackUrl && !vpFallbackTried) {
                vpFallbackTried = true;
                if (typeof window.awReportPlaybackError === 'function') {
                    window.awReportPlaybackError('Coach reviews: primary source failed, trying fallback', { view: 'coach_reviews', action: 'try_fallback', fallback: vpFallbackUrl, state: diagState });
                }
                if (typeof window.awInitHlsPlayer === 'function') {
                    vpHls = window.awInitHlsPlayer(vpVideo, vpFallbackUrl);
                }
            } else if (!vpFallbackUrl && vpPrimaryUrl && !vpReloadTried) {
                vpReloadTried = true;
                if (typeof window.awReportPlaybackError === 'function') {
                    window.awReportPlaybackError('Coach reviews: no fallback URL, reloading primary HLS stream', { view: 'coach_reviews', action: 'reload_primary', primary: vpPrimaryUrl, state: diagState });
                }
                if (typeof window.awInitHlsPlayer === 'function') {
                    vpHls = window.awInitHlsPlayer(vpVideo, vpPrimaryUrl);
                }
            } else if (typeof window.awTryDashFallback === 'function' && vpVideo.getAttribute('data-dash-url') && !vpVideo._dashTried) {
                vpVideo._dashTried = true;
                if (typeof window.awReportPlaybackError === 'function') {
                    window.awReportPlaybackError('Coach reviews: HLS recovery exhausted, trying DASH fallback', { view: 'coach_reviews', action: 'try_dash', state: diagState });
                }
                window.awTryDashFallback(vpVideo);
            } else if (typeof window.awReportPlaybackError === 'function') {
                window.awReportPlaybackError('Coach reviews: video playback failed — all recovery exhausted', { view: 'coach_reviews', action: 'give_up', state: diagState });
            }
        }, true);
    }

    document.querySelectorAll('[data-action="view-video"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.dataset.videoUrl;
            var hlsUrl = this.dataset.fallbackUrl || '';
            var dashUrl = this.dataset.dashUrl || '';
            var thumbnailUrl = this.dataset.thumbnailUrl || '';
            var item = this.closest('.video-list-item');
            var title = item ? (item.querySelector('.video-details h4')?.textContent || 'Video') : 'Video';
            if (!vpModal) return;
            vpModal.style.display = 'flex';
            vpTitle.innerHTML = '<i class="fas fa-play-circle"></i> ' + title;
            cleanupCoachVideoPlayer();
            vpReloadTried = false;
            vpVideo._dashTried = false;
            // Store HLS fallback URL for error recovery
            vpFallbackUrl = (hlsUrl && hlsUrl !== url) ? hlsUrl : '';
            vpPrimaryUrl = url || '';
            vpFallbackTried = false;
            // Set DASH URL on video element for fallback
            if (dashUrl) { vpVideo.setAttribute('data-dash-url', dashUrl); }
            else { vpVideo.removeAttribute('data-dash-url'); }
            if (typeof window.awReportPlaybackError === 'function') {
                window.awReportPlaybackError('Coach reviews: play button clicked', { view: 'coach_reviews', action: 'play_click', primaryUrl: url, fallbackUrl: hlsUrl, title: title, type: 'lifecycle' });
            }
            if (thumbnailUrl) vpVideo.poster = thumbnailUrl;
            if (url && typeof window.awInitHlsPlayer === 'function') {
                vpHls = window.awInitHlsPlayer(vpVideo, url);
            } else if (url) {
                vpVideo.querySelector('source').src = url;
                vpVideo.load();
            }
        });
    });

    document.querySelectorAll('[data-action="close-video-modal"]').forEach(function(el) {
        el.addEventListener('click', function() {
            if (vpModal) vpModal.style.display = 'none';
            cleanupCoachVideoPlayer();
        });
    });

    document.querySelectorAll('[data-action="switch-tab"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
        });
    });
    
    const categorySelect = document.getElementById('videoCategorySelect');
    const gameFields = document.getElementById('gameFields');
    const drillFields = document.getElementById('drillFields');
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const gameInputs = gameFields ? gameFields.querySelectorAll('input, select') : [];
            const drillInputs = drillFields ? drillFields.querySelectorAll('input, select') : [];
            
            if (this.value === 'game') {
                gameFields.style.display = 'block';
                drillFields.style.display = 'none';
                // Make game fields required
                gameInputs.forEach(input => {
                    if (input.name === 'game_date' || input.name === 'opponent_team') {
                        input.required = true;
                    }
                });
                // Remove required from drill fields
                drillInputs.forEach(input => input.required = false);
            } else {
                gameFields.style.display = 'none';
                drillFields.style.display = 'block';
                // Remove required from game fields
                gameInputs.forEach(input => input.required = false);
            }
        });
    }
    
    const fileUploadArea = document.querySelector('.file-upload-area');
    const fileInput = document.querySelector('[data-field="video-file"]');
    const fileName = document.querySelector('.file-name');
    
    if (fileUploadArea && fileInput) {
        fileUploadArea.addEventListener('click', () => fileInput.click());
        document.querySelector('[data-action="trigger-file-input"]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.click();
        });
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileName.textContent = this.files[0].name;
                fileName.style.display = 'block';
            }
        });
    }
    
    document.querySelectorAll('[data-action="close-modal"]').forEach(el => {
        el.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });

    // ── Delete Video ──
    document.querySelectorAll('[data-action="delete-video"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var videoId = this.dataset.videoId;
            var videoTitle = this.dataset.videoTitle || 'this video';
            document.getElementById('deleteVideoId').value = videoId;
            document.getElementById('deleteVideoTitle').textContent = videoTitle;
            document.getElementById('deleteModal').style.display = 'flex';
        });
    });

    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            var videoId = document.getElementById('deleteVideoId').value;
            var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            var formData = new FormData();
            formData.append('action', 'delete_video');
            formData.append('video_id', videoId);
            formData.append('csrf_token', csrfToken);

            fetch('process_video.php', { method: 'POST', body: formData })
                .then(function(r) {
                    if (!r.ok) return r.text().then(function(b) { var m = 'HTTP ' + r.status; try { var j = JSON.parse(b); if (j.error) m = j.error; } catch(e) {} throw new Error(m); });
                    return r.json();
                })
                .then(function(data) {
                    if (data.success) {
                        document.getElementById('deleteModal').style.display = 'none';
                        var card = document.querySelector('.video-list-item[data-video-id="' + videoId + '"]');
                        if (card) card.remove();
                        if (typeof showToast === 'function') showToast('Video deleted successfully.', 'success');
                    } else {
                        if (typeof showToast === 'function') showToast('Delete failed: ' + (data.error || data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(function(err) {
                    if (typeof showToast === 'function') showToast('Delete failed: ' + err.message, 'error');
                })
                .finally(function() {
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
                });
        });
    }
    
    document.querySelectorAll('[data-action="review-video"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const videoId = this.dataset.videoId;
            const modal = document.getElementById('reviewModal');
            if (modal) {
                document.getElementById('reviewVideoId').value = videoId;
                modal.style.display = 'flex';
            }
        });
    });
    
    document.querySelectorAll('[data-action="cancel"]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelector('[data-tab="pending"]')?.click();
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
            cleanupCoachVideoPlayer();
        }
    });

    // Upload log helper
    var crLogPre = document.getElementById('crUploadLogPre');
    function crLog(msg) {
        if (crLogPre) { crLogPre.textContent += '[' + new Date().toLocaleTimeString() + '] ' + msg + '\n'; crLogPre.scrollTop = crLogPre.scrollHeight; }
        console.log('[Upload] ' + msg);
    }
    function crLogError(msg) { crLog('ERROR: ' + msg); }
    function crLogWarn(msg) { crLog('WARNING: ' + msg); }

    var MULTIPART_THRESHOLD = 64 * 1024 * 1024;
    var PART_SIZE = 64 * 1024 * 1024;
    var CONCURRENT_PARTS = 3;
    var MAX_PART_RETRIES = 5;
    var PROGRESS_LOG_INTERVAL = 10;

    function crElapsed(startMs) { return ((Date.now() - startMs) / 1000).toFixed(1) + 's'; }

    function crPostAction(params, csrfToken, options) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (var k in params) { if (params.hasOwnProperty(k)) fd.append(k, params[k]); }
        var fetchOpts = { method: 'POST', body: fd };
        if (options && options.keepalive) fetchOpts.keepalive = true;
        return fetch('process_video.php', fetchOpts)
            .then(function(r) {
                if (!r.ok) return r.text().then(function(b) { var m = 'HTTP ' + r.status; try { var j = JSON.parse(b); if (j.error) m += ': ' + j.error; } catch(e) {} throw new Error(m); });
                return r.json();
            })
            .then(function(d) { if (!d.success) throw new Error(d.error || 'Request failed'); return d; });
    }

    function crShowComplete(bar, percent, status, overlay, submitBtn, redirectUrl) {
        bar.style.width = '100%'; percent.textContent = '100%';
        status.textContent = 'Upload complete! Transcoding in background…';
        var t = document.getElementById('crUploadTitle');
        var s = document.getElementById('crUploadSubtext');
        var sp = document.getElementById('crUploadSpinner');
        var cb = document.getElementById('crCancelUploadBtn');
        if (t) t.textContent = 'Upload Complete!';
        if (s) s.textContent = 'Your video has been uploaded. Transcoding will happen in the background — you can leave this page.';
        if (sp) sp.style.display = 'none';
        if (cb) cb.style.display = 'none';
        var ld = document.getElementById('crUploadLogDetails');
        if (ld) ld.open = true;
        setTimeout(function() { window.location.href = redirectUrl || 'dashboard.php?page=coaches_reviews&success=video_uploaded'; }, 3000);
    }

    // Video upload — new multipart + presigned URL flow
    var uploadForm = document.querySelector('[data-form="video-upload"]');
    var currentUploadXhr = null;
    if (uploadForm) {
        var cancelBtn = document.getElementById('crCancelUploadBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (currentUploadXhr) { currentUploadXhr.abort(); currentUploadXhr = null; }
                document.getElementById('uploadProgressOverlay').style.display = 'none';
                document.getElementById('uploadSubmitBtn').disabled = false;
                showToast('Upload cancelled.', 'info');
            });
        }

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var overlay = document.getElementById('uploadProgressOverlay');
            var bar = document.getElementById('uploadProgressBar');
            var percent = document.getElementById('uploadProgressPercent');
            var status = document.getElementById('uploadProgressStatus');
            var submitBtn = document.getElementById('uploadSubmitBtn');
            var videoFile = uploadForm.querySelector('[data-field="video-file"]');

            if (!videoFile || !videoFile.files.length) {
                showToast('Please select a video file.', 'error');
                return;
            }

            var file = videoFile.files[0];
            overlay.style.display = 'flex';
            submitBtn.disabled = true;
            bar.style.width = '0%';
            percent.textContent = '0%';
            status.textContent = 'Requesting upload URL...';
            if (crLogPre) crLogPre.textContent = '';

            crLog('Selected file: ' + file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)');

            var csrfToken = uploadForm.querySelector('[name="csrf_token"]')?.value || '';
            var title = uploadForm.querySelector('[name="title"]')?.value || '';
            var videoCategory = uploadForm.querySelector('[name="video_category"]')?.value || 'drill';
            var description = uploadForm.querySelector('[name="description"]')?.value || '';
            var coachInput = uploadForm.querySelector('[name="coach_id"]');
            var coachId = (coachInput && coachInput.value) ? coachInput.value : '';
            var athleteInput = uploadForm.querySelector('[name="athlete_id"]');
            var athleteId = (athleteInput && athleteInput.value) ? athleteInput.value : '';
            var gd = uploadForm.querySelector('[name="game_date"]');
            var gameDate = (gd && gd.value) ? gd.value : '';
            var tp = uploadForm.querySelector('[name="team_played_on"]');
            var teamPlayedOn = (tp && tp.value) ? tp.value : '';
            var ot = uploadForm.querySelector('[name="opponent_team"]');
            var opponentTeam = (ot && ot.value) ? ot.value : '';

            if (file.size > MULTIPART_THRESHOLD) {
                crLog('File exceeds ' + (MULTIPART_THRESHOLD / 1048576) + ' MB — using multipart upload');
                crMultipartUpload(file, bar, percent, status, overlay, submitBtn, csrfToken,
                    title, videoCategory, description, coachId, athleteId, gameDate, teamPlayedOn, opponentTeam);
            } else {
                crSingleUpload(file, bar, percent, status, overlay, submitBtn, csrfToken,
                    title, videoCategory, description, coachId, athleteId, gameDate, teamPlayedOn, opponentTeam);
            }
        });
    }

    function crSingleUpload(file, bar, percent, status, overlay, submitBtn, csrfToken,
                            title, videoCategory, description, coachId, athleteId, gameDate, teamPlayedOn, opponentTeam) {
        var uploadStart = Date.now();
        var formMeta = new FormData();
        formMeta.append('action', 'get_video_upload_url');
        formMeta.append('upload_type', 'athlete_video');
        formMeta.append('csrf_token', csrfToken);
        formMeta.append('title', title);
        formMeta.append('video_category', videoCategory);
        formMeta.append('description', description);
        formMeta.append('file_name', file.name);
        formMeta.append('file_size', file.size);
        formMeta.append('file_type', file.type || 'video/mp4');
        if (coachId) formMeta.append('coach_id', coachId);
        if (athleteId) formMeta.append('athlete_id', athleteId);
        if (gameDate) formMeta.append('game_date', gameDate);
        if (teamPlayedOn) formMeta.append('team_played_on', teamPlayedOn);
        if (opponentTeam) formMeta.append('opponent_team', opponentTeam);

        var uploadNonce = null;
        var proxyUploadUrl = null;
        var proxyToken = null;
        var contentType = null;

        fetch('process_video.php', { method: 'POST', body: formMeta })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
                uploadNonce = data.upload_nonce;
                proxyUploadUrl = data.proxy_upload_url || null;
                proxyToken = data.proxy_token || null;
                contentType = data.content_type || file.type || 'application/octet-stream';
                crLog('Presigned URL obtained. Object key: ' + data.object_key);
                status.textContent = 'Uploading to cloud storage...';

                var url = data.presigned_url || proxyUploadUrl;
                var useProxy = !data.presigned_url && !!(proxyUploadUrl && proxyToken);
                if (!url) throw new Error('No upload URL available');
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    currentUploadXhr = xhr;
                    xhr.open('PUT', url, true);
                    xhr.setRequestHeader('Content-Type', contentType);
                    if (useProxy) xhr.setRequestHeader('X-Upload-Token', proxyToken);
                    var uploadStarted = false;
                    var lastLogTime = 0;
                    var connTimer = setTimeout(function() {
                        if (!uploadStarted) { xhr.abort(); reject(new Error('Connection timed out')); }
                    }, 30000);
                    xhr.upload.onprogress = function(ev) {
                        if (!uploadStarted && ev.loaded > 0) { uploadStarted = true; clearTimeout(connTimer); }
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            bar.style.width = pct + '%';
                            percent.textContent = pct + '%';
                            status.textContent = pct < 100 ? 'Uploading… ' + pct + '%' : 'Finalizing upload...';
                            var now = Date.now();
                            if (ev.loaded > 0 && (now - lastLogTime) >= PROGRESS_LOG_INTERVAL * 1000) {
                                lastLogTime = now;
                                crLog('Progress: ' + pct + '% — ' + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB');
                            }
                        }
                    };
                    xhr.onload = function() {
                        clearTimeout(connTimer);
                        if (xhr.status >= 200 && xhr.status < 300) { crLog('Upload completed in ' + crElapsed(uploadStart)); resolve(); }
                        else reject(new Error('Upload failed (HTTP ' + xhr.status + ')'));
                    };
                    xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during upload')); };
                    xhr.send(file);
                });
            })
            .then(function() {
                status.textContent = 'Confirming upload...';
                crLog('Confirming upload with server…');
                return (window.extractVideoThumbnail ? extractVideoThumbnail(file).catch(function(){ return null; }) : Promise.resolve(null))
                    .then(function(thumbBase64) {
                        var confirmData = new FormData();
                        confirmData.append('action', 'confirm_video_upload');
                        confirmData.append('csrf_token', csrfToken);
                        confirmData.append('upload_nonce', uploadNonce);
                        if (thumbBase64) confirmData.append('thumbnail_data', thumbBase64);
                        return fetch('process_video.php', { method: 'POST', body: confirmData, keepalive: true }).then(function(r) { return r.json(); });
                    });
            })
            .then(function(result) {
                if (result.success) {
                    crLog('Upload confirmed! Triggering background transcode…');
                    var tp = { action: 'trigger_transcode', object_key: result.object_key };
                    if (result.video_id) tp.video_id = result.video_id;
                    if (result.source_id) tp.source_id = result.source_id;
                    crPostAction(tp, csrfToken, { keepalive: true })
                        .then(function(t) { crLog('Transcode triggered (job: ' + (t.hls_job_id || 'N/A') + ')'); })
                        .catch(function(e) { crLogWarn('Transcode trigger: ' + e.message); });
                    crShowComplete(bar, percent, status, overlay, submitBtn, result.redirect);
                } else throw new Error(result.error || 'Confirmation failed');
            })
            .catch(function(err) {
                crLogError(err.message);
                overlay.style.display = 'none';
                submitBtn.disabled = false;
                showToast('Upload failed: ' + err.message, 'error');
            });
    }

    function crMultipartUpload(file, bar, percent, status, overlay, submitBtn, csrfToken,
                               title, videoCategory, description, coachId, athleteId, gameDate, teamPlayedOn, opponentTeam) {
        var totalParts = Math.ceil(file.size / PART_SIZE);
        var objectKey = '', uploadId = '', uploadNonce = '';
        var uploadStart = Date.now();

        crLog('Multipart upload: ' + totalParts + ' parts of ' + (PART_SIZE / 1048576) + ' MB');
        status.textContent = 'Initiating multipart upload…';

        var params = {
            action: 'initiate_multipart', upload_type: 'athlete_video',
            file_name: file.name, file_size: file.size, file_type: file.type || 'video/mp4',
            title: title, video_category: videoCategory, description: description
        };
        if (coachId) params.coach_id = coachId;
        if (athleteId) params.athlete_id = athleteId;
        if (gameDate) params.game_date = gameDate;
        if (teamPlayedOn) params.team_played_on = teamPlayedOn;
        if (opponentTeam) params.opponent_team = opponentTeam;

        crPostAction(params, csrfToken)
        .then(function(data) {
            objectKey = data.object_key; uploadId = data.upload_id; uploadNonce = data.upload_nonce;
            crLog('Multipart initiated. Object key: ' + objectKey);
            return crUploadAllParts(file, objectKey, uploadId, totalParts, bar, percent, status, csrfToken);
        })
        .then(function(parts) {
            crLog('All parts uploaded (' + crElapsed(uploadStart) + '). Completing…');
            status.textContent = 'Completing multipart upload…';
            return crPostAction({ action: 'complete_multipart', object_key: objectKey, upload_id: uploadId, parts: JSON.stringify(parts) }, csrfToken);
        })
        .then(function() {
            crLog('Confirming upload…');
            status.textContent = 'Confirming upload...';
            return crPostAction({ action: 'confirm_video_upload', upload_nonce: uploadNonce }, csrfToken, { keepalive: true });
        })
        .then(function(result) {
            if (result.success) {
                crLog('Upload confirmed! Triggering background transcode…');
                var tp = { action: 'trigger_transcode', object_key: result.object_key };
                if (result.video_id) tp.video_id = result.video_id;
                if (result.source_id) tp.source_id = result.source_id;
                crPostAction(tp, csrfToken, { keepalive: true })
                    .then(function(t) { crLog('Transcode triggered (job: ' + (t.hls_job_id || 'N/A') + ')'); })
                    .catch(function(e) { crLogWarn('Transcode trigger: ' + e.message); });
                crShowComplete(bar, percent, status, overlay, submitBtn, result.redirect);
            }
            else throw new Error(result.error || 'Confirmation failed');
        })
        .catch(function(err) {
            crLogError(err.message);
            overlay.style.display = 'none';
            submitBtn.disabled = false;
            showToast('Upload failed: ' + err.message, 'error');
            if (uploadId) crPostAction({ action: 'abort_multipart', object_key: objectKey, upload_id: uploadId }, csrfToken).catch(function() {});
        });
    }

    function crUploadAllParts(file, objectKey, uploadId, totalParts, bar, percent, statusEl, csrfToken) {
        var results = new Array(totalParts);
        var partBytes = new Array(totalParts);
        for (var i = 0; i < totalParts; i++) partBytes[i] = 0;
        var nextIndex = 0, activeCount = 0, completedCount = 0;
        return new Promise(function(resolve, reject) {
            var failed = false;
            function dispatch() {
                while (!failed && activeCount < CONCURRENT_PARTS && nextIndex < totalParts) {
                    (function(idx) {
                        var pn = idx + 1; activeCount++;
                        crUploadOnePart(file, objectKey, uploadId, pn, totalParts, partBytes, bar, percent, statusEl, csrfToken)
                            .then(function(r) { if (failed) return; partBytes[idx] = r.size; results[idx] = { PartNumber: pn, ETag: r.etag }; activeCount--; completedCount++; if (completedCount === totalParts) resolve(results); else dispatch(); })
                            .catch(function(e) { if (failed) return; failed = true; reject(e); });
                    })(nextIndex); nextIndex++;
                }
            }
            dispatch();
        });
    }

    function crUploadOnePart(file, objectKey, uploadId, partNumber, totalParts, partBytes, bar, percent, statusEl, csrfToken) {
        var start = (partNumber - 1) * PART_SIZE, end = Math.min(start + PART_SIZE, file.size), chunkSize = end - start, attempt = 0;
        function tryUpload() {
            attempt++;
            if (attempt > 1) crLogWarn('Retrying part ' + partNumber + '/' + totalParts + ' (attempt ' + attempt + ')');
            var chunk = file.slice(start, end), partStart = Date.now();
            return crPostAction({ action: 'presign_part', object_key: objectKey, upload_id: uploadId, part_number: partNumber }, csrfToken)
            .then(function(data) {
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('PUT', data.presigned_url, true);
                    var partIndex = partNumber - 1, lastProgressTime = Date.now();
                    crLog('Uploading part ' + partNumber + '/' + totalParts + ' (' + (chunkSize / 1048576).toFixed(1) + ' MB)…');
                    xhr.upload.onprogress = function(ev) {
                        if (ev.lengthComputable) {
                            if (ev.loaded > partBytes[partIndex]) lastProgressTime = Date.now();
                            partBytes[partIndex] = ev.loaded;
                            var total = 0; for (var i = 0; i < partBytes.length; i++) total += partBytes[i];
                            var pct = Math.round((total / file.size) * 100);
                            bar.style.width = pct + '%'; percent.textContent = pct + '%';
                            statusEl.textContent = 'Uploading… ' + pct + '%';
                        }
                    };
                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            var etag = xhr.getResponseHeader('ETag');
                            if (etag) etag = etag.replace(/"/g, '');
                            if (!etag) { reject(new Error('No ETag for part ' + partNumber)); return; }
                            crLog('Part ' + partNumber + '/' + totalParts + ' done (' + crElapsed(partStart) + ')');
                            resolve(etag);
                        } else reject(new Error('Part ' + partNumber + ' failed: HTTP ' + xhr.status));
                    };
                    xhr.onerror = function() { reject(new Error('Network error part ' + partNumber)); };
                    xhr.onabort = function() { reject(new Error('Part ' + partNumber + ' aborted')); };
                    xhr.send(chunk);
                });
            })
            .then(function(etag) { return { etag: etag, size: chunkSize }; })
            .catch(function(err) {
                if (attempt < MAX_PART_RETRIES) {
                    var d = Math.min(Math.pow(2, attempt), 30);
                    crLogWarn('Part ' + partNumber + ' failed: ' + err.message + ' — retrying in ' + d + 's');
                    return new Promise(function(res) { setTimeout(res, d * 1000); }).then(tryUpload);
                }
                throw err;
            });
        }
        return tryUpload();
    }
});

    // ── Ingest Device Tab ──────────────────────────────────
    (function() {
        var selectBtn = document.getElementById('ingestSelectDirBtn');
        var step1 = document.getElementById('ingestStep1');
        var step2 = document.getElementById('ingestStep2');
        var step3 = document.getElementById('ingestStep3');
        var scanStatus = document.getElementById('ingestScanStatus');
        var videoList = document.getElementById('ingestVideoList');
        var actionsDiv = document.getElementById('ingestActions');
        var startBtn = document.getElementById('ingestStartBtn');
        var cancelBtn = document.getElementById('ingestCancelBtn');
        var deleteCheckbox = document.getElementById('ingestDeleteAfter');
        var progressTitle = document.getElementById('ingestProgressTitle');
        var progressCount = document.getElementById('ingestProgressCount');
        var progressFill = document.getElementById('ingestProgressFill');
        var progressStatus = document.getElementById('ingestProgressStatus');
        var fsaNotSupported = document.getElementById('ingestFsaNotSupported');
        var fsaSupported = document.getElementById('ingestFsaSupported');

        if (typeof window.showDirectoryPicker !== 'function') {
            if (fsaNotSupported) fsaNotSupported.style.display = '';
            if (fsaSupported) fsaSupported.style.display = 'none';
            return;
        }

        var _discovered = [];
        var _dirHandle = null;

        if (selectBtn) selectBtn.addEventListener('click', async function() {
            try {
                _dirHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
                step1.style.display = 'none';
                step2.style.display = '';
                scanStatus.textContent = 'Scanning for videos…';
                videoList.innerHTML = '';
                actionsDiv.style.display = 'none';

                if (typeof AwOfflineQueue !== 'undefined' && AwOfflineQueue.scanForIngest) {
                    _discovered = await AwOfflineQueue.scanForIngest(_dirHandle);
                } else {
                    _discovered = [];
                }

                if (_discovered.length === 0) {
                    scanStatus.textContent = 'No video files with matching sidecar metadata found in this folder.';
                    return;
                }

                scanStatus.textContent = 'Found ' + _discovered.length + ' video(s) ready to import:';
                _discovered.forEach(function(item) {
                    var div = document.createElement('div');
                    div.className = 'ingest-video-item';
                    var sizeStr = item.meta && item.meta.file_size ? (item.meta.file_size / (1024*1024)).toFixed(1) + ' MB' : 'unknown size';
                    div.innerHTML = '<i class="fas fa-film"></i><div class="ingest-video-info"><strong>' +
                        (item.meta && item.meta.title ? item.meta.title : item.videoFile.name) +
                        '</strong><small>' + item.videoFile.name + ' — ' + sizeStr + '</small></div>';
                    videoList.appendChild(div);
                });
                actionsDiv.style.display = '';
            } catch (err) {
                if (err.name !== 'AbortError') {
                    scanStatus.textContent = 'Error: ' + err.message;
                }
            }
        });

        if (cancelBtn) cancelBtn.addEventListener('click', function() {
            step2.style.display = 'none';
            step1.style.display = '';
            _discovered = [];
            _dirHandle = null;
        });

        if (startBtn) startBtn.addEventListener('click', async function() {
            try {
                if (_discovered.length === 0) return;
                step2.style.display = 'none';
                step3.style.display = '';
                progressTitle.textContent = 'Importing videos to queue…';
                progressCount.textContent = '0 / ' + _discovered.length;
                progressFill.style.width = '0%';
                progressStatus.textContent = '';

                var imported = 0;
                var shouldDelete = deleteCheckbox && deleteCheckbox.checked;
                for (var i = 0; i < _discovered.length; i++) {
                    var item = _discovered[i];
                    progressStatus.textContent = 'Reading ' + item.videoFile.name + '…';
                    try {
                        if (typeof AwOfflineQueue !== 'undefined' && AwOfflineQueue.ingestFromDevice) {
                            await AwOfflineQueue.ingestFromDevice(item, { deleteAfterImport: shouldDelete, dirHandle: _dirHandle });
                        }
                        imported++;
                        progressCount.textContent = imported + ' / ' + _discovered.length;
                        progressFill.style.width = Math.round((imported / _discovered.length) * 100) + '%';
                    } catch (err) {
                        progressStatus.textContent = 'Failed: ' + item.videoFile.name + ' — ' + err.message;
                    }
                }
                progressTitle.textContent = 'Import complete!';
                progressStatus.textContent = imported + ' video(s) added to upload queue. They will upload automatically.';
                // Auto-start upload queue for imported videos
                if (typeof AwOfflineQueue !== 'undefined' && AwOfflineQueue.processQueue) {
                    AwOfflineQueue.processQueue();
                }
            } catch (err) {
                progressStatus.textContent = 'Import error: ' + err.message;
            }
        });
    })();
</script>
