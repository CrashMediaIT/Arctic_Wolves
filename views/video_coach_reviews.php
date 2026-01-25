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

<!-- Coach Review Videos View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-video"></i> Coach Review Videos
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
   COACH VIDEO REVIEWS - Consolidated Design
   ========================================================= */

/* Tab content visibility */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.action-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.btn-lg {
    height: 45px;
    padding: 0 30px;
}

.upload-section {
    margin-bottom: 24px;
}

.upload-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
}

.upload-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 24px;
}

.upload-card h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    background: var(--bg-main);
    transition: all 0.3s;
}

.file-upload-area:hover {
    border-color: var(--neon);
    background: rgba(255, 77, 0, 0.05);
}

.file-upload-area i {
    font-size: 48px;
    color: var(--neon);
    opacity: 0.5;
    display: block;
    margin-bottom: 12px;
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 12px;
}

.rating-selector {
    display: flex;
    gap: 10px;
    font-size: 24px;
}

.rating-selector i {
    color: var(--border);
    cursor: pointer;
    transition: all 0.3s;
}

.rating-selector i:hover,
.rating-selector i.active {
    color: var(--accent);
}

.videos-list {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.video-list-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 12px;
    transition: all 0.3s;
}

.video-list-item:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.video-thumbnail-small {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.video-thumbnail-small i {
    font-size: 32px;
    color: var(--neon);
    opacity: 0.5;
}

.video-details {
    flex: 1;
}

.video-details h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.video-status-badge {
    margin-left: auto;
}

.badge-success {
    background: #10b981;
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.badge-warning {
    background: #f59e0b;
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-actions-inline {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}

/* Placeholder Container for empty states */
.placeholder-container {
    text-align: center;
    padding: 40px 20px;
    margin-top: 20px;
}

.placeholder-icon {
    font-size: 48px;
    color: var(--primary);
    opacity: 0.4;
    margin-bottom: 16px;
    display: block;
}

.placeholder-text {
    font-size: 16px;
    color: var(--text-dim);
    margin-bottom: 12px;
}

/* Improved spacing between badge icon and text */
.badge-warning i,
.badge-success i {
    margin-right: 6px;
}

/* Video meta spacing */
.video-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.video-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-dim);
}

.video-meta i {
    color: var(--primary);
}

/* Enhanced Coach Reviews Design */
.coach-video-content {
    max-width: 1400px;
    margin: 0 auto;
}

/* Improved tabs container */
.tabs-container {
    background: linear-gradient(135deg, var(--bg-card) 0%, rgba(107, 70, 193, 0.05) 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.tabs-nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    background: var(--bg-main);
    padding: 6px;
    border-radius: 10px;
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    color: var(--text-dim);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-btn:hover {
    color: var(--text-white);
    background: rgba(107, 70, 193, 0.1);
}

.tab-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light, #8B5CF6));
    color: white;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.tabs-filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.tabs-filters .form-input-small {
    min-width: 150px;
}

/* Improved video list styling */
.videos-list {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title::before {
    content: '';
    width: 4px;
    height: 24px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 2px;
}

/* Enhanced video list items */
.video-list-item {
    display: grid;
    grid-template-columns: 100px 1fr auto auto;
    align-items: center;
    gap: 24px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
}

.video-list-item:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 32px rgba(107, 70, 193, 0.15);
    transform: translateY(-2px);
}

.video-thumbnail-small {
    width: 100px;
    height: 75px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(139, 92, 246, 0.1));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.video-thumbnail-small::after {
    content: '\f04b';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    color: white;
    font-size: 20px;
    background: rgba(0, 0, 0, 0.5);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.video-list-item:hover .video-thumbnail-small::after {
    opacity: 1;
}

.video-thumbnail-small i {
    font-size: 28px;
    color: var(--primary);
    opacity: 0.6;
}

.video-thumbnail-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-details h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
    line-height: 1.4;
}

.video-rating {
    margin-top: 8px;
}

.video-rating i {
    color: #f59e0b;
    font-size: 14px;
}

.video-rating i.far {
    color: var(--border);
}

/* Status badges */
.video-status-badge .badge-success,
.video-status-badge .badge-warning {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

/* Action buttons */
.video-actions-inline {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 42px;
    height: 42px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    transform: scale(1.05);
}

/* Upload section improvements */
.upload-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
}

.upload-card h3 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.upload-card h3 i {
    color: var(--primary);
    font-size: 24px;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 48px;
    text-align: center;
    background: linear-gradient(135deg, var(--bg-main) 0%, rgba(107, 70, 193, 0.03) 100%);
    transition: all 0.3s;
    cursor: pointer;
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.08);
}

.file-upload-area.drag-over {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.12);
    transform: scale(1.01);
}

.file-upload-area i {
    font-size: 56px;
    color: var(--primary);
    opacity: 0.6;
    display: block;
    margin-bottom: 16px;
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 16px;
    font-size: 15px;
}

/* Rating selector */
.rating-selector {
    display: flex;
    gap: 8px;
    font-size: 28px;
}

.rating-selector i {
    color: var(--border);
    cursor: pointer;
    transition: all 0.2s;
}

.rating-selector i:hover,
.rating-selector i.active {
    color: #f59e0b;
    transform: scale(1.15);
}

/* Placeholder improvements */
.placeholder-container {
    text-align: center;
    padding: 60px 20px;
    margin-top: 20px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.3;
    margin-bottom: 20px;
    display: block;
}

.placeholder-text {
    font-size: 16px;
    color: var(--text-dim);
    margin-bottom: 12px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Form improvements */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 13px;
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
    padding-top: 20px;
    border-top: 1px solid var(--border);
    margin-top: 24px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .video-list-item {
        grid-template-columns: 80px 1fr;
        grid-template-rows: auto auto;
    }
    
    .video-status-badge,
    .video-actions-inline {
        grid-column: 2;
    }
    
    .tabs-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .tabs-nav {
        justify-content: center;
    }
    
    .tabs-filters {
        justify-content: center;
    }
}
</style>
