<?php
// Get filter parameters
$filter_status = $_GET['filter_status'] ?? 'all';
$filter_drill_type = $_GET['filter_drill'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query for videos
$video_query = "
    SELECT v.*, 
           CONCAT(u.first_name, ' ', u.last_name) as coach_name,
           s.session_date,
           s.session_type
    FROM videos v
    LEFT JOIN users u ON v.coach_id = u.id
    LEFT JOIN sessions s ON v.session_id = s.id
    WHERE v.athlete_id = ?
";

$params = [$user_id];

// Apply filters
if ($filter_status !== 'all') {
    $video_query .= " AND v.review_status = ?";
    $params[] = $filter_status;
}

if ($filter_drill_type !== 'all') {
    $video_query .= " AND v.drill_type = ?";
    $params[] = $filter_drill_type;
}

if (!empty($search)) {
    $video_query .= " AND (v.drill_name LIKE ? OR v.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$video_query .= " ORDER BY v.created_at DESC";

$video_stmt = $pdo->prepare($video_query);
$video_stmt->execute($params);
$videos = $video_stmt->fetchAll();

// Get drill types for filter
$drill_types = $pdo->query("SELECT DISTINCT drill_type FROM videos WHERE drill_type IS NOT NULL ORDER BY drill_type")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Player Drill Video Review View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-video"></i> Drill Video Reviews
    </h1>
    <p class="page-description">View and review your drill performance videos</p>
</div>

<div class="video-content">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="page" value="drill_review">
            <select name="filter_status" class="form-input-small" data-action="auto-submit">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Videos</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Not Reviewed</option>
                <option value="reviewed" <?= $filter_status === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                <option value="flagged" <?= $filter_status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
            </select>
            <select name="filter_drill" class="form-input-small" data-action="auto-submit">
                <option value="all">All Drills</option>
                <?php foreach ($drill_types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= $filter_drill_type === $type ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-input-small" placeholder="Search videos..." value="<?= htmlspecialchars($search) ?>" data-action="search-debounce">
        </form>
    </div>

    <!-- Video Grid -->
    <div class="video-grid">
        <?php if (count($videos) > 0): ?>
            <?php foreach ($videos as $video): ?>
            <div class="video-card" data-component="VideoCard" data-video-id="<?= $video['id'] ?>">
                <div class="video-thumbnail">
                    <?php if (!empty($video['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Video thumbnail">
                    <?php else: ?>
                        <div class="video-placeholder">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($video['duration'])): ?>
                        <span class="video-duration"><?= htmlspecialchars($video['duration']) ?></span>
                    <?php endif; ?>
                    <span class="video-status <?= $video['review_status'] ?>">
                        <i class="fas fa-<?= $video['review_status'] === 'reviewed' ? 'check-circle' : 'clock' ?>"></i>
                    </span>
                </div>
                <div class="video-info">
                    <h4 class="video-title"><?= htmlspecialchars($video['drill_name']) ?></h4>
                    <div class="video-meta">
                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($video['created_at'])) ?></span>
                        <?php if (!empty($video['coach_name'])): ?>
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['coach_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($video['rating'] > 0): ?>
                        <div class="video-rating">
                            <span class="rating-label">Rating:</span>
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?= $i <= $video['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="video-actions">
                    <button class="btn-primary btn-full" data-action="view-video" data-video-id="<?= $video['id'] ?>">
                        <i class="fas fa-play"></i> Watch & Review
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-video placeholder-icon"></i>
                <p class="placeholder-text">No drill videos available yet. Your coach will upload videos after your sessions.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Video Modal (Hidden by default) -->
<div class="video-modal" id="videoModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-video"></i> Video Review</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="video-player-container">
                <div class="video-player-placeholder">
                    <i class="fas fa-play-circle"></i>
                    <p>Video Player</p>
                </div>
            </div>
            <div class="video-review-section">
                <h4>Coach's Review</h4>
                <div class="coach-comments">
                    <p class="placeholder-text">Coach comments will appear here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.video-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.video-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.1);
    border-color: var(--neon);
}

.video-thumbnail {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 aspect ratio */
    background: var(--bg-main);
    overflow: hidden;
}

.video-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
}

.video-placeholder i {
    font-size: 48px;
    color: var(--neon);
    opacity: 0.5;
}

.video-duration {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-status {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-status.reviewed {
    background: #10b981;
    color: #fff;
}

.video-status.pending {
    background: var(--accent);
    color: #fff;
}

.video-info {
    padding: 15px;
}

.video-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.video-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.video-meta span {
    font-size: 12px;
    color: var(--text-dim);
}

.video-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.video-rating {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rating-label {
    font-size: 12px;
    color: var(--text-dim);
}

.stars {
    display: flex;
    gap: 3px;
}

.stars i {
    color: var(--accent);
    font-size: 14px;
}

.video-actions {
    padding: 15px;
    border-top: 1px solid var(--border);
}

.video-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
}

.modal-content {
    position: relative;
    width: 90%;
    max-width: 1200px;
    margin: 50px auto;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 700;
}

.modal-header h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.modal-close:hover {
    background: var(--neon);
    border-color: var(--neon);
}

.modal-body {
    padding: 20px;
}

.video-player-container {
    margin-bottom: 20px;
}

.video-player-placeholder {
    width: 100%;
    padding-top: 56.25%;
    background: var(--bg-main);
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}

.video-player-placeholder i {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 64px;
    color: var(--neon);
    opacity: 0.3;
}

.video-review-section h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 15px;
}

.coach-comments {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--neon);
    opacity: 0.3;
    display: block;
    text-align: center;
    margin-bottom: 20px;
}
</style>
