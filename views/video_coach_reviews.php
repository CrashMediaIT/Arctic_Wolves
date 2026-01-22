<?php
// Get athletes for coach
$athletes_query = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
    FROM users u
    INNER JOIN sessions s ON s.athlete_id = u.id
    WHERE s.coach_id = ? AND u.is_active = 1
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
           s.session_type
    FROM videos v
    LEFT JOIN users a ON v.athlete_id = a.id
    LEFT JOIN sessions s ON v.session_id = s.id
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
    $video_query .= " AND DATE(v.created_at) = CURDATE()";
} elseif ($filter_period === 'week') {
    $video_query .= " AND v.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($filter_period === 'month') {
    $video_query .= " AND v.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}

$video_query .= " ORDER BY v.created_at DESC LIMIT 50";

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
    <!-- Action Bar -->
    <div class="action-bar">
        <button class="btn-primary btn-lg" data-action="show-upload-form"><i class="fas fa-upload"></i> Upload Video</button>
        <form method="GET" action="" class="filter-group">
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

    <!-- Upload Section (Initially Hidden) -->
    <div class="upload-section" id="uploadSection" style="display: none;">
        <div class="upload-card">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload Review Video</h3>
            
            <form class="upload-form" method="POST" action="process_video.php" enctype="multipart/form-data" data-form="video-upload">
                <input type="hidden" name="action" value="upload_video">
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
                    <button type="button" class="btn-secondary" data-action="hide-upload-form">Cancel</button>
                    <button type="submit" class="btn-primary" data-action="submit-form"><i class="fas fa-check"></i> Upload Video</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Videos List -->
    <div class="videos-list">
        <h3 class="section-title">Recent Uploads (<?= count($videos) ?>)</h3>
        
        <?php if (count($videos) > 0): ?>
            <?php foreach ($videos as $video): ?>
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
                    <span class="badge-<?= $video['review_status'] === 'reviewed' ? 'success' : 'warning' ?>">
                        <i class="fas fa-<?= $video['review_status'] === 'reviewed' ? 'check-circle' : 'clock' ?>"></i> 
                        <?= ucfirst($video['review_status']) ?>
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
                <i class="fas fa-video placeholder-icon"></i>
                <p class="placeholder-text">No videos uploaded yet. Click "Upload Video" to add your first review video.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
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
    margin-bottom: 30px;
}

.upload-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
}

.upload-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 25px;
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
    margin-bottom: 15px;
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 15px;
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
    padding: 30px;
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
    margin-bottom: 15px;
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
</style>
