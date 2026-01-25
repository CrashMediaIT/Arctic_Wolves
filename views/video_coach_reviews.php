<?php
// Get athletes for coach
$athletes_query = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
    FROM users u
    INNER JOIN videos v ON v.athlete_id = u.id
    WHERE v.coach_id = ? AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
";
$athletes_stmt = $pdo->prepare($athletes_query);
$athletes_stmt->execute([$user_id]);
$athletes = $athletes_stmt->fetchAll();

// Get filter parameters
$filter_athlete = $_GET['filter_athlete'] ?? 'all';
$filter_period = $_GET['filter_period'] ?? 'all';

// Build query for videos
$video_query = "
    SELECT v.*, 
           CONCAT(a.first_name, ' ', a.last_name) as athlete_name,
           s.session_date,
           st.name as session_type_name
    FROM videos v
    LEFT JOIN users a ON v.athlete_id = a.id
    LEFT JOIN sessions s ON v.session_id = s.id
    LEFT JOIN session_types st ON s.session_type_id = st.id
    WHERE v.coach_id = ?
";

$params = [$user_id];

// Apply athlete filter
if ($filter_athlete !== 'all') {
    $video_query .= " AND v.athlete_id = ?";
    $params[] = $filter_athlete;
}

// Apply period filter
if ($filter_period === 'today') {
    $video_query .= " AND DATE(v.upload_date) = CURDATE()";
} elseif ($filter_period === 'week') {
    $video_query .= " AND v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($filter_period === 'month') {
    $video_query .= " AND v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}

$video_query .= " ORDER BY v.upload_date DESC LIMIT 50";

$video_stmt = $pdo->prepare($video_query);
$video_stmt->execute($params);
$videos = $video_stmt->fetchAll();
?>

<!-- Coach Uploads View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-video"></i> Coach Uploads
    </h1>
    <p class="page-description">Upload and manage athlete review videos</p>
</div>

<div class="coach-video-content">
    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" data-action="switch-tab" data-tab="pending">
                <i class="fas fa-clock"></i> Pending
            </button>
            <button class="tab-btn" data-action="switch-tab" data-tab="reviewed">
                <i class="fas fa-check-circle"></i> Reviewed
            </button>
            <button class="tab-btn" data-action="switch-tab" data-tab="upload">
                <i class="fas fa-upload"></i> Upload
            </button>
        </div>
        
        <!-- Filters (shown for pending and reviewed tabs) -->
        <form method="GET" action="" class="filter-group tabs-filters">
            <input type="hidden" name="page" value="coaches_reviews">
            <select name="filter_athlete" class="form-input-small" data-action="auto-submit">
                <option value="all">All Athletes</option>
                <?php foreach ($athletes as $athlete): ?>
                    <option value="<?= $athlete['id'] ?>" <?= $filter_athlete == $athlete['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="filter_period" class="form-input-small" data-action="auto-submit">
                <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>All Sessions</option>
                <option value="today" <?= $filter_period === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
            </select>
        </form>
    </div>
    
    <?php
    // Separate videos by status
    $pending_videos = array_filter($videos, function($v) { 
        return $v['status'] === 'pending_review'; 
    });
    $reviewed_videos = array_filter($videos, function($v) { 
        return $v['status'] === 'reviewed'; 
    });
    ?>

    <!-- Tab Content: Pending Videos -->
    <div class="tab-content active" id="pending-tab">
        <div class="videos-list">
            <h3 class="section-title">Pending Reviews (<?= count($pending_videos) ?>)</h3>
            
            <?php if (count($pending_videos) > 0): ?>
                <?php foreach ($pending_videos as $video): ?>
                <div class="video-list-item" data-component="VideoListItem" data-video-id="<?= $video['id'] ?>">
                    <div class="video-thumbnail-small">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-video"></i>
                        <?php endif; ?>
                        <span class="play-overlay"><i class="fas fa-play"></i></span>
                    </div>
                    <div class="video-details">
                        <h4><?= htmlspecialchars($video['drill_name']) ?> - <?= htmlspecialchars($video['athlete_name']) ?></h4>
                        <div class="video-meta">
                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['created_at'])) ?></span>
                            <?php if (!empty($video['duration'])): ?>
                                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($video['duration']) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($video['drill_type']) ?></span>
                        </div>
                    </div>
                    <div class="video-status-badge">
                        <span class="badge-warning">
                            <i class="fas fa-clock"></i> Pending
                        </span>
                    </div>
                    <div class="video-actions-inline">
                        <button class="btn-icon" title="View" data-action="view-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" title="Edit" data-action="edit-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Delete" data-action="delete-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="placeholder-container">
                    <i class="fas fa-clock placeholder-icon"></i>
                    <p class="placeholder-text">No pending reviews. All videos have been reviewed!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Content: Reviewed Videos -->
    <div class="tab-content" id="reviewed-tab">
        <div class="videos-list">
            <h3 class="section-title">Reviewed Videos (<?= count($reviewed_videos) ?>)</h3>
            
            <?php if (count($reviewed_videos) > 0): ?>
                <?php foreach ($reviewed_videos as $video): ?>
                <div class="video-list-item" data-component="VideoListItem" data-video-id="<?= $video['id'] ?>">
                    <div class="video-thumbnail-small">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-video"></i>
                        <?php endif; ?>
                        <span class="play-overlay"><i class="fas fa-play"></i></span>
                    </div>
                    <div class="video-details">
                        <h4><?= htmlspecialchars($video['drill_name']) ?> - <?= htmlspecialchars($video['athlete_name']) ?></h4>
                        <div class="video-meta">
                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['created_at'])) ?></span>
                            <?php if (!empty($video['duration'])): ?>
                                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($video['duration']) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($video['drill_type']) ?></span>
                        </div>
                        <?php if ($video['rating'] > 0): ?>
                            <div class="video-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?= $i <= $video['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="video-status-badge">
                        <span class="badge-success">
                            <i class="fas fa-check-circle"></i> Reviewed
                        </span>
                    </div>
                    <div class="video-actions-inline">
                        <button class="btn-icon" title="View" data-action="view-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" title="Edit" data-action="edit-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Delete" data-action="delete-video" data-video-id="<?= $video['id'] ?>"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="placeholder-container">
                    <i class="fas fa-check-circle placeholder-icon"></i>
                    <p class="placeholder-text">No reviewed videos yet. Review pending videos to see them here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Content: Upload Section -->
    <div class="tab-content" id="upload-tab">
        <div class="upload-card">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload Review Video</h3>
            
            <form class="upload-form" method="POST" action="process_video.php" enctype="multipart/form-data" data-form="video-upload">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="upload_video">
                <?php if ($user['role'] !== 'coach'): ?>
                    <div class="error">Unauthorized: Only coaches can upload videos</div>
                    <?php return; ?>
                <?php endif; ?>
                <!-- Note: coach_id will be validated server-side from session -->
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Athlete *</label>
                        <select name="athlete_id" class="form-input" required data-field="athlete-select">
                            <option value="">-- Select Athlete --</option>
                            <?php foreach ($athletes as $athlete): ?>
                                <option value="<?= $athlete['id'] ?>">
                                    <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session Date *</label>
                        <input type="date" name="session_date" class="form-input" required max="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Drill Type *</label>
                        <select name="drill_type" class="form-input" required>
                            <option value="">-- Select Drill Type --</option>
                            <option>Skating</option>
                            <option>Shooting</option>
                            <option>Passing</option>
                            <option>Stickhandling</option>
                            <option>Defensive</option>
                            <option>Conditioning</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Drill Name *</label>
                        <input type="text" name="drill_name" class="form-input" placeholder="e.g., Crossover Drill" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Video File *</label>
                    <div class="file-upload-area" data-component="FileUpload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop video file here or click to browse</p>
                        <p class="file-name" style="display: none;"></p>
                        <input type="file" name="video_file" accept="video/*" style="display: none;" required data-field="video-file">
                        <button type="button" class="btn-secondary" data-action="trigger-file-input">Choose File</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Review Comments</label>
                    <textarea name="comments" class="form-textarea" rows="4" placeholder="Provide feedback and notes for the athlete..."></textarea>
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <input type="hidden" name="rating" value="0" data-field="rating-value">
                    <div class="rating-selector" data-component="RatingSelector">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" data-rating="<?= $i ?>" data-action="set-rating"></i>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" data-action="cancel">Cancel</button>
                    <button type="submit" class="btn-primary" data-action="submit-form"><i class="fas fa-check"></i> Upload Video</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* =========================================================
   COACH VIDEO REVIEWS - Modern Design System
   Updated: January 2026
   ========================================================= */

/* Tab content visibility */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeInUp 0.4s ease-out;
}

@keyframes fadeInUp {
    from { 
        opacity: 0; 
        transform: translateY(15px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Main content container */
.coach-video-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
}

/* =========================================================
   TABS NAVIGATION
   ========================================================= */
.tabs-container {
    background: linear-gradient(135deg, var(--bg-card) 0%, rgba(107, 70, 193, 0.08) 100%);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.tabs-nav {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    background: rgba(10, 10, 15, 0.6);
    padding: 6px;
    border-radius: 12px;
    border: 1px solid rgba(45, 45, 63, 0.5);
}

.tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    color: var(--text-dim);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.tab-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    opacity: 0;
    transition: opacity 0.25s ease;
    z-index: -1;
}

.tab-btn:hover {
    color: var(--text-white);
    background: rgba(107, 70, 193, 0.15);
}

.tab-btn.active {
    color: white;
    background: transparent;
    box-shadow: 0 4px 15px rgba(107, 70, 193, 0.4);
}

.tab-btn.active::before {
    opacity: 1;
}

.tab-btn i {
    font-size: 14px;
}

.tabs-filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.tabs-filters .form-input-small {
    min-width: 160px;
    height: 42px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    font-size: 14px;
    padding: 0 12px;
    transition: all 0.25s ease;
}

.tabs-filters .form-input-small:hover {
    border-color: var(--primary);
}

.tabs-filters .form-input-small:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    outline: none;
}

/* =========================================================
   VIDEO LIST CONTAINER
   ========================================================= */
.videos-list {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-white);
}

.section-title::before {
    content: '';
    width: 4px;
    height: 24px;
    background: linear-gradient(180deg, var(--primary), var(--accent));
    border-radius: 2px;
    flex-shrink: 0;
}

/* =========================================================
   VIDEO LIST ITEMS - Card Style
   ========================================================= */
.video-list-item {
    display: grid;
    grid-template-columns: 120px 1fr auto auto;
    align-items: center;
    gap: 20px;
    padding: 16px 20px;
    background: linear-gradient(135deg, var(--bg-main) 0%, rgba(22, 22, 31, 0.8) 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.video-list-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary), var(--accent));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.video-list-item:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 30px rgba(107, 70, 193, 0.2);
    transform: translateY(-2px);
}

.video-list-item:hover::before {
    opacity: 1;
}

.video-list-item:last-child {
    margin-bottom: 0;
}

/* =========================================================
   VIDEO THUMBNAIL
   ========================================================= */
.video-thumbnail-small {
    width: 120px;
    height: 80px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.15), rgba(139, 92, 246, 0.1));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
    border: 1px solid rgba(107, 70, 193, 0.2);
}

.video-thumbnail-small i {
    font-size: 28px;
    color: var(--primary);
    opacity: 0.5;
    transition: all 0.3s ease;
}

.video-thumbnail-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Play button overlay */
.video-thumbnail-small .play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 36px;
    height: 36px;
    background: rgba(107, 70, 193, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(107, 70, 193, 0.5);
}

.video-thumbnail-small .play-overlay i {
    font-size: 14px;
    color: white;
    opacity: 1;
    margin-left: 2px;
}

.video-list-item:hover .video-thumbnail-small .play-overlay {
    opacity: 1;
}

.video-list-item:hover .video-thumbnail-small > .fa-video {
    opacity: 0.3;
}

/* =========================================================
   VIDEO DETAILS
   ========================================================= */
.video-details {
    flex: 1;
    min-width: 0;
}

.video-details h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.video-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 4px;
}

.video-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-dim);
}

.video-meta i {
    color: var(--primary);
    font-size: 12px;
    opacity: 0.8;
}

.video-rating {
    margin-top: 10px;
    display: flex;
    gap: 3px;
}

.video-rating i {
    color: #F59E0B;
    font-size: 13px;
}

.video-rating i.far {
    color: rgba(245, 158, 11, 0.3);
}

/* =========================================================
   STATUS BADGES
   ========================================================= */
.video-status-badge {
    flex-shrink: 0;
}

.badge-success,
.badge-warning {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.badge-success {
    background: rgba(16, 185, 129, 0.12);
    color: #10B981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.12);
    color: #F59E0B;
    border: 1px solid rgba(245, 158, 11, 0.25);
}

.badge-success i,
.badge-warning i {
    font-size: 11px;
}

/* =========================================================
   ACTION BUTTONS
   ========================================================= */
.video-actions-inline {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.btn-icon {
    width: 38px;
    height: 38px;
    background: rgba(22, 22, 31, 0.8);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
}

.btn-icon[title="Delete"]:hover {
    background: #EF4444;
    border-color: #EF4444;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

/* =========================================================
   UPLOAD SECTION
   ========================================================= */
.upload-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.upload-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-white);
}

.upload-card h3 i {
    color: var(--primary);
    font-size: 22px;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 48px 32px;
    text-align: center;
    background: linear-gradient(135deg, var(--bg-main) 0%, rgba(107, 70, 193, 0.03) 100%);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.06);
}

.file-upload-area.drag-over {
    border-color: var(--accent);
    background: rgba(139, 92, 246, 0.1);
    transform: scale(1.01);
    box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
}

.file-upload-area i {
    font-size: 52px;
    color: var(--primary);
    opacity: 0.5;
    display: block;
    margin-bottom: 16px;
    transition: all 0.3s ease;
}

.file-upload-area:hover i {
    opacity: 0.8;
    transform: translateY(-4px);
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 16px;
    font-size: 15px;
    line-height: 1.5;
}

.file-upload-area .file-name {
    color: var(--accent);
    font-weight: 600;
    margin-top: 12px;
}

/* =========================================================
   RATING SELECTOR
   ========================================================= */
.rating-selector {
    display: flex;
    gap: 6px;
    font-size: 26px;
}

.rating-selector i {
    color: rgba(245, 158, 11, 0.25);
    cursor: pointer;
    transition: all 0.2s ease;
}

.rating-selector i:hover {
    color: #F59E0B;
    transform: scale(1.2);
}

.rating-selector i.active {
    color: #F59E0B;
}

.rating-selector i.active:hover {
    transform: scale(1.1);
}

/* =========================================================
   EMPTY STATES / PLACEHOLDERS
   ========================================================= */
.placeholder-container {
    text-align: center;
    padding: 60px 24px;
    margin-top: 20px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.25;
    margin-bottom: 20px;
    display: block;
}

.placeholder-text {
    font-size: 15px;
    color: var(--text-dim);
    margin-bottom: 0;
    max-width: 360px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

/* =========================================================
   FORM STYLING
   ========================================================= */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    margin-top: 28px;
}

/* =========================================================
   RESPONSIVE DESIGN
   ========================================================= */
@media (max-width: 992px) {
    .video-list-item {
        grid-template-columns: 100px 1fr;
        grid-template-rows: auto auto;
        gap: 12px 16px;
    }
    
    .video-thumbnail-small {
        width: 100px;
        height: 68px;
        grid-row: span 2;
    }
    
    .video-status-badge {
        justify-self: start;
    }
    
    .video-actions-inline {
        grid-column: 1 / -1;
        justify-content: flex-end;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        margin-top: 4px;
    }
}

@media (max-width: 768px) {
    .coach-video-content {
        padding: 0 12px;
    }
    
    .tabs-container {
        flex-direction: column;
        align-items: stretch;
        padding: 16px;
        gap: 16px;
    }
    
    .tabs-nav {
        justify-content: center;
        width: 100%;
    }
    
    .tab-btn {
        flex: 1;
        justify-content: center;
        padding: 10px 12px;
        font-size: 13px;
    }
    
    .tabs-filters {
        justify-content: center;
        width: 100%;
    }
    
    .tabs-filters .form-input-small {
        flex: 1;
        min-width: 0;
    }
    
    .videos-list {
        padding: 20px 16px;
        border-radius: 12px;
    }
    
    .video-list-item {
        grid-template-columns: 80px 1fr;
        padding: 14px;
    }
    
    .video-thumbnail-small {
        width: 80px;
        height: 56px;
    }
    
    .video-details h4 {
        font-size: 14px;
    }
    
    .video-meta {
        gap: 10px;
    }
    
    .video-meta span {
        font-size: 12px;
    }
    
    .upload-card {
        padding: 24px 20px;
    }
    
    .file-upload-area {
        padding: 32px 20px;
    }
    
    .file-upload-area i {
        font-size: 42px;
    }
}

@media (max-width: 480px) {
    .tab-btn span {
        display: none;
    }
    
    .tab-btn i {
        margin: 0;
    }
    
    .video-list-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .video-thumbnail-small {
        width: 100%;
        height: 120px;
        margin-bottom: 8px;
    }
    
    .video-status-badge {
        justify-self: center;
        margin: 8px 0;
    }
    
    .video-actions-inline {
        justify-content: center;
    }
}
</style>
