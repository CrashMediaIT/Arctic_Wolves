<?php
/**
 * Game Plan - Video Review View
 * Review, tag and clip game footage.
 */
?>
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-film"></i> Video Review</h1>
    <p class="gp-page-desc">Review, tag &amp; clip game footage</p>
</div>

<div class="gp-section">
    <?php if (empty($recentVideos)): ?>
    <div class="gp-empty">
        <i class="fas fa-video-slash"></i>
        <p>No videos available for review. Upload videos from the main dashboard.</p>
        <a href="/dashboard.php?page=video"><i class="fas fa-upload"></i> Go to Video Upload</a>
    </div>
    <?php else: ?>
    <div class="gp-grid">
        <?php foreach ($recentVideos as $video): ?>
        <div class="gp-card">
            <div class="gp-card-thumb">
                <i class="fas fa-play-circle"></i>
                <span class="gp-card-badge <?= ($video['status'] ?? '') === 'reviewed' ? 'reviewed' : 'pending' ?>">
                    <?= htmlspecialchars($video['status'] ?? 'pending') ?>
                </span>
            </div>
            <div class="gp-card-body">
                <div class="gp-card-title"><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></div>
                <div class="gp-card-meta">
                    <?php if (!empty($video['athlete_first_name'])): ?>
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['athlete_first_name'] . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($video['created_at'])) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
