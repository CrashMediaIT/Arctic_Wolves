<?php
/**
 * Coach Review Tab - Video Upload and Review
 * Athletes can upload videos (game or drill) for coach review
 * Coaches can review, add notes, and mark videos as reviewed
 */

// Get the current user's assigned coach
$assigned_coach_id = null;
$assigned_coach_name = '';
if ($user_role === 'athlete') {
    $coach_stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
    $coach_stmt->execute([$user_id]);
    $coach_row = $coach_stmt->fetch();
    $assigned_coach_id = $coach_row['assigned_coach_id'] ?? null;
    
    if ($assigned_coach_id) {
        $coach_name_stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $coach_name_stmt->execute([$assigned_coach_id]);
        $assigned_coach_name = $coach_name_stmt->fetchColumn() ?: '';
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
        WHERE u.assigned_coach_id = ? AND u.is_active = 1 AND u.role = 'athlete'
        ORDER BY u.last_name, u.first_name
    ";
    $athletes_stmt = $pdo->prepare($athletes_query);
    $athletes_stmt->execute([$user_id]);
    $athletes = $athletes_stmt->fetchAll();
    
    if (empty($athletes)) {
        $athletes_query = "
            SELECT u.id, u.first_name, u.last_name, u.email
            FROM users u
            WHERE u.is_active = 1 AND u.role = 'athlete'
            ORDER BY u.last_name, u.first_name
        ";
        $athletes_stmt = $pdo->query($athletes_query);
        $athletes = $athletes_stmt->fetchAll();
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
               CONCAT(a.first_name, ' ', a.last_name) as athlete_name,
               a.email as athlete_email,
               CONCAT(c.first_name, ' ', c.last_name) as coach_name,
               d.title as drill_title,
               s.title as session_title,
               s.session_date
        FROM videos v
        LEFT JOIN users a ON v.athlete_id = a.id
        LEFT JOIN users c ON v.coach_id = c.id
        LEFT JOIN drills d ON v.drill_id = d.id
        LEFT JOIN sessions s ON v.session_id = s.id
        WHERE v.video_type = 'uploaded_by_athlete'
        AND (v.coach_id = ? OR a.assigned_coach_id = ?)
    ";
    $params = [$user_id, $user_id];
} else {
    $video_query = "
        SELECT v.*, 
               CONCAT(a.first_name, ' ', a.last_name) as athlete_name,
               a.email as athlete_email,
               CONCAT(c.first_name, ' ', c.last_name) as coach_name,
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
                <div class="video-list-item" data-video-id="<?= $video['id'] ?>">
                    <div class="video-thumbnail-small">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-video"></i>
                        <?php endif; ?>
                        <span class="play-overlay"><i class="fas fa-play"></i></span>
                    </div>
                    <div class="video-details">
                        <h4><?= htmlspecialchars($video['title']) ?></h4>
                        <div class="video-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['athlete_name']) ?></span>
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
                        <button class="btn-icon" title="View" data-action="view-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-eye"></i></button>
                        <?php if ($isAnyCoach): ?>
                        <button class="btn-icon btn-review" title="Review" data-action="review-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-check"></i></button>
                        <?php endif; ?>
                        <button class="btn-icon" title="Delete" data-action="delete-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-trash"></i></button>
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
                <div class="video-list-item" data-video-id="<?= $video['id'] ?>">
                    <div class="video-thumbnail-small">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-video"></i>
                        <?php endif; ?>
                        <span class="play-overlay"><i class="fas fa-play"></i></span>
                    </div>
                    <div class="video-details">
                        <h4><?= htmlspecialchars($video['title']) ?></h4>
                        <div class="video-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['athlete_name']) ?></span>
                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['upload_date'])) ?></span>
                            <span class="video-category-badge <?= ($video['video_category'] ?? 'drill') === 'game' ? 'badge-game' : 'badge-drill' ?>">
                                <i class="fas <?= ($video['video_category'] ?? 'drill') === 'game' ? 'fa-hockey-puck' : 'fa-dumbbell' ?>"></i>
                                <?= ucfirst($video['video_category'] ?? 'drill') ?>
                            </span>
                            <?php if (!empty($video['coach_name'])): ?>
                                <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($video['coach_name']) ?></span>
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
                        <button class="btn-icon" title="View" data-action="view-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" title="View Feedback" data-action="view-feedback" data-video-id="<?= $video['id'] ?>"><i class="fas fa-comments"></i></button>
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
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>You don't have an assigned coach yet. Please contact an administrator.</span>
            </div>
            <?php else: ?>
            
            <form class="upload-form" method="POST" action="process_video.php" enctype="multipart/form-data" data-form="video-upload">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="athlete_upload_video">
                <?php if (!$isAnyCoach): ?>
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

                <?php if ($isAnyCoach): ?>
                <div class="form-group">
                    <label>Athlete *</label>
                    <select name="athlete_id" class="form-input" required>
                        <option value="">-- Select Athlete --</option>
                        <?php foreach ($athletes as $athlete): ?>
                            <option value="<?= $athlete['id'] ?>">
                                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Video File *</label>
                    <div class="file-upload-area" data-component="FileUpload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop video file here or click to browse</p>
                        <p class="file-hint">Supported: MP4, MOV, AVI, WebM (Max 500MB)</p>
                        <p class="file-name" style="display: none;"></p>
                        <input type="file" name="video_file" accept="video/*" style="display: none;" required data-field="video-file">
                        <button type="button" class="btn-secondary" data-action="trigger-file-input">Choose File</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes for Coach</label>
                    <textarea name="description" class="form-textarea" rows="4" placeholder="Describe what you'd like feedback on..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" data-action="cancel">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-upload"></i> Upload for Review</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isAnyCoach): ?>
<div class="modal" id="reviewModal" style="display: none;">
    <div class="modal-overlay" data-action="close-modal"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> Review Video</h3>
            <button class="modal-close" data-action="close-modal"><i class="fas fa-times"></i></button>
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

.video-list-item { display: grid; grid-template-columns: 120px 1fr auto auto; align-items: center; gap: 20px; padding: 16px 20px; background: linear-gradient(135deg, var(--bg-main) 0%, rgba(22, 22, 31, 0.8) 100%); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; transition: all 0.3s ease; }
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
            if (this.value === 'game') {
                gameFields.style.display = 'block';
                drillFields.style.display = 'none';
            } else {
                gameFields.style.display = 'none';
                drillFields.style.display = 'block';
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
        }
    });
});
</script>
