<?php
/**
 * Game Plan - Home / Dashboard View
 * Overview with recent videos and quick stats.
 */
?>
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-house"></i> Dashboard</h1>
    <p class="gp-page-desc">Video review &amp; game planning overview</p>
</div>

<!-- Recent Videos -->
<div class="gp-section">
    <div class="gp-section-title"><i class="fas fa-film"></i> Recent Videos</div>

    <?php if (empty($recentVideos)): ?>
    <div class="gp-empty">
        <i class="fas fa-video-slash"></i>
        <p>No videos yet. Upload videos from the main dashboard or record them in the app.</p>
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
