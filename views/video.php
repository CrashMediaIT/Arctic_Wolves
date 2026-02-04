<?php
// Video Parent Page with Tabs
$tab = $_GET['page'] ?? 'video';
if ($tab === 'video') $tab = 'drill_review'; // Default tab
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-video"></i> Video</h1>
    <p class="page-description">Watch session videos and upload videos for coach review</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs-wrapper">
    <div class="page-tabs">
        <a href="?page=drill_review" class="page-tab <?= $tab === 'drill_review' ? 'active' : '' ?>">
            <i class="fas fa-film"></i> Drill Review
        </a>
        <a href="?page=coaches_reviews" class="page-tab <?= $tab === 'coaches_reviews' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Coach Review
        </a>
    </div>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'drill_review') {
        include __DIR__ . '/video_drill_review.php';
    } elseif ($tab === 'coaches_reviews') {
        include __DIR__ . '/video_coach_reviews.php';
    }
    ?>
</div>
