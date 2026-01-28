<?php
// Video Parent Page with Tabs
$tab = $_GET['page'] ?? 'video';
if ($tab === 'video') $tab = 'drill_review'; // Default tab
?>

<style>
/* Video Tabs Navigation - Financial Reports Hub Style */
.video-tabs-wrapper { display: flex; align-items: stretch; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.video-tabs { display: flex; flex: 1; gap: 0; }
.video-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.video-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.video-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.video-tab i { font-size: 16px; }
.video-tabs-action { display: flex; align-items: center; padding: 12px 16px; border-left: 1px solid var(--border); }
.video-tabs-action .btn { white-space: nowrap; }

/* Tab Content Container */
.video-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-video"></i> Video</h1>
    <p class="page-description">Watch session videos and upload videos for coach review</p>
</div>

<!-- Tabs Navigation -->
<div class="video-tabs-wrapper">
    <div class="video-tabs">
        <a href="?page=drill_review" class="video-tab <?= $tab === 'drill_review' ? 'active' : '' ?>">
            <i class="fas fa-film"></i> Drill Review
        </a>
        <a href="?page=coaches_reviews" class="video-tab <?= $tab === 'coaches_reviews' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Coach Review
        </a>
    </div>
    <?php if($isAnyCoach): ?>
    <div class="video-tabs-action">
        <a href="?page=record_drill_video" class="btn btn-primary">
            <i class="fas fa-video"></i> Record Drill Video
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="video-tab-content">
    <?php
    if ($tab === 'drill_review') {
        include __DIR__ . '/video_drill_review.php';
    } elseif ($tab === 'coaches_reviews') {
        include __DIR__ . '/video_coach_reviews.php';
    }
    ?>
</div>
